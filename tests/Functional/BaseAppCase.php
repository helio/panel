<?php

namespace Helio\Test\Functional;

use Helio\SlimWrapper\Slim;
use Helio\SlimWrapper\SlimFactory;
use Helio\Test\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Environment;


/**
 * Class BaseAppCase
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class BaseAppCase extends TestCase
{


    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param bool $withMiddleware whether the app should include the middlewares (mainly JWT).
     * @param mixed $cookieData the request data
     * @param mixed $requestData the request data
     * @param bool|Slim $app if set, this variable will contain the app for further analysis of results and processings (memory heavy!)
     *
     * @return ResponseInterface
     *
     * @throws
     */
    public function runApp($requestMethod, $requestUri, $withMiddleware = false, $cookieData = null, $requestData = null, &$app = null): ResponseInterface
    {

        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri
            ]
        );

        if ($cookieData) {
            $environment->set('HTTP_Cookie', $cookieData);
        }

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if ($requestData !== null) {
            $request = $request->withParsedBody($requestData);
        }

        if ($withMiddleware) {
            $response = SlimFactory::getFactory()->getApp()->process($request);
        } else {
            $response = SlimFactory::getFactory()->getAppWithoutMiddleware()->process($request);
        }

        if ($app) {
            $app = SlimFactory::getFactory()->getApp();
        }
        self::delTree(APPLICATION_ROOT . '/tmp/cache/test/');

        return $response;
    }


    /**
     * @param $dir
     *
     * @return bool
     */
    protected static function delTree($dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        $files = array_diff(scandir($dir, 0), array ('.', '..'));
        foreach ($files as $file) {
            is_dir("$dir/$file") ? self::delTree("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }
}