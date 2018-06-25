<?php
namespace Helio\Panel\Helper;

use GuzzleHttp\Handler\MockHandler;
use Helio\ZapierWrapper\ZapierFactory;
use Helio\ZapierWrapper\Zapier;

class ZapierHelper {


    /**
     * @param MockHandler|null $handler
     *
     * @return Zapier
     */
    public static function get(MockHandler $handler = null): Zapier {
        if ($handler) {
            return ZapierFactory::getFactory()->getZapier('', $handler);
        }
        return ZapierFactory::getFactory()->getZapier('https://hooks.zapier.com/');
    }
}