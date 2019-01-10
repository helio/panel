<?php

namespace Helio\Panel\Helper;

# Imports Auth libraries and Guzzle HTTP libraries.
use Google\Auth\OAuth2;
use Google\Auth\Middleware\ScopedAccessTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class GoogleIapHelper
{
    /**
     * @param $url
     * @param $path
     * @param $clientId
     * @param $pathToServiceAccount
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function make_iap_request($url, $path, $clientId, $pathToServiceAccount)
    {
        $serviceAccountKey = json_decode(file_get_contents($pathToServiceAccount), true);
        $oauth_token_uri = 'https://www.googleapis.com/oauth2/v4/token';
        $iam_scope = 'https://www.googleapis.com/auth/iam';

        # Create an OAuth object using the service account key
        $oauth = new OAuth2([
            'audience' => $oauth_token_uri,
            'issuer' => $serviceAccountKey['client_email'],
            'signingAlgorithm' => 'RS256',
            'signingKey' => $serviceAccountKey['private_key'],
            'tokenCredentialUri' => $oauth_token_uri,
        ]);
        $oauth->setGrantType(OAuth2::JWT_URN);
        $oauth->setAdditionalClaims(['target_audience' => $clientId]);

        # Obtain an OpenID Connect token, which is a JWT signed by Google.
        $oauth->fetchAuthToken();
        $idToken = $oauth->getIdToken();

        # Construct a ScopedAccessTokenMiddleware with the ID token.
        $middleware = new ScopedAccessTokenMiddleware(
            function () use ($idToken) {
                return $idToken;
            },
            $iam_scope
        );

        $stack = HandlerStack::create();
        $stack->push($middleware);

        # Create an HTTP Client using Guzzle and pass in the credentials.
        $http_client = new Client([
            'handler' => $stack,
            'base_uri' => $url,
            'auth' => 'scoped'
        ]);

        # Make an authenticated HTTP Request
        return $http_client->request('GET', $path, []);
    }
}