<?php

namespace Helio\Panel\Helper;

use Exception;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LogLevel;

/**
 * Class LogHelper.
 *
 * @method static addDebug($message, array $context = array())
 * @method static addInfo($message, array $context = array())
 * @method static addNotice($message, array $context = array())
 * @method static addWarning($message, array $context = array())
 * @method static addError($message, array $context = array())
 * @method static addCritical($message, array $context = array())
 * @method static addAlert($message, array $context = array())
 * @method static addEmergency($message, array $context = array())
 * @method static log($message, array $context = array())
 * @method static debug($message, array $context = array())
 * @method static info($message, array $context = array())
 * @method static notice($message, array $context = array())
 * @method static warn($message, array $context = array())
 * @method static warning($message, array $context = array())
 * @method static err($message, array $context = array())
 * @method static error($message, array $context = array())
 * @method static crit($message, array $context = array())
 * @method static critical($message, array $context = array())
 * @method static alert($message, array $context = array())
 * @method static emerg($message, array $context = array())
 * @method static emergency($message, array $context = array())
 * @method static setTimezone($message, array $context = array())
 *
 * @author    Christoph Buchli <support@snowflake.ch>
 */
class LogHelper implements HelperInterface
{
    /**
     * @var Logger[]
     */
    protected static $logger = [];

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
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
     */
    public static function logToConsole(...$args): void
    {
        foreach ($args as $arg) {
            if (is_object($arg) || is_array($arg) || is_resource($arg)) {
                $output = print_r($arg, true);
            } else {
                $output = (string) $arg;
            }

            fwrite(fopen('php://stdout', 'wb'), $output . "\n");
        }
    }

    /**
     * @param LogLevel $level
     * @param string   $message
     *
     * @throws Exception
     */
    protected static function write(LogLevel $level, string $message): void
    {
        self::getInstance()->log($level, $message);
    }

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
                    ->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING, false));
            }
        }

        return self::$logger[$suffix];
    }

    public static function pushProcessorToAllInstances(callable $callback)
    {
        foreach (self::$logger as $l) {
            $l->pushProcessor($callback);
        }
    }
}
