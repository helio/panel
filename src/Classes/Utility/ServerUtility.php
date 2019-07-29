<?php

namespace Helio\Panel\Utility;

use \Exception;
use \RuntimeException;
use \InvalidArgumentException;
use \DateTime;
use \DateTimeZone;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Tuupola\Base62;

class ServerUtility extends AbstractUtility
{

    /**
     * THE ONLY place where a timezone is mentioned. This shall be read from database (in each entity) or ENV in the future.
     *
     * @var string
     */
    protected static $timeZone = 'Europe/Berlin';

    /**
     * @var bool
     */
    protected static $testMode = false;

    /**
     * NOTE: This is filled in test mode only!
     * @var array
     */
    protected static $lastExecutedShellCommand = [];


    /**
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {

        $protocol = 'http' . (self::isSecure() ? 's' : '');

        return $protocol . '://' . self::get('HTTP_HOST') . '/';
    }


    /**
     * @return DateTimeZone
     */
    public static function getTimezoneObject(): DateTimeZone
    {
        return new DateTimeZone(self::$timeZone);
    }


    /**
     * @return bool
     */
    public static function isSecure(): bool
    {
        if (strtolower(self::get('HTTPS', 'off')) !== 'off') {
            return true;
        }
        if (self::isBehindReverseProxy()) {
            return self::get('HTTTP_X_FORWARDED_PROTO', false) === 'https';
        }
        return false;
    }


    /**
     * @return bool
     */
    public static function isProd(): bool
    {
        return self::get('SITE_ENV') === 'PROD';
    }

    public static function isLocalDevEnv(): bool
    {
        return PHP_SAPI === 'cli-server' && SITE_ENV === 'DEV';
    }

    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return string
     *
     * TODO: Don't use _ENV or _SERVER anymore here, but use proper request params
     */
    public static function get(string $name, $default = null): string
    {
        // local development server has the stuff in _ENV
        if (PHP_SAPI === 'cli-server' && array_key_exists($name, $_ENV)) {
            return $_ENV[$name];
        }

        // look in _SERVER and request
        if (!array_key_exists($name, $_SERVER) || !$_SERVER[$name]) {
            if (App::isReady()) {
                try {
                    $reqParams = App::getApp()->getContainer()->get('request')->getServerParams();
                    if (array_key_exists($name, $reqParams) && $reqParams[$name]) {
                        return $reqParams[$name];
                    }
                } catch (Exception $e) {
                    // fallback to default.
                }
            }
            if ($default !== null) {
                return $default;
            }
            throw new RuntimeException('please set the ENV Variable ' . $name, 1530357047);
        }

        return $_SERVER[$name];
    }


    /**
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        return self::isBehindReverseProxy() ? self::get('HTTP_X_FORWARDED_FOR') : self::get('REMOTE_ADDR');
    }


    /**
     * @return bool
     */
    protected static function isBehindReverseProxy(): bool
    {
        return self::get('REMOTE_ADDR') === self::get('REVERSE_PROXY_IP', 'impossible') && self::get('HTTP_X_FORWARDED_FOR', false);
    }


    /**
     * @noinspection PhpDocMissingThrowsInspection some kind of IntelliJ Bug here...?
     * @param string $command
     * @return string|null
     */
    public static function executeShellCommand(string $command): ?string
    {
        try {
            $trace = (new Base62())->encode(random_bytes(12));
        } catch (Exception $e) {
            LogHelper::warn('random_bytes error: ' . $e->getMessage());
            $trace = 'etrace' . (new DateTime())->getTimestamp();
        }
        LogHelper::debug('executing shell command (' . $trace . '):' . "\n" . $command);

        if (self::$testMode) {
            self::$lastExecutedShellCommand[] = $command;
        }

        if (self::isProd() || self::get('ENFORCE_SYS_EXEC', false)) {
            $result = trim(@shell_exec($command));
        } else {
            $result = \Helio\Test\Infrastructure\Utility\ServerUtility::getMockResultForShellCommand($command);
        }

        LogHelper::debug('result of shell command (' . $trace . '):' . "\n" . print_r($result, true));
        return $result;
    }


    /**
     * @param array $params
     */
    public static function validateParams(array $params): void
    {
        foreach ($params as $item) {
            $res = preg_match('/[^0-9a-zA-Z\.\-_\/:\?=&",]/', $item);
            if ($res !== 0) {
                throw new InvalidArgumentException('suspicious shell command submitted: ' . $item, 1544664506);
            }
        }
    }


    /**
     * @param array $subpath
     * @return string
     */
    public static function getApplicationRootPath(array $subpath = []): string
    {
        $sub = $subpath ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $subpath) : '';

        return APPLICATION_ROOT . $sub;
    }

    /**
     * @param array $subpath
     * @return string
     */
    public static function getTmpPath(array $subpath = []): string
    {
        return self::getApplicationRootPath(array_merge(['tmp'], $subpath));
    }


    /**
     * @param array $subpath
     * @return string
     */
    public static function getTemplatesPath(array $subpath = []): string
    {
        return self::getApplicationRootPath(array_merge(['src', 'templates'], $subpath));
    }


    /**
     * @param array $subpath
     * @return string
     */
    public static function getClassesPath(array $subpath = []): string
    {
        return self::getApplicationRootPath(array_merge(['src', 'Classes'], $subpath));
    }


    /**
     * @param string $path
     * @return string
     */
    public static function getHashOfFile(string $path): string
    {
        return sha1_file($path);
    }


    /**
     * @param string $string
     * @return string
     */
    public static function getHashOfString(string $string): string
    {
        return sha1($string);
    }


    /**
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function getShortHashOfString(string $string, int $length = 8): string
    {
        return substr(sha1($string), 0, $length);
    }

    /**
     *
     */
    public static function setTesting(): void
    {
        self::$testMode = true;
    }

    /**
     * @param int $offset
     * @return string
     */
    public static function getLastExecutedShellCommand(int $offset = 0): string
    {
        $index = count(self::$lastExecutedShellCommand) - $offset - 1;
        return $index >= 0 ? self::$lastExecutedShellCommand[$index] : '';
    }

    /**
     * @param string $folder
     * @param string $fileEnding
     * @return array
     *
     * TODO: test this, it's quite pecular and only used for apidoc so far
     */
    public static function getAllFilesInFolder(string $folder, string $fileEnding = ''): array
    {
        $result = [];
        foreach (scandir($folder, true) as $node) {
            if (strpos($node, '.') === 0) {
                continue;
            }
            if (is_dir($folder . DIRECTORY_SEPARATOR . $node)) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $result = array_merge($result, self::getAllFilesInFolder($folder . DIRECTORY_SEPARATOR . $node, $fileEnding));
            } else if ($fileEnding) {
                if (strpos($node, $fileEnding) === (strlen($node) - strlen($fileEnding))) {
                    $result[] = $folder . DIRECTORY_SEPARATOR . $node;
                }
            } else {
                $result[] = $folder . DIRECTORY_SEPARATOR . $node;
            }
        }
        return $result;
    }
}
