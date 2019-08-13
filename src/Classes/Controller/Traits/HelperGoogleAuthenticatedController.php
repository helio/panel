<?php

namespace Helio\Panel\Controller\Traits;

use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\Helper\GoogleIapHelper;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;

trait HelperGoogleAuthenticatedController
{
    /** @var string $baseUrl */
    protected $baseUrl;

    /**
     * @param string $path
     * @param string $method
     * @param array  $options
     *
     * @return mixed|ResponseInterface
     *
     * @throws GuzzleException
     */
    protected function requestIapProtectedResource(string $path, string $method = 'GET', array $options = [])
    {
        return GoogleIapHelper::getInstance()->make_iap_request(
            $this->baseUrl,
            $path,
            ServerUtility::get('GOOGLE_AUTH_USER_ID'),
            ServerUtility::get('GOOGLE_AUTH_JSON_PATH'),
            $method,
            $options
        );
    }
}
