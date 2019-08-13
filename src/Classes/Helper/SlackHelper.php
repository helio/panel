<?php

namespace Helio\Panel\Helper;

use Exception;
use GuzzleHttp\Client;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;

class SlackHelper implements HelperInterface
{
    /**
     * @var array<SlackHelper>
     */
    protected static $instances;

    /**
     * @return $this
     */
    public static function getInstance(): self
    {
        $class = static::class;
        if (!self::$instances || !array_key_exists($class, self::$instances)) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * @param string $message
     *
     * @return mixed|ResponseInterface
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendNotification(string $message)
    {
        // Create an HTTP Client using Guzzle
        $http_client = new Client([
            'base_uri' => 'https://hooks.slack.com/services/',
        ]);

        // Make an authenticated HTTP Request
        return 200 === $http_client->request('POST', ServerUtility::get('SLACK_WEBHOOK'), ['body' => '{"text":"' . $message . '"}'])->getStatusCode();
    }
}
