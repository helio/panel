<?php

namespace Helio\Panel\Helper;

use GuzzleHttp\Client;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;


class ZapierHelper
{


    /**
     * @var ZapierHelper
     */
    private static $helper;


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
            'email' => 'we don\'t want zapier adding users all the time'
            // TODO: submit proper email to zapier - $user->getEmail()
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


    protected function getZapierHookUrl(): string
    {
        return ServerUtility::get('ZAPIER_HOOK_URL');
    }

    /**
     *
     * @return ZapierHelper
     */
    public static function getInstance(): ZapierHelper
    {
        if (!self::$helper) {
            self::$helper = new self();
        }

        return self::$helper;
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
            $this->client = new Client($config);
        }

        return $this->client;
    }
}