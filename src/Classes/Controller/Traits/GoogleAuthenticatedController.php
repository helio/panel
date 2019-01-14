<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Helio\Panel\Helper\GoogleIapHelper;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;

trait GoogleAuthenticatedController
{
    /** @var GoogleIapHelper $auth */
    protected $auth;

    /** @var string $baseUrl */
    protected $baseUrl;

    /**
     * @return bool
     */
    public function setupGoogleAuthentication(): bool
    {
        $this->auth = new GoogleIapHelper();
        return true;
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $options
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function requestIapProtectedResource(string $path, string $method = 'GET', array $options = [])
    {
        return $this->auth->make_iap_request(
            $this->baseUrl,
            $path,
            ServerUtility::get('GOOGLE_AUTH_USER_ID', '1022009500119-cq4u988crl055fbamtrmvenofq0ofiv5.apps.googleusercontent.com'),
            ServerUtility::get('GOOGLE_AUTH_JSON_PATH', APPLICATION_ROOT . '/cnf/googleauth.json'),
            $method,
            $options
        );
    }
}