<?php

namespace Helio\Panel\Helper;

use GuzzleHttp\Client;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;


class ZapierHelper
{


    /**
     * @var array>ZapierHelper>
     */
    protected static $instances;


    /**
     * @var Client
     */
    protected $client;


    /**
     * @param User $user
     *
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function submitUserToZapier(User $user): bool
    {
        $publicUserObject = json_encode([
            'name' => $user->getName(),
            'email' => $user->getEmail()
        ]);

        $result = $this->getClient()->request('POST', $this->getZapierHookUrl(),
            ['body' => $publicUserObject]);

        return ($result->getStatusCode() === 200);
    }


    /**
     * @return mixed
     */
    protected function getHandler()
    {
        return null;
    }


    /**
     * @return string
     */
    protected function getBaseUrl(): string
    {
        return 'https://hooks.zapier.com/';
    }


    /**
     * @return mixed
     */
    protected function hasHandler()
    {
        return false;
    }


    /**
     * @return string
     */
    protected function hasBaseUrl(): string
    {
        return (bool)$this->getBaseUrl();
    }


    /**
     * @return string
     * @throws \Exception
     */
    protected function getZapierHookUrl(): string
    {
        return ServerUtility::get('ZAPIER_HOOK_URL');
    }


    /**
     *
     * @return $this
     */
    public static function getInstance(): self
    {
        $class = static::class;
        if (!self::$instances || !\array_key_exists($class, self::$instances)) {
            // new $class() will work too
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }


    /**
     *
     * @return Client
     */
    protected function getClient(): Client
    {
        if (!$this->client) {
            $config = [];
            if ($this->hasBaseUrl()) {
                $config['base_uri'] = $this->getBaseUrl();
            }
            if ($this->hasHandler()) {
                $config['handler'] = $this->getHandler();
            }
            if (isset($_SERVER['http_proxy']) && strpos($this->getBaseUrl(), 'http:') === 0) {
                $config['proxy'] = $_SERVER['http_proxy'];
            }
            if (isset($_SERVER['https_proxy']) && strpos($this->getBaseUrl(), 'https:') === 0) {
                $config['proxy'] = $_SERVER['https_proxy'];
            }


            $this->client = new Client($config);
        }

        return $this->client;
    }
}