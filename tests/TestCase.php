<?php

namespace Helio\Test;

use Doctrine\DBAL\Types\Type;
use Helio\Panel\Helper\SQLLogger;
use Helio\Panel\Model\Filter\DeletedFilter;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\QueryFunction\TimestampDiff;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Type\PreferencesType;
use Helio\Panel\Model\Type\UTCDateTimeType;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\MiddlewareForHttpUtility;
use Helio\Test\Infrastructure\App;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\LogHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\Infrastructure\Orchestrator\OrchestratorFactory;
use Helio\Test\Infrastructure\Utility\NotificationUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Environment;
use Doctrine\Common\Persistence\ObjectRepository;
use Slim\Http\RequestBody;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Class TestCase serves as root for all cases.
 *
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

    /**
     * @var ObjectRepository
     */
    protected $managerRepository;

    /**
     * @return bool
     */
    private static function isVeryVerboseSet(): bool
    {
        return in_array('-vv', $_SERVER['argv'], true);
    }

    /**
     * @return bool
     */
    private static function isVerboseSet(): bool
    {
        return in_array('-v', $_SERVER['argv'], true);
    }

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
        $_SERVER['KOALA_FARM_ORIGIN'] = 'http://localhost:3000';
        $_SERVER['BLENDER_DOCKER_IMAGE'] = 'blender';
        $_SERVER['BLENDER_DOCKER_REGISTRY_SERVER'] = 'registry.org';
        $_SERVER['BLENDER_DOCKER_REGISTRY_USERNAME'] = 'user';
        $_SERVER['BLENDER_DOCKER_REGISTRY_PASSWORD'] = 'password';
        $_SERVER['BLENDER_DOCKER_REGISTRY_EMAIL'] = 'email@example.com';
        $_SERVER['BLENDER_STORAGE_BUCKET_NAME'] = 'bucket';
        $_SERVER['BLENDER_STORAGE_CREDENTIALS_JSON_PATH'] = __DIR__ . '/Infrastructure/dummy.json';

        // re-init Zapier helper to make sure no Responses are left in the stack etc.
        ZapierHelper::reset();

        // re-init DBHelper
        DbHelper::reset();
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);
        if (!Type::hasType(PreferencesType::TypeName)) {
            Type::addType(PreferencesType::TypeName, PreferencesType::class);
        }

        $this->infrastructure = ORMInfrastructure::createWithDependenciesFor([User::class, Instance::class, Job::class, Execution::class, Manager::class]);

        $configuration = $this->infrastructure->getEntityManager()->getConfiguration();
        $configuration->addFilter('deleted', DeletedFilter::class);
        $configuration->addCustomNumericFunction('timestampdiff', TimestampDiff::class);

        if (self::isVeryVerboseSet()) {
            $configuration->setSQLLogger(new SQLLogger());
        }

        DbHelper::setInfrastructure($this->infrastructure);

        $this->userRepository = $this->infrastructure->getRepository(User::class);
        $this->instanceRepository = $this->infrastructure->getRepository(Instance::class);
        $this->jobRepository = $this->infrastructure->getRepository(Job::class);
        $this->executionRepository = $this->infrastructure->getRepository(Execution::class);
        $this->managerRepository = $this->infrastructure->getRepository(Manager::class);

        ServerUtility::resetLastExecutedCommand();
        OrchestratorFactory::resetInstances();
    }

    public static function setUpBeforeClass(): void
    {
        if (!\defined('APPLICATION_ROOT')) {
            \define('APPLICATION_ROOT', \dirname(__DIR__));
            \define('LOG_DEST', 'php://stdout');
            $logLevel = Logger::EMERGENCY;
            if (self::isVeryVerboseSet()) {
                $logLevel = Logger::DEBUG;
            } elseif (self::isVerboseSet()) {
                $logLevel = Logger::WARNING;
            }
            \define('LOG_LVL', $logLevel);
        }

        // make sure no shell commands are being executed.
        ServerUtility::setTesting();
    }

    public function tearDown(): void
    {
        // N.B. when you remove jobs/users, you need to reset the instances of orchestrator singleton cache
        // otherwise following tests create new jobs with the same ID, instance cache thinks it knows them still,
        // but the object is actually gone already.
        // Singletons are evil.
        OrchestratorFactory::resetInstances();

        // Reset sent mails
        NotificationUtility::$mails = [];
    }

    /**
     * @param $dir
     */
    public static function tearDownAfterClass($dir = APPLICATION_ROOT . '/tmp/cache/test'): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir, 0), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file") ? self::tearDownAfterClass("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }

    /**
     * Process the application given a request method and URI.
     *
     * @param string                         $requestMethod  the request method (e.g. GET, POST, etc.)
     * @param string                         $requestUri     the request URI
     * @param bool                           $withMiddleware whether the app should include the middlewares (mainly JWT)
     * @param null                           $headerData
     * @param mixed                          $requestData    the request data
     * @param array                          $cookieData     the cookies
     * @param array                          $attributes
     * @param bool|\Helio\Panel\App|App|null $app            if set, this variable will contain the app for further analysis of
     *                                                       results and processings
     *                                                       (memory heavy!)
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    protected function runWebApp(
        $requestMethod,
        $requestUri,
        $withMiddleware = false,
        $headerData = null,
        $requestData = null,
        $cookieData = null,
        array $attributes = [],
        &$app = null
    ): ResponseInterface {
        $requestUri = preg_replace(';^https?://localhost(:[0-9]+)?/;', '/', $requestUri);

        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri,
                'QUERY_STRING' => $requestParts[1] ?? '',
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
        if (null !== $requestData) {
            $request = $request->withParsedBody($requestData);

            $body = new RequestBody();
            $body->write(json_encode($requestData));
            $request = $request->withBody($body);
        }

        if ($attributes) {
            $request = $request->withAttributes($attributes);
        }

        $middlewares = $withMiddleware ? [MiddlewareForHttpUtility::class] : [];
        $app = App::getTestApp(true, $middlewares);

        $app->getContainer()['request'] = $request;

        $res = $app->run(true);

        if (500 === $res->getStatusCode()) {
            LogHelper::warn('internal server error', ['body' => $res->getBody()->getContents()]);
        }

        return $res;
    }

    /**
     * @param $command
     * @param  array         $parameters
     * @param  bool          $withMiddleware
     * @param  null          $app
     * @return CommandTester
     */
    protected function runCliApp(
        string $command,
        array $parameters = [],
        bool $withMiddleware = true,
        &$app = null
    ) {
        /* @var Command $commandInstance */
        if (false === $withMiddleware) {
            $commandInstance = new $command(App::class, []);
        } else {
            $commandInstance = new $command(App::class);
        }

        $app = new Application();
        $app->add($commandInstance);

        $command = $app->find($commandInstance->getName());
        $testresult = new CommandTester($command);
        $testresult->execute($parameters);

        return $testresult;
    }
}
