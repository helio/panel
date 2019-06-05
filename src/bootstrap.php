<?php

define('APPLICATION_ROOT', dirname(__DIR__));

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
} else {
    define('SITE_ENV', array_key_exists('SITE_ENV', $_SERVER) ? $_SERVER['SITE_ENV'] : 'PROD');
}

require APPLICATION_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// set logging
$logfile = APPLICATION_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . ((PHP_SAPI === 'cli') ? 'console' : 'app') . '.log';
define('LOG_DEST', isset($_ENV['docker']) ? 'php://stdout' : $logfile);
if (\array_key_exists('DEBUG', $_SERVER)) {
    define('LOG_LVL', \Monolog\Logger::DEBUG);
} else {
    define('LOG_LVL', SITE_ENV === 'PROD' ? \Monolog\Logger::WARNING : \Monolog\Logger::DEBUG);
}
// cleanup
unset($url, $file, $logfile);
