<?php

namespace Helio\Test\Functional;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helio\Panel\Model\User;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;

class ZapierTest extends TestCase
{


    /**
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testBasicZapierFunctionality(): void
    {

        ZapierHelper::getInstance()->setResponseStack([
            new Response(200, [], '{"success" => "true"}'),
            new Response(404, [], '{"success" => "true"}'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        $user = (new User())->setId(4343)->setEmail('test@local.com');

        $this->assertTrue(ZapierHelper::getInstance()->submitUserToZapier($user));
        $this->assertFalse(ZapierHelper::getInstance()->submitUserToZapier($user));

        $caught = false;
        try {
            ZapierHelper::getInstance()->submitUserToZapier($user);
        } catch (RequestException $e) {
            $caught = true;
        } finally {
            $this->assertTrue($caught);
        }
    }
}