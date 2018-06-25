<?php

namespace Helio\Test\Functional;

use Helio\Panel\App;
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
     * Use middleware when running application?
     *
     * @var bool
     */
    protected $withMiddleware = true;


    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param mixed $requestData the request data
     * @return ResponseInterface
     *
     * @throws
     */
    public function runApp($requestMethod, $requestUri, $requestData = null): ResponseInterface
    {

        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri
            ]
        );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if ($requestData !== null) {
            $request = $request->withParsedBody($requestData);
        }

        $response = App::process('test', $request);

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