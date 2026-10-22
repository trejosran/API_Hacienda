FROM php:8.2.35-apache
LABEL version="1.1"
# VOLUME [ "/var/www/html" ]
# VOLUME [ "/var/www/api" ]
RUN apt-get update && apt-get -y install libpng-dev curl libcurl4-openssl-dev openssl netcat-openbsd
RUN docker-php-ext-install pdo pdo_mysql mysqli gd curl
RUN a2enmod rewrite
RUN a2dismod mpm_event mpm_worker >/dev/null 2>&1; a2enmod mpm_prefork
COPY ./docker-php-apache/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
COPY ./docker-php-apache/php-security.ini /usr/local/etc/php/conf.d/zz-security.ini
COPY ./docker-php-apache/security.conf /etc/apache2/conf-available/z-security.conf
RUN a2enconf z-security && apache2ctl configtest
COPY ./www/ /var/www/html
COPY ./api/ /var/www/api
RUN mkdir -p /var/www/api/errors
RUN mkdir -p /var/www/api/logs
RUN mkdir -p /var/www/api/files
# Set permissions on the /var/www/api directories
RUN chmod -R 755 /var/www/
RUN chmod -R 777 /var/www/api/errors
RUN chmod -R 777 /var/www/api/logs
RUN chmod -R 777 /var/www/api/files
RUN ln -s /usr/local/bin/docker-entrypoint.sh / # backwards compat
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
