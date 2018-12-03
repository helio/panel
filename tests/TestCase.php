<?php

namespace Helio\Test;

use Helio\Test\Infrastructure\App;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Environment;
use Doctrine\Common\Persistence\ObjectRepository;
use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Class TestCase serves as root for all cases
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class TestCase extends \PHPUnit_Framework_TestCase
{


    /**
     * @var ORMInfrastructure
     */
    protected $infrastructure;


    /**
     * @var ObjectRepository
     */
    protected $userRepository;


    /**
     * @var ObjectRepository
     */
    protected $serverRepository;


    /** @see \PHPUnit_Framework_TestCase::setUp() */
    protected function setUp()
    {
        $this->infrastructure = ORMInfrastructure::createWithDependenciesFor([User::class, Server::class]);
        $this->userRepository = $this->infrastructure->getRepository(User::class);
        $this->serverRepository = $this->infrastructure->getRepository(Server::class);
    }


    /**
     *
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!\defined('APPLICATION_ROOT')) {
            \define('APPLICATION_ROOT', __DIR__ . '/..');
            \define('SITE_ENV', 'TEST');
            \define('LOG_DEST', APPLICATION_ROOT . '/log/app-test.log');
            \define('LOG_LVL', 100);
        }
        $_SERVER['JWT_SECRET'] = 'ladida';
        $_SERVER['ZAPIER_HOOK_URL'] = '/blah';
        $_SERVER['SCRIPT_HASH'] = 'TESTSHA1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SITE_ENV'] = 'TEST';
    }


    /**
     * @param $dir
     *
     */
    public static function tearDownAfterClass($dir = APPLICATION_ROOT . '/tmp/cache/test'): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir, 0), array ('.', '..'));
        foreach ($files as $file) {
            is_dir("$dir/$file") ? self::tearDownAfterClass("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }


    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param bool $withMiddleware whether the app should include the middlewares (mainly JWT).
     * @param mixed $cookieData the request data
     * @param mixed $requestData the request data
     * @param array $attributes
     * @param bool|\Helio\Panel\App|App|null $app if set, this variable will contain the app for further analysis of
     *     results and processings
     *     (memory heavy!)
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    protected function runApp(
        $requestMethod,
        $requestUri,
        $withMiddleware = false,
        $cookieData = null,
        $requestData = null,
        $attributes = [],
        &$app = null
    ): ResponseInterface {

        $requestParts = explode('?', $requestUri);
        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri,
                'QUERY_STRING' => $requestParts[1] ?? ''
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

        if ($attributes) {
            $request = $request->withAttributes($attributes);
        }

        if ($withMiddleware) {
            $app = App::getApp('app', DbHelper::class, ZapierHelper::class);
        } else {
            $app = App::getTestApp();
        }
        $app->getContainer()['request'] = $request;

        return $app->run(true);
    }
}