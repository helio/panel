<?php

namespace Helio\Test\Functional;

use Exception;
use GuzzleHttp\Psr7\Response;
use Helio\Panel\Model\User;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\Infrastructure\Utility\NotificationUtility;
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
     * @throws \Exception
     */
    public function testActivationLinkActivatesUser(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/user/login', true, null, ['email' => 'email@example.com']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $response->getHeader('Location'));

        $confirmationMail = NotificationUtility::$mails[0];

        $this->assertStringContainsString('/confirm', $confirmationMail['content']);

        preg_match_all('/^\s+(https?):\/\/(.+)$/m', $confirmationMail['content'], $matches, PREG_SET_ORDER);

        $confirmationUrl = sprintf('%s://%s', $matches[0][1], $matches[0][2]);

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
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $response->getHeader('Location'));

        $confirmationMail = NotificationUtility::$mails[0];

        $this->assertStringContainsString('/confirm', $confirmationMail['content']);

        preg_match_all('/^\s+(https?):\/\/(.+)$/m', $confirmationMail['content'], $matches, PREG_SET_ORDER);

        $confirmationUrl = sprintf('%s://%s', $matches[0][1], $matches[0][2]);

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

        $this->assertEqualsWithDelta($now, $user->getLoggedOut(), 1.0);

        $response = $this->runWebApp('GET', $confirmationUrl, true);
        $this->assertEquals(302, $response->getStatusCode());
    }
}
