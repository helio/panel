<?php

namespace Helio\Panel\Utility;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Middleware\CliAuthenticate;

/**
 * Class MiddlewareUtility.
 */
class MiddlewareForCliUtility extends AbstractUtility
{
    /**
     * @param App $app
     *
     * NOTE: Middlewares are processed as a FILO stack, so beware their order
     *
     * @throws Exception
     */
    public static function addMiddleware(App $app): void
    {
        $app->add(new CliAuthenticate());
    }
}
