<?php
namespace Helio\Test\Functional;

use Psr\Http\Message\ResponseInterface;

class ZapierTest extends BaseRequestCase {


    /**
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testBasicZapierFunctionality(): void {
        /** @var ResponseInterface $result */
        $result = $this->call('GET');
        $this->assertEquals(200, $result->getStatusCode());
    }
}