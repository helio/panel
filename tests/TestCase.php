<?php

namespace Helio\Test;

use Doctrine\DBAL\Types\Type;
use Helio\Panel\Model\Filter\DeletedFilter;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\QueryFunction\TimestampDiff;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Type\UTCDateTimeType;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\MiddlewareUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\Infrastructure\App;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Environment;
use Doctrine\Common\Persistence\ObjectRepository;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Class TestCase serves as root for all cases
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class TestCase extends \PHPUnit\Framework\TestCase
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
    protected $instanceRepository;


    /**
     * @var ObjectRepository
     */
    protected $jobRepository;


    /**
     * @var ObjectRepository
     */
    protected $executionRepository;


    /** @throws \Exception
     * @see \PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp(): void
    {
        $_SERVER['JWT_SECRET'] = 'ladida';
        $_SERVER['ZAPIER_HOOK_URL'] = '/blah';
        $_SERVER['SCRIPT_HASH'] = 'TESTSHA1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SITE_ENV'] = 'TEST';
        $_SERVER['ELASTIC_HOST'] = 'elastic.neverland.global';


        // re-init Zapier helper to make sure no Responses are left in the stack etc.
        ZapierHelper::reset();


        // re-init DBHelper
        DbHelper::reset();
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);


        $this->infrastructure = ORMInfrastructure::createWithDependenciesFor([User::class, Instance::class, Job::class, Execution::class]);
        $this->infrastructure->getEntityManager()->getConfiguration()->addFilter('deleted', DeletedFilter::class);
        $this->infrastructure->getEntityManager()->getConfiguration()->addCustomNumericFunction('timestampdiff', TimestampDiff::class);

        DbHelper::setInfrastructure($this->infrastructure);


        $this->userRepository = $this->infrastructure->getRepository(User::class);
        $this->instanceRepository = $this->infrastructure->getRepository(Instance::class);
        $this->jobRepository = $this->infrastructure->getRepository(Job::class);
        $this->executionRepository = $this->infrastructure->getRepository(Execution::class);
    }

    /**
     *
     */
    public static function setUpBeforeClass(): void
    {
        if (!\defined('APPLICATION_ROOT')) {
            \define('APPLICATION_ROOT', \dirname(__DIR__));
            \define('LOG_DEST', 'php://stdout');
            \define('LOG_LVL', Logger::WARNING);
        }

        // make sure no shell commands are being executed.
        ServerUtility::setTesting();
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
        $files = array_diff(scandir($dir, 0), array('.', '..'));
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
     * @param null $headerData
     * @param mixed $requestData the request data
     * @param array $cookieData the cookies
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
        $requestMethod, $requestUri, $withMiddleware = false, $headerData = null, $requestData = null, $cookieData = null, array $attributes = [], &$app = null
    ): ResponseInterface
    {
        $requestUri = preg_replace(';^https?://localhost(:[0-9]+)?/;', '/', $requestUri);

        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri,
                'QUERY_STRING' => $requestParts[1] ?? ''
            ]
        );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        if ($cookieData) {
            if (is_string($cookieData)) {
                $cookieString = $cookieData;
                $cookieData = [];
                list($key, $value) = explode('=', explode(';', $cookieString)[0]);
                $cookieData[$key] = $value;
            }
            $request = $request->withCookieParams($cookieData);
        }

        if ($headerData) {
            foreach ($headerData as $headerKey => $headerValue) {
                $request = $request->withHeader($headerKey, $headerValue);
            }
        }

        // Add request data, if it exists
        if ($requestData !== null) {
            $request = $request->withParsedBody($requestData);
        }

        if ($attributes) {
            $request = $request->withAttributes($attributes);
        }

        $middlewares = $withMiddleware ? [MiddlewareUtility::class] : [];
        $app = App::getTestApp(true, $middlewares);

        $app->getContainer()['request'] = $request;

        return $app->run(true);
    }
}