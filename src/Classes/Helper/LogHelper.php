<?php

namespace Helio\Panel\Helper;

use \Exception;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
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
class LogHelper implements HelperInterface
{


    /**
     * @var array<Logger>
     */
    protected static $logger = [];


    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws Exception
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([self::getInstance(), $name], $arguments);
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

            if (is_object($arg) || is_array($arg) || is_resource($arg)) {
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
     * @throws Exception
     */
    protected static function write(LogLevel $level, string $message): void
    {
        self::getInstance()->log($level, $message);
    }

    /**
     * @param string $suffix
     * @return Logger
     * @throws Exception
     */
    public static function getInstance(string $suffix = 'app'): Logger
    {
        if (!array_key_exists($suffix, self::$logger)) {
            // this needs to be done through str_replace and not enforces, since LOG_DEST can also be a php:// resource
            $filename = str_replace('.log', '-' . $suffix . '.log', LOG_DEST);

            self::$logger[$suffix] = (new Logger('helio.panel.' . $suffix))
                ->pushProcessor(new UidProcessor())
                ->pushHandler(new StreamHandler($filename, LOG_LVL));
            self::$logger[$suffix]::setTimezone(ServerUtility::getTimezoneObject());

            // also log to stdout
            if (ServerUtility::isLocalDevEnv()) {
                self::$logger[$suffix]
                    ->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING, false))
                    ->pushHandler(new StreamHandler('php://stdout'));
            }
        }

        return self::$logger[$suffix];
    }

}
