<?php

namespace Helio\Panel\Utility;

class ServerUtility
{

    /**
     * THE ONLY place where a timezone is mentioned. This shall be read from database (in each entity) or ENV in the future.
     *
     * @var string
     */
    protected static $timeZone = 'Europe/Berlin';


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
     * @return \DateTimeZone
     */
    public static function getTimezoneObject(): \DateTimeZone
    {
        return new \DateTimeZone(self::$timeZone);
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
     * @param string $name
     * @param mixed|null $default
     *
     * @return string
     */
    public static function get(string $name, $default = null): string
    {

        if (!array_key_exists($name, $_SERVER) || !$_SERVER[$name]) {
            if ($default) {
                return $default;
            }
            throw new \RuntimeException('please set the ENV Variable ' . $name, 1530357047);
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
    public static function isBehindReverseProxy(): bool
    {
        return self::get('REMOTE_ADDR') === self::get('REVERSE_PROXY_IP', 'impossible') && self::get('HTTP_X_FORWARDED_FOR', false);
    }


    /**
     * @param string $command
     * @param bool $returnInsteadOfCall
     *
     * @return null|string
     */
    public static function executeShellCommand(string $command, bool $returnInsteadOfCall = false): ?string
    {
        return $returnInsteadOfCall ? $command : trim(@shell_exec($command));
    }


    /**
     * @param array $params
     */
    public static function validateParams(array $params): void
    {
        foreach ($params as $item) {
            $res = preg_match('/[^0-9a-zA-Z\.\-_"]/', $item);
            if ($res === false || $res > 0) {
                throw new \InvalidArgumentException('suspicious shell command submitted', 1544664506);
            }
        }
    }
}
