<?php

namespace Helio\Test\Integration;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ServerApiRegisterTest
 * @package Helio\Test\Integration
 *
 * TODO: Add some tests
 */
class ServerApiRegisterTest extends TestCase
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
     * @var array
     */
    protected $data;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())->setName('testuser')->setCreated()->setEmail('test@example.com')->setActive(true);
        $this->instance = (new Instance())
            ->setId(1) // note: this is not super nice but we need the ID for token generation AND the testing framework's persist() desn't work properly atm
            ->setName('testinstance')
            ->setCreated()
            ->setFqdn('testserver.example.com')
            ->setOwner($this->user)
            ->setStatus(InstanceStatus::CREATED);
        $this->instance->setToken(JwtUtility::generateInstanceIdentificationToken($this->instance));

        $this->data = ['token' => $this->instance->getToken(), 'email' => $this->user->getEmail()];
    }

    /**
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function exec(): ResponseInterface
    {
        return $this->runApp('POST', '/server/register', true, null, $this->data);
    }

    /**
     * @throws \Exception
     */
    public function testNostingExists(): void
    {
        $this->assertEquals(406, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testWrongToken(): void
    {
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);
        $this->data['token'] = 'blubbtwo:blasdfb';
        $this->assertEquals(406, $this->exec()->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testWrongOwner(): void
    {
        $wrongUser = (new User())->setName('wrong user')->setEmail('test@another.example.com');
        $this->instance->setOwner($wrongUser);

        $this->infrastructure->import($this->user);
        $this->infrastructure->import($wrongUser);
        $this->infrastructure->import($this->instance);

        $this->assertEquals(406, $this->exec()->getStatusCode());
    }


    /**
     * @throws \Exception
     */
    public function testWithSetFqdn(): void
    {
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);

        $this->data['fqdn'] = 'new.fqdn.example.com';
        $result = $this->exec();
        $body = (string)$result->getBody();
        $this->assertEquals(200, $result->getStatusCode());
        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByName('testinstance');
        $this->assertEquals('new.fqdn.example.com', $instance->getFqdn());
    }



    /**
     * @throws \Exception
     */
    public function testWithNewlySetFqdn(): void
    {
        $this->instance->setFqdn('');
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);

        $this->data['fqdn'] = 'new.fqdn.example.com';
        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByName('testinstance');
        $this->assertEquals('new.fqdn.example.com', $instance->getFqdn());
    }


    /**
     * @throws \Exception
     */
    public function testWithNoFqdn(): void
    {
        $this->instance->setFqdn('');
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);

        $this->assertEquals(406, $this->exec()->getStatusCode());
    }


    /**
     * @throws \Exception
     *
     */
    public function testEverythingOk(): void
    {
        $this->instance->setOwner($this->user)->setIp('1.2.3.4');
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        $response = $this->exec();

        $body = (string)$response->getBody();

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($body, true);
        $this->assertArrayHasKey('token', $json);

        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByFqdn('testserver.example.com');
        $this->assertNotNull($instance);
        $this->assertEquals($instance->getId(), $json['server_id']);
        $this->assertEquals($instance->getOwner()->getId(), $json['user_id']);
    }
}