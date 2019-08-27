<?php

namespace Helio\Test\Functional;

use Exception;
use GuzzleHttp\Psr7\Response;
use Helio\Panel\Model\User;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

/**
 * Class HomepageTest.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class PanelTest extends TestCase
{
    /**
     * Test that the index route returns a rendered response.
     *
     * @throws Exception
     */
    public function testGetHomeContainsWithLogin(): void
    {
        $response = $this->runWebApp('GET', '/');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Log In', (string) $response->getBody());
        $this->assertStringContainsString('<form', (string) $response->getBody());
    }

    /**
     * Test that the index route returns a rendered response.
     *
     * @throws \Exception
     */
    public function testLoginGetRedirectedWithExampleUser(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/user/login', true, null, ['email' => 'email@example.com']);

        $this->assertEquals(302, $response->getStatusCode(), $response->getBody());
        $this->assertCount(1, $response->getHeader('Location'));
        $this->assertStringContainsString('confirm?signature', $response->getHeader('Location')[0]);
    }

    /**
     * @throws \Exception
     */
    public function testActivationLinkActivatesUser(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/user/login', true, null, ['email' => 'email@example.com']);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertCount(1, $response->getHeader('Location'));
        $this->assertStringContainsString('/confirm', $response->getHeader('Location')[0]);

        $confirmationUrl = $response->getHeader('Location')[0];

        $response = $this->runWebApp('GET', $confirmationUrl, true);
        $this->assertEquals(StatusCode::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('/panel', $response->getHeaderLine('location'));
        $response = $this->runWebApp('GET', $response->getHeaderLine('location'), true, null, null, $response->getHeaderLine('set-cookie'));

        /** @var User $user */
        $user = $this->userRepository->findOneByEmail('email@example.com');
        $this->assertTrue($user->isActive());
    }

    /**
     * @throws \Exception
     */
    public function testLoggedOutInvalidatesToken(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/user/login', true, null, ['email' => 'email@example.com'], null);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertCount(1, $response->getHeader('Location'));
        $this->assertStringContainsString('/confirm', $response->getHeader('Location')[0]);

        $confirmationUrl = $response->getHeader('Location')[0];

        $response = $this->runWebApp('GET', $confirmationUrl, true);
        $this->assertEquals(StatusCode::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('/panel', $response->getHeaderLine('location'));
        $this->runWebApp('GET', $response->getHeaderLine('location'), true, null, null, $response->getHeaderLine('set-cookie'));

        /** @var User $user */
        $user = $this->userRepository->findOneByEmail('email@example.com');

        // make sure loggedOut is not set
        $user->setLoggedOut(null);
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush();

        // simulate second-percision when storing values in the database
        $now = \DateTime::createFromFormat('Y-m-d H:i:s', (new \DateTime())->format('Y-m-d H:i:s'));

        $this->runWebApp('GET', '/panel/logout', true);

        $user = $this->userRepository->findOneByEmail('email@example.com');

        $this->assertGreaterThanOrEqual($now, $user->getLoggedOut());

        $response = $this->runWebApp('GET', $confirmationUrl, true);
        $this->assertEquals(302, $response->getStatusCode());
    }
}
