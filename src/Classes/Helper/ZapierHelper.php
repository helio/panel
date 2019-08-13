<?php

namespace Helio\Panel\Helper;

use GuzzleHttp\Exception\GuzzleException;
use Exception;
use GuzzleHttp\Client;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;

class ZapierHelper implements HelperInterface
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
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitUserToZapier(User $user): bool
    {
        $publicUserObject = json_encode([
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ]);

        $result = $this->getClient()->request(
            'POST',
            $this->getZapierHookUrl(),
            ['body' => $publicUserObject]
        );

        return 200 === $result->getStatusCode();
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
        return (bool) $this->getBaseUrl();
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    protected function getZapierHookUrl(): string
    {
        return ServerUtility::get('ZAPIER_HOOK_URL');
    }

    /**
     * @return $this
     */
    public static function getInstance(): self
    {
        $class = static::class;
        if (!self::$instances || !array_key_exists($class, self::$instances)) {
            // new $class() will work too
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
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
            if (isset($_SERVER['http_proxy']) && 0 === strpos($this->getBaseUrl(), 'http:')) {
                $config['proxy'] = $_SERVER['http_proxy'];
            }
            if (isset($_SERVER['https_proxy']) && 0 === strpos($this->getBaseUrl(), 'https:')) {
                $config['proxy'] = $_SERVER['https_proxy'];
            }

            $this->client = new Client($config);
        }

        return $this->client;
    }
}
