<?php

namespace Helio\Panel\Utility;

class ServerUtility
{


    private static $systemCommand = 'ssh panel@35.198.151.151 "autosign generate -b %s"';


    /**
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {

        $protocol = 'http';

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && stripos('off', $_SERVER['HTTPS']) !== 0) {
            $protocol .= 's';
        }

        return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/';
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
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    }


    /**
     * @param string $fqdn
     * @param bool $returnInsteadOfCall
     *
     * @return string
     */
    public static function submitAutosign(string $fqdn, bool $returnInsteadOfCall = false): string
    {
        $match = preg_match('/[^0-9a-zA-Z\.\-_]/', $fqdn);
        if ($match !== 0) {
            throw new \InvalidArgumentException('invalid fqdn submitted', 1531076419);
        }

        $command = sprintf(self::$systemCommand, $fqdn);

        return $returnInsteadOfCall ? $command : system($command);
    }
}