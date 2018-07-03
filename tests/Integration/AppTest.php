<?php

namespace Helio\Test\Integration;

use Helio\Panel\App;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Functional\BaseIntegrationCase;
use Helio\Test\Functional\Fixture\Model\User;

class AppTest extends BaseIntegrationCase
{


    /**
     *
     * @throws \Exception
     */
    public function skipped_testLoadUserFromJwtMiddleware(): void
    {

        $user = new User();
        $user->setId(6446);
        $app = true;

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];

        /** @var App $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, $app);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->getId(), $app->getContainer()->get('user')->getId());

    }
}