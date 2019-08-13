<?php

namespace Helio\Test\Integration;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ServerApiRegisterTest.
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
     * @var array
     */
    protected $header;

    /**
     * @var bool
     */
    protected $imported = false;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())
            ->setEmail('test@example.com')
            ->setActive(true)
            ->setName('testuser')
            ->setCreated();

        $this->instance = (new Instance())
            ->setFqdn('testserver.example.com')
            ->setName('testinstance')
            ->setCreated()
            ->setStatus(InstanceStatus::CREATED);
        $this->instance->setOwner($this->user);

        $this->data = ['email' => $this->user->getEmail(), 'fqdn' => 'testserver.example.com'];
    }

    /**
     * @param array $data
     * @param bool  $withData
     *
     * @return ResponseInterface
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function exec(array $data = null, bool $withData = true): ResponseInterface
    {
        if ($withData) {
            $this->import();
        } else {
            $this->assertFalse($this->imported, 'Data already imported, cannot exec test. Please reset data again.');
        }

        return $this->runApp('POST', '/api/instance/register', true, $this->header, null !== $data ? $data : $this->data);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function import(): void
    {
        if ($this->imported) {
            return;
        }

        $this->infrastructure->getEntityManager()->persist($this->instance);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();

        $this->header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, $this->instance)['token']];
        $this->imported = true;
    }

    /**
     * @throws \Exception
     */
    public function testNothingExists(): void
    {
        $this->assertEquals(StatusCode::HTTP_UNAUTHORIZED, $this->exec(null, false)->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testWrongToken(): void
    {
        $this->import();
        $backupheaders = $this->header;
        $this->header['Authorization'] = 'Bearer i-dbei45:nonsense';
        $result = $this->exec();
        $debug = (string) $result->getBody();
        $this->assertEquals(StatusCode::HTTP_UNAUTHORIZED, $result->getStatusCode());
        $this->header = $backupheaders;
    }

    /**
     * @throws \Exception
     */
    public function testWithSetFqdn(): void
    {
        $this->import();
        $result = $this->exec(['fqdn' => 'new.fqdn.example.com']);
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
        $this->import();

        $result = $this->exec(['fqdn' => 'new.fqdn.example.com']);

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
        $this->import();
        $backupdata = $this->data;
        unset($this->data['fqdn']);
        $result = $this->exec();
        $this->data = $backupdata;

        $this->assertEquals(406, $result->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testEverythingOk(): void
    {
        $this->instance->setOwner($this->user)->setIp('1.2.3.4');
        $this->infrastructure->getEntityManager()->persist($this->instance);
        $this->infrastructure->getEntityManager()->flush();
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        $response = $this->exec();

        $body = (string) $response->getBody();

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
