<?php

namespace Helio\Panel\Helper;

use GuzzleHttp\Client;
use Helio\Panel\Utility\ServerUtility;
use GuzzleHttp\Exception\GuzzleException;

class SlackHelper implements HelperInterface
{
    /**
     * @var array<SlackHelper>
     */
    protected static $instances;

    /** @var Client */
    protected $client;

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
     * SlackHelper constructor.
     */
    public function __construct()
    {
        // Create an HTTP Client using Guzzle
        $this->client = new Client([
            'base_uri' => 'https://hooks.slack.com/services/',
        ]);
    }

    /**
     * @param string $message
     *
     * @return bool
     * @throws GuzzleException
     */
    public function sendNotification(string $message): bool
    {
        if (!ServerUtility::get('SLACK_WEBHOOK', '')) {
            return false;
        }

        return 200 === $this->client->request('POST', ServerUtility::get('SLACK_WEBHOOK'), ['body' => '{"text":"' . $message . '"}'])->getStatusCode();
    }

    /**
     * @param string $message
     *
     * @return bool
     * @throws GuzzleException
     */
    public function sendAlert(string $message): bool
    {
        if (!ServerUtility::get('SLACK_WEBHOOK_ALERT', '')) {
            return false;
        }

        return 200 === $this->client->request('POST', ServerUtility::get('SLACK_WEBHOOK_ALERT'), ['body' => '{"text":"' . $message . '"}'])->getStatusCode();
    }
}
