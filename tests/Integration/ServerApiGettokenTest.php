<?php

namespace Helio\Test\Integration;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class ServerApiGettokenTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var Instance
     */
    protected $instance;

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

        $this->instance = (new Instance())->setName('testinstance')->setCreated()->setFqdn('testserver.example.com')->setStatus(InstanceStatus::CREATED);
        $this->user = (new User())->setName('testuser')->setCreated()->setEmail('test@example.com')->setActive(true);

        $this->data = ['fqdn' => $this->instance->getFqdn(), 'email' => $this->user->getEmail()];

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
        return $this->runWebApp('POST', '/server/gettoken', true, null, $this->data);
    }

    /**
     * @throws \Exception
     */
    public function testUserDoesntExist(): void
    {
        $this->assertEquals(403, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testInstanceDoesntExist(): void
    {
        $this->infrastructure->import($this->user);
        $this->assertEquals(200, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testBothExistButWrongOwner(): void
    {
        $newUser = (new User())->setCreated()->setActive(true)->setName('wrong user');
        $this->instance->setOwner($newUser);

        $this->infrastructure->import($this->user);
        $this->infrastructure->import($newUser);
        $this->infrastructure->import($this->instance);

        $this->assertEquals(404, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testServerHasNoOwner(): void
    {
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);

        $this->assertEquals(404, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testEverythingOk(): void
    {
        $this->instance->setOwner($this->user)->setIp('1.2.3.4');
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        $response = $this->exec();
        $this->assertEquals(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $json = json_decode($body, true);
        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByFqdn('testserver.example.com');
        $this->assertNotNull($instance);
        $this->assertStringContainsString('token', $body);
    }
}
