<?php
/**
 * PHPUnit bootstrap.
 *
 * The application normally defines DATA_PATH in includes/inc.php, which also
 * starts a session and reads the real user_data directory. Tests only need the
 * pure helpers and the class library, so they point DATA_PATH at a throw-away
 * directory instead.
 */

$cgTestDataPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cryptoglance-tests-' . getmypid() . DIRECTORY_SEPARATOR;

if (!is_dir($cgTestDataPath)) {
    mkdir($cgTestDataPath, 0775, true);
}

define('DATA_PATH', $cgTestDataPath);
define('DATA_FOLDER', 'user_data');
define('CURRENT_VERSION', 'v2.1-test');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/functions.php';
