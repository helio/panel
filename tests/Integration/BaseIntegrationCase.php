<?php

namespace Helio\Test\Integration;

use Helio\Test\App;
use Helio\Test\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Environment;
use Slim\Http\Request;


/**
 * Class BaseAppCase
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class BaseIntegrationCase extends TestCase
{


    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param bool $withMiddleware whether the app should include the middlewares (mainly JWT).
     * @param mixed $cookieData the request data
     * @param mixed $requestData the request data
     * @param bool|\Helio\Panel\App|App|null $app if set, this variable will contain the app for further analysis of results and
     *     processings
     *     (memory heavy!)
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    public function runApp(
        $requestMethod,
        $requestUri,
        $withMiddleware = false,
        $cookieData = null,
        $requestData = null,
        &$app = false
    ): ResponseInterface {

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
            $app = App::getApp();
        } else {
            $app = App::getTestApp();
        }
        $app->getContainer()['request'] = $request;
        $response = $app->run(true);

        // reset cache after each test
        self::delTree(APPLICATION_ROOT . '/tmp/cache/test/');

        return $response;
    }
}