<?php

define('APPLICATION_ROOT', __DIR__ . '/..');

// make ENV work on local developemnt server
if (PHP_SAPI === 'cli-server') {

    // allow internal server to serve static files
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }

    // set ENV stuff from ENV instead of _SERVER
    define('SITE_ENV', 'DEV');
    $_SERVER['DB_USERNAME'] = array_key_exists('DB_USERNAME', $_ENV) ? $_ENV['DB_USERNAME'] : '';
    $_SERVER['DB_PASSWORD'] = array_key_exists('DB_PASSWORD', $_ENV) ? $_ENV['DB_PASSWORD'] : '';
    $_SERVER['DB_NAME'] = array_key_exists('DB_NAME', $_ENV) ? $_ENV['DB_NAME'] : '';
    $_SERVER['DB_HOST'] = array_key_exists('DB_HOST', $_ENV) ? $_ENV['DB_HOST'] : '';
    $_SERVER['DB_PORT'] = array_key_exists('DB_PORT', $_ENV) ? $_ENV['DB_PORT'] : '';
    $_SERVER['ZAPIER_HOOK_URL'] = array_key_exists('ZAPIER_HOOK_URL', $_ENV) ? $_ENV['ZAPIER_HOOK_URL'] : '';
    $_SERVER['JWT_SECRET'] = array_key_exists('JWT_SECRET', $_ENV) ? $_ENV['JWT_SECRET'] : '';
    $_SERVER['SCRIPT_HASH'] = array_key_exists('SCRIPT_HASH', $_ENV) ? $_ENV['SCRIPT_HASH'] : '';
} else {
    define('SITE_ENV', array_key_exists('SITE_ENV', $_SERVER) ? $_SERVER['SITE_ENV'] : 'PROD');
}

require APPLICATION_ROOT . '/vendor/autoload.php';

// set logging
$logfile = APPLICATION_ROOT . '/log/' . ((PHP_SAPI === 'cli') ? 'console' : 'app') . '.log';
define('LOG_DEST', isset($_ENV['docker']) ? 'php://stdout' : $logfile);
define('LOG_LVL', SITE_ENV === 'PROD' ? \Monolog\Logger::WARNING : \Monolog\Logger::DEBUG);

// cleanup
unset($url, $file, $logfile);
