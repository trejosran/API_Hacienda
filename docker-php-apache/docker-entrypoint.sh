#!/bin/bash
set -e

echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Starting API Hacienda Configuration"

# Certificate store (the Hacienda .p12 uploads live here). On Railway this path should be a persistent
# Volume, which is mounted owned by root: give it to Apache's user and keep it private. Without a volume
# the directory is simply the container's own disk (the certificate is then lost on every redeploy).
mkdir -p /var/www/api/files
chown www-data:www-data /var/www/api/files 2>/dev/null || true
chmod 700 /var/www/api/files 2>/dev/null || true

setUpCryptoKey() {
    SETTINGS_FILE_PATH=/var/www/html/settings.php
    SETTINGS_TEMPLATE_FILE_PATH=/var/www/html/settings_original_docker.php.dist
    LOCALHOSTNAME=localhost
    CRLIBRE_API_HACIENDA_CRYPTO_KEY="non-set"

    if [ -e "${SETTINGS_FILE_PATH}" ]; then
        echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] *** Found ${SETTINGS_FILE_PATH}, checking if cryptoKey exists ***"

        # Check if cryptoKey is already set in settings.php
        if grep -q "'cryptoKey'" "${SETTINGS_FILE_PATH}"; then
            echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] CryptoKey already exists. Skipping key generation."
            return
        fi
    fi

    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Generating new settings.php"

    # Generate new settings.php
    cat "${SETTINGS_TEMPLATE_FILE_PATH}" \
    | sed "s/{dbName}/${CRLIBRE_API_HACIENDA_DB_NAME}/g" \
    | sed "s/{dbPss}/${CRLIBRE_API_HACIENDA_DB_PASSWORD}/g" \
    | sed "s/{dbUser}/${CRLIBRE_API_HACIENDA_DB_USER}/g" \
    | sed "s/{dbHost}/${CRLIBRE_API_HACIENDA_DB_HOST}/g" \
    > "${SETTINGS_FILE_PATH}"

    echo "Waiting on Database Server to be ready"
    while ! nc -z ${CRLIBRE_API_HACIENDA_DB_HOST} 3306; do
        echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Sleeping 1 sec.. waiting on MySQL"
        sleep 1
    done
    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] MySQL Is Up"

    echo "Waiting on Web Server to be ready"
    while ! nc -z ${LOCALHOSTNAME} 80; do
        echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Sleeping 1 sec... waiting on Apache"
        sleep 1
    done
    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Apache is Up"

    # Generate cryptoKey if it's missing
    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Trying to retrieve CryptoKey"
    curl -s "http://${LOCALHOSTNAME}:80/api.php?w=crypto&r=makeKey" -o /var/www/html/cryptoKey.json
    
    CRLIBRE_API_HACIENDA_CRYPTO_KEY=$(cat /var/www/html/cryptoKey.json | awk -F'"' '/"resp"/ {print $4}')
    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] CryptoKey generated"

    # Replace cryptoKey in settings.php
    sed -i "s/{cryptoKey}/${CRLIBRE_API_HACIENDA_CRYPTO_KEY}/g" "${SETTINGS_FILE_PATH}"
    
    echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Created ${SETTINGS_FILE_PATH} with CryptoKey ***"
}

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
        echo "[$(date -u +%d-%m-%Y_%H-%S-%N)][${0}] Starting Apache Web Server"
        set -- apache2-foreground "$@"
fi

setUpCryptoKey &

a2dismod mpm_event 2>/dev/null || true
   a2dismod mpm_worker 2>/dev/null || true
   a2enmod mpm_prefork 2>/dev/null || true
   
exec "$@"
