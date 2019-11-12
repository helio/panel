<?php

namespace Helio\Test\Integration;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;

class ApiInstanceTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testLoadUserFromJwtMiddleware(): void
    {
        $user = new User();
        $this->infrastructure->import($user);

        $response = $this->runWebApp('POST', '/api/instance', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('token', $body);
    }

    /**
     * @throws \Exception
     */
    public function testCleanupCallback(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $instance = (new Instance())
            ->setOwner($user);

        $this->infrastructure->import($instance);

        $response = $this->runWebApp('POST', '/api/instance/callback?instanceid=' . $instance->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']], [
            'action' => 'cleanup',
            'success' => true,
            'nodes' => 'foo.fqdn.example.org',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        /** @var Instance $instance */
        $instance = $this->infrastructure->getRepository(Instance::class)->find($instance->getId());
        $this->assertTrue($instance->isHidden());
    }
}
