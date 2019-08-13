<?php

namespace Helio\Panel\Utility;

use Exception;
use DateTime;
use Firebase\JWT\JWT;
use Helio\Panel\App;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Tuupola\Base62;

class JwtUtility extends AbstractUtility
{
    /**
     * @param string|null   $duration
     * @param User|null     $user
     * @param Instance|null $instance
     * @param Job|null      $job
     *
     * @return array
     *
     * @throws Exception
     */
    public static function generateToken(string $duration = null, User $user = null, Instance $instance = null, Job $job = null): array
    {
        $now = new DateTime('now', ServerUtility::getTimezoneObject());
        $jti = (new Base62())->encode(random_bytes(16));
        $payload = [
            'iat' => $now->getTimestamp(),
            'jti' => $jti,
        ];
        if ('sticky' === $duration) {
            $payload['sticky'] = true;
        } elseif ($duration) {
            $payload['exp'] = (new DateTime($duration, ServerUtility::getTimezoneObject()))->getTimestamp();
        }
        if ($user) {
            $payload['u'] = $user->getId();
        }
        if ($job) {
            $payload['j'] = $job->getId();
        }
        if ($instance) {
            $payload['i'] = $instance->getId();
        }
        $secret = ServerUtility::get('JWT_SECRET');
        $token = JWT::encode($payload, $secret, 'HS256');

        return [
            'token' => $token,
            'expires' => array_key_exists('exp', $payload) ? $payload['exp'] : '',
        ];
    }

    /**
     * @param string $duration
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function generateNewTokenForCurrentSession(string $duration)
    {
        $params = [$duration];

        // prevent type error
        $mayAddMore = true;

        // resolve impresonation
        if (App::getApp()->getContainer()->has('impersonatinguser')) {
            $params[] = App::getApp()->getContainer()->get('impersonatinguser');
        } elseif (App::getApp()->getContainer()->has('user')) {
            $params[] = App::getApp()->getContainer()->get('user');
        } else {
            $mayAddMore = false;
        }

        if ($mayAddMore && App::getApp()->getContainer()->has('instance')) {
            $params[] = App::getApp()->getContainer()->get('instance');
        } else {
            $mayAddMore = false;
        }

        if ($mayAddMore && App::getApp()->getContainer()->has('job')) {
            $params[] = App::getApp()->getContainer()->get('job');
        }

        return forward_static_call_array([self::class, 'generateToken'], $params);
    }
}
