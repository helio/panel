<?php

namespace Helio\Test\Functional;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helio\Test\Functional\Fixture\Helper\ZapierHelper;
use Helio\Test\Functional\Fixture\Model\User;

class ZapierTest extends BaseRequestCase
{


    /**
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testBasicZapierFunctionality(): void
    {

        $helper = ZapierHelper::getTestInstance()->setResponseStack([
            new Response(200, [], '{"success" => "true"}'),
            new Response(404, [], '{"success" => "true"}'),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        $user = (new User())->setId(4343)->setEmail('test@local.com');

        $this->assertTrue($helper->submitUserToZapier($user));
        $this->assertFalse($helper->submitUserToZapier($user));

        $caught = false;
        try {
            $helper->submitUserToZapier($user);
        } catch (RequestException $e) {
            $caught = true;
        } finally {
            $this->assertTrue($caught);
        }
    }
}