<?php

namespace Helio\Panel\Helper;

use Helio\Panel\Utility\ServerUtility;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;

/**
 * Class LogHelper
 *
 * @method static addDebug($message)
 * @method static addInfo($message)
 * @method static addNotice($message)
 * @method static addWarning($message)
 * @method static addError($message)
 * @method static addCritical($message)
 * @method static addAlert($message)
 * @method static addEmergency($message)
 * @method static log($message)
 * @method static debug($message)
 * @method static info($message)
 * @method static notice($message)
 * @method static warn($message)
 * @method static warning($message)
 * @method static err($message)
 * @method static error($message)
 * @method static crit($message)
 * @method static critical($message)
 * @method static alert($message)
 * @method static emerg($message)
 * @method static emergency($message)
 * @method static setTimezone($message)
 *
 * @package    Helio\Panel\Helper
 * @author    Christoph Buchli <support@snowflake.ch>
 */
class LogHelper
{


    /**
     * @var Logger
     */
    protected static $logger;


    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        return \call_user_func_array([self::get(), $name], $arguments);
    }


    /**
     * send a log message to the STDOUT stream.
     *
     * @param array<int, mixed> $args
     *
     * @return void
     */
    public static function logToConsole(...$args): void
    {

        foreach ($args as $arg) {

            if (\is_object($arg) || \is_array($arg) || \is_resource($arg)) {
                $output = print_r($arg, true);
            } else {
                $output = (string)$arg;
            }

            fwrite(fopen('php://stdout', 'wb'), $output . "\n");
        }

    }

    /**
     * @param LogLevel $level
     * @param string $message
     * @throws \Exception
     */
    protected static function write(LogLevel $level, string $message): void
    {
        self::get()->log($level, $message);
    }

    /**
     * @return Logger
     * @throws \Exception
     */
    public static function get(): Logger
    {
        if (!self::$logger) {
            self::$logger = (new Logger('helio.panel'))
                ->pushProcessor(new \Monolog\Processor\UidProcessor())
                ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL));
            self::$logger::setTimezone(ServerUtility::getTimezoneObject());

            // also log to stdout
            if (ServerUtility::isLocalDevEnv()) {
                self::$logger->pushHandler(new ErrorLogHandler());
            }
        }

        return self::$logger;
    }

}
