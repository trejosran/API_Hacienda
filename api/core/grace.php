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

/** @ingroup Constants
 *  @{
 */
# Details about how I am going to be running
//! Print it all in grace
define('GRACE_PRINT_ALL', conf_get('print_all', 'debug'));
//! Print absurd messages
define('GRACE_PRINT_ABSURD', conf_get('print_absurd', 'debug'));
//! Print debug messages
define('GRACE_PRINT_DEBUG', conf_get('print_debug', 'debug'));
//! Print error messages
define('GRACE_PRINT_ERROR', conf_get('print_error', 'debug'));

/** @} */
/** @ingroup GlobalVars
 *  @{
 */
//! Debug messages stored in Grace
$grace_logMsgs = array();
global $grace_logMsgs;

/** @} */

/**
 * Masks credential-like values (passwords, PINs, tokens, session keys, secrets)
 * in a message before it is written to any log. Messages that are not strings
 * are returned untouched.
 */
function grace_redact($msg)
{
    if (!is_string($msg))
        return $msg;

    $names = 'password|passwd|pwd|pin|client_secret|refresh_token|access_token|id_token|token|sessionKey|secret|cryptoKey|api_?key';

    $rules = array(
        # INSERT/REPLACE whose column list names a sensitive column: hide the VALUES
        '/^(\s*(?:INSERT|REPLACE)\b[^(]*\([^)]*\b(?:' . $names . ')\b[^)]*\)\s*VALUES\s*\().*(\)\s*;?\s*)$/is' => '$1***$2',
        # JSON: "password":"value"
        '/("(?:' . $names . ')"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i' => '$1"***"',
        # SQL: pwd='value'
        '/\b(' . $names . ')\b(\s*=\s*)\'(?:[^\'\\\\]|\\\\.)*\'/i' => '$1$2\'***\'',
        # urlencoded / key=value
        '/\b(' . $names . ')=([^&\s"\']+)/i' => '$1=***',
        # Authorization headers
        '/(bearer\s+)[A-Za-z0-9._~+\/=-]+/i' => '$1***',
    );

    foreach ($rules as $pattern => $replacement)
    {
        $masked = preg_replace($pattern, $replacement, $msg);
        # Fail closed: if a rule cannot run (e.g. backtrack limit) the original
        # text is never logged.
        if ($masked === null)
            return '[message withheld: could not be sanitized]';
        $msg = $masked;
    }

    return $msg;
}

/**
 * Removes every quoted literal and number from a SQL statement or a database
 * error message so it can be logged without data values. Fails closed: if the
 * text cannot be sanitized nothing from the original is returned.
 */
function grace_stripLiterals($text, $maxLen = 300)
{
    $patterns = array(
        "/'(?:[^'\\\\]|\\\\.|'')*'/s",
        '/"(?:[^"\\\\]|\\\\.|"")*"/s',
        # A literal cut short by truncation, e.g. "... near 'abc"
        "/'[^']*$/s",
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        '/\b\d+(?:\.\d+)?\b/',
        '/\s+/',
    );
    $replacements = array('?', '?', '?', '?', '?', ' ');

    $stripped = preg_replace($patterns, $replacements, (string) $text);
    if ($stripped === null)
        return '[unavailable: could not be sanitized]';

    return substr($stripped, 0, $maxLen);
}

/**
 * I am the one who actually talks
 * DO NOT call me directly, use the other functions
 */
function _grace_talk($msg, $who = 'info')
{
    global $grace_logMsgs;

    if (GRACE_PRINT_ALL)
    {
        # Never dump structures or large payloads (invoice data, XML, request
        # bodies) into the log: only short scalar text is kept, then redacted.
        if (is_array($msg) || is_object($msg))
            $msg = '[' . gettype($msg) . ' not logged]';
        else if (is_string($msg) && strlen($msg) > 1500)
            $msg = '[message of ' . strlen($msg) . ' bytes not logged]';
        $msg = grace_redact($msg);
        # Log injection: user-supplied text must not be able to start a fake log line
        if (is_string($msg))
            $msg = strtr($msg, array("\r" => '\\r', "\n" => '\\n'));
        # Format the message
        $msg = sprintf("[%s] %s @ %s", $who, date('y-m-d h:m:s', time()), $msg) . "\n";
        //echo "$msg" . ($who == 'a' ? "" : "\n" . "<br />");
        if (!file_exists(conf_get('coreInstall', 'modules') . "errors/"))
            mkdir(conf_get('coreInstall', 'modules') . "errors/", 0777, true);

        #error_log($msg, 3, conf_get('coreInstall', 'modules') . "errors/" . date('y_m_d_h', time()) . "_errors.log");
        #error_log($msg, 3, conf_get('coreInstall', 'modules') . "errors/" . "_errors.log");
        error_log($msg);
        # Add the message to the debug pool if you want me to store them in a file
        if (conf_get('logPath', 'grace', '') != '' && $who != 'a')
            $grace_logMsgs[] = $msg;
    }
}

/**
 * Logs all messages in this session
 */
function grace_storeLog()
{
    global $grace_logMsgs;

    # Add the last message
    grace_debug("Finished! Memory used: Mb" . (memory_get_peak_usage() / 1000000) . "<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<");

    # Do I have a place to store them?
    $fileName = conf_get('logPath', 'grace', '');

    if ($fileName != '')
    {
        # Create a new file every hour
        $fileName = $fileName . "wirez_" . date('y_m_d_h', time()) . ".txt";

        # Merge arrays to make them readable
        $grace_logMsgs = implode("\n", $grace_logMsgs) . "\n";

        //Open a connection
        $fp = fopen($fileName, 'a');
        if ($fp)
        {
            fwrite($fp, $grace_logMsgs);
            fclose($fp);
        }
    }
}

/**
 * Debuging messages
 */
function grace_debug($msg)
{
    if (GRACE_PRINT_DEBUG == true)
        _grace_talk($msg, 'd');
}

/**
 * Informational messages
 */
function grace_info($msg)
{
    if (GRACE_PRINT_DEBUG == true)
        _grace_talk($msg, 'i');
}

/**
 * Error messages
 */
function grace_error($msg)
{
    if (GRACE_PRINT_ERROR == true)
        _grace_talk($msg, 'e');
}

/**
 * Use this if you want to send messages that are absurd, that you want to see
 * only if you REALLY need it.
 */
function grace_absurd($msg)
{
    if (GRACE_PRINT_ABSURD == true)
        _grace_talk($msg, 'a');
}

/**
 * Very lightweight browser detection
 * http://us2.php.net/get_browser
 */
function lestatz_browserInfo($agent = null)
{
    // Declare known browsers to look for
    $known = array('msie', 'firefox', 'safari', 'webkit', 'opera', 'netscape', 'konqueror', 'gecko');

    // Clean up agent and build regex that matches phrases for known browsers (e.g. "Firefox/2.0" or "MSIE 6.0" (This only matches the 
    //	major and minor version numbers.  E.g. "2.0.0.6" is parsed as simply "2.0"
    $agent = strtolower($agent ? $agent : $_SERVER['HTTP_USER_AGENT']);
    $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9]+(?:\.[0-9]+)?)#';

    // Find all phrases (or return empty array if none found)
    if (!preg_match_all($pattern, $agent, $matches))
        return array();

    // Since some UAs have more than one phrase	(e.g Firefox has a Gecko phrase, Opera 7,8
    //  have a MSIE phrase), use the last one found (the right-most one in the UA).
    //  That's usually the most correct.
    $i = count($matches['browser']) - 1;

    return array($matches['browser'][$i] => $matches['version'][$i]);
}
