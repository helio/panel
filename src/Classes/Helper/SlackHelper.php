<?php

namespace Helio\Panel\Helper;

use GuzzleHttp\Client;
use Helio\Panel\Utility\ServerUtility;

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

    public function sendNotification(string $message): bool
    {
        return $this->sendMessage('SLACK_WEBHOOK', $message);
    }

    public function sendAlert(string $message): bool
    {
        return $this->sendMessage('SLACK_WEBHOOK_ALERT', $message);
    }

    public function sendKoalaFarmNotification(string $message): bool
    {
        return $this->sendMessage('SLACK_WEBHOOK_KOALA_FARM', $message);
    }

    public function sendCheetahNotification(string $message): bool
    {
        return $this->sendMessage('SLACK_WEBHOOK_CHEETAH', $message);
    }

    private function sendMessage(string $webhookEnvVariable, string $text): bool
    {
        $webhook = ServerUtility::get($webhookEnvVariable, '');
        if (!$webhook) {
            return false;
        }

        return 200 === $this->client->request('POST', $webhook, ['json' => ['text' => $text]])->getStatusCode();
    }
}
