<?php
/*
 * Copyright (C) 2017-2025 CRLibre <https://crlibre.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * The api calls receiver :)
 */

error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

// Cambiamos zona horaria a Costa Rica
date_default_timezone_set("America/Costa_Rica");

if (file_exists("settings.php"))
    include_once("settings.php");
else
{
    echo "No se ha encontrado el archivo de configuración. Ve a la carpeta \"www\", renombra el archivo \"settings.php.dist\" a \"settings.php\" y ajusta los valores de configuración.";
    exit;
}

# Uncaught exceptions (e.g. mysqli_sql_exception) carry SQL fragments and data
# values in their messages. Log only a sanitized summary and answer with a bare
# 500, which matches what PHP did before for an uncaught exception.
set_exception_handler(function ($e) {
    $text = '[message withheld]';
    if (function_exists('grace_stripLiterals'))
        $text = grace_stripLiterals($e->getMessage());
    error_log(sprintf('Unhandled %s (code %s) at %s:%d: %s', get_class($e), $e->getCode(), basename($e->getFile()), $e->getLine(), $text));
    if (!headers_sent())
        http_response_code(500);
});

# PHP warnings/notices can also embed data values in their text. Log them with
# the same sanitizer instead of PHP's raw message. Errors silenced with @ or
# excluded by error_reporting() are ignored, as PHP itself would.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno))
        return true;
    $text = '[message withheld]';
    if (function_exists('grace_stripLiterals'))
        $text = grace_stripLiterals($errstr);
    error_log(sprintf('PHP error %d at %s:%d: %s', $errno, basename($errfile), $errline, $text));
    return true;
});

$corePath = $config['modules']['coreInstall'];
if (file_exists($corePath."core/boot.php"))
    include_once($corePath."core/boot.php");
else
{
    echo "No se ha encontrado la carpeta de \"api\", por favor verifica la configuración \$config['modules']['coreInstall'] en \"settings.php\".";
    exit;
}

/*CORS to the app access*/
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control, Pragma, Origin, Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, PUT, POST');


//print_r($argv);

if (isset($_GET['w']))
    params_set('w', $_GET);
else if (isset($_POST['w']))
    params_set('w', $_POST);
else if (isset($_PUT))
    params_set('w', $_PUT);
else if (file_get_contents("php://input") !== null)
{
    $content = file_get_contents("php://input");
    if (is_string($content) && is_array(json_decode($content, true)) && (json_last_error() == JSON_ERROR_NONE))
    {
        $datas = json_decode($content, true);
        if (isset($datas['w']))
            params_set('w', $datas);
    }
    else
    {
        grace_error("File Content Input: ". $content);
        die("La informacion json enviada contiene errores.");
    }
}
else if (isset($argv))
{
    grace_debug("Booting from cli...");

    foreach ($argv as $r)
    {
       list($key, $val) = explode("=", $r);
        $_POST[$key] = $val;
        grace_debug("Adding to post: $key => $val");
    }

    params_set('w', $_POST);
}

$r = boot_itUp();
