<?php

namespace Helio\Panel\Helper;

use Google\Auth\OAuth2;
use Google\Auth\Middleware\ScopedAccessTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class GoogleIapHelper
{


    /**
     * @param $url
     * @param $clientId
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function make_iap_request($url, $clientId = '1022009500119-gagi1ktmi136r2kc12k8tusv9jvhdnep.apps.googleusercontent.com')
    {
        $oauth_token_uri = 'https://www.googleapis.com/oauth2/v4/token';
        $iam_scope = 'https://www.googleapis.com/auth/iam';

        # Create an OAuth object using the service account key
        $oauth = new OAuth2([
            'audience' => $oauth_token_uri,
            'issuer' => 'kevin@helio.exchange',
            'signingAlgorithm' => 'RS256',
            'signingKey' => 'TJRn_MpypGrSAe9nC3tWBNrj',
            'tokenCredentialUri' => $oauth_token_uri,
        ]);
        $oauth->setGrantType(OAuth2::JWT_URN);
        $oauth->setAdditionalClaims(['target_audience' => $clientId]);

        # Obtain an OpenID Connect token, which is a JWT signed by Google.
        $token = $oauth->fetchAuthToken();
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
        return $http_client->request('GET', '/', []);
    }
}