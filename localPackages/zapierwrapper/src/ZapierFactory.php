<?php

namespace Helio\ZapierWrapper;

use GuzzleHttp\Handler\MockHandler;

class ZapierFactory
{


    /**
     * @var ZapierFactory
     */
    protected static $factory;


    /**
     * @var Zapier
     */
    protected $zapier;


    /**
     *
     * @return ZapierFactory
     */
    public static function getFactory(): ZapierFactory
    {
        if (!self::$factory) {
            self::$factory = new self();
        }

        return self::$factory;
    }


    /**
     * @param string $baseUrl
     * @param MockHandler $handler
     *
     * @return Zapier
     */
    public function getZapier(string $baseUrl = '', MockHandler $handler = null): Zapier
    {
        if (!$this->zapier) {
            $config = [];
            if ($baseUrl) {
                $config['base_uri'] = $baseUrl;
            }
            if ($handler) {
                $config['handler'] = $handler;
            }
            $this->zapier = new Zapier($config);
        }

        return $this->zapier;
    }
}