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
    public function setUp()
    {
        parent::setUp();

        $this->user = (new User())->setName('testuser')->setCreated()->setEmail('test@example.com')->setActive(true);
        $this->infrastructure->import($this->user);
        $this->instance = (new Instance())
            ->setName('testinstance')
            ->setCreated()
            ->setFqdn('testserver.example.com')
            ->setOwner($this->user)
            ->setStatus(InstanceStatus::CREATED);
        $this->infrastructure->import($this->instance);
        $this->instance->setToken(JwtUtility::generateInstanceIdentificationToken($this->instance));
        $this->infrastructure->import($this->instance);

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
        $this->data['token'] = 'blubbtwo:blasdfb';
        $this->assertEquals(406, $this->exec()->getStatusCode());
    }


    /**
     * @throws \Exception
     *
     * TODO: Finish
     */
    public function testEverythingOk(): void
    {
        $this->markTestIncomplete('WIP');
        $this->instance->setOwner($this->user)->setIp('1.2.3.4');
        $this->infrastructure->import($this->user);
        $this->infrastructure->import($this->instance);
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        $response = $this->exec();

        $this->assertEquals(200, $response->getStatusCode());

        $body = (string)$response->getBody();
        $json = json_decode($body, true);
        /** @var Instance $instance */
        $instance = $this->instanceRepository->findOneByFqdn('testserver.example.com');
        $this->assertNotNull($instance);
        $this->assertContains('token', $body);
        $this->assertEquals($instance->getToken(), $json['token']);
    }
}