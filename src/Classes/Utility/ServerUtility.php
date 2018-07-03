<?php

namespace Helio\Panel\Utility;

class ServerUtility
{


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
}