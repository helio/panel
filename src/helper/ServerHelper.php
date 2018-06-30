<?php

namespace Helio\Panel\Helper;

class ServerHelper
{


    /**
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {

        $protocol = 'http';

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off') {
            $protocol .= 's';
        }

        return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/';
    }
}