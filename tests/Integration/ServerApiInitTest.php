<?php

namespace Helio\Test\Integration;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class ServerApiInitTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var string
     */
    protected $data;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())->setName('testuser')->setCreated()->setEmail('test@example.com');

        $this->data = ['fqdn' => 'testserver.example.com', 'email' => $this->user->getEmail()];

        ZapierHelper::setResponseStack([
            new Response(200, [], '{"success" => "true"}'),
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    protected function exec(): ResponseInterface
    {
        return $this->runApp('POST', '/server/init', true, null, $this->data);
    }

    /**
     * @throws \Exception
     */
    public function testUserInactive(): void
    {
        $this->infrastructure->import($this->user);
        $this->assertEquals(406, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testActiveUserDoesntTriggerZapier(): void
    {
        ZapierHelper::setResponseStack([
            new Response(400, [], '{"success" => "false"}'),
        ]);

        $this->user->setActive(true);
        $this->infrastructure->import($this->user);

        $this->assertEquals(416, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testOnZapierFailure(): void
    {
        ZapierHelper::setResponseStack([
            new Response(400, [], '{"success" => "false"}'),
        ]);

        $this->assertEquals(406, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testUserAlreadyExists(): void
    {
        $this->user->setActive(true);
        $this->infrastructure->import($this->user);

        $response = $this->exec();

        $this->assertEquals(416, $response->getStatusCode());

        $instance = $this->instanceRepository->findOneByFqdn('testserver.example.com');
        $this->assertNotNull($instance);
        $this->assertInstanceOf(Instance::class, $instance);
    }

    /**
     * @throws \Exception
     */
    public function testServerInit(): void
    {
        $response = $this->exec();
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('server_id', $body);
        $this->assertStringContainsString('user_id', $body);

        $json = json_decode($body, true);
        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByFqdn('testserver.example.com');
        $this->assertEquals($instance->getId(), $json['server_id']);
        $this->assertEquals($instance->getOwner()->getId(), $json['user_id']);
    }
}
