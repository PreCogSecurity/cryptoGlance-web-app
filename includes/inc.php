<?php
// looking for required extensions
if (!extension_loaded('sockets')) {
    die('The sockets extension is not loaded.');
} else if (!extension_loaded('curl')) {
    die('The curl extension is not loaded.');
} else if (ini_set('session.use_only_cookies', 1) === FALSE) {
    die('Error: We require that sessions only use cookies. Consult your PHP config to resolve this issue.');
}

require_once('config.php');
require_once('env.php');

// App Setup
set_time_limit(120); // Allow up to 2 minutes for all APIs to return information

// Errors are shown only when CG_DEBUG=1 (development). In production they are
// logged to disk instead of being exposed to every visitor.
$cgDebug = cg_env_bool('CG_DEBUG', false);
ini_set('display_errors', $cgDebug ? '1' : '0');
error_reporting($cgDebug ? E_ALL : E_ERROR);

date_default_timezone_set("GMT");

session_name('cryptoGlance'); // feel free to rename this!
// Gets cookies params
$cookieParams = session_get_cookie_params();
// Secure, HttpOnly cookies: the Secure flag is set automatically on HTTPS so
// that login cookies can never be read by scripts or sent over plain HTTP.
session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], false, true);
session_start();

define('DATA_PATH', getcwd() . DIRECTORY_SEPARATOR . DATA_FOLDER . DIRECTORY_SEPARATOR);

// Production error log lives with the rest of the runtime state.
if (!$cgDebug) {
    if (!is_dir(DATA_PATH . 'logs')) {
        @mkdir(DATA_PATH . 'logs', 0775, true);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', DATA_PATH . 'logs' . DIRECTORY_SEPARATOR . 'php-error.log');
}

require_once('cryptoglance.php');
$cryptoGlance = new CryptoGlance();
$settings = $cryptoGlance->getSettings();

// Current Build:
define('CURRENT_VERSION', 'v2.1');

// Misc function used throughout cryptoglance
require_once('functions.php');
