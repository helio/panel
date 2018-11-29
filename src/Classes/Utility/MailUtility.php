<?php

namespace Helio\Panel\Utility;

use Helio\Panel\Model\User;

class MailUtility
{


    /**
     * @var string
     */
    protected static $confirmationMailContent = <<<EOM
    Hi %s 
    Welcome to helio. Please klick this link to log in:
    %s
EOM;


    /**
     * @param User $user
     * @param string $linkLifetime
     *
     * @return bool
     * @throws \Exception
     */
    public static function sendConfirmationMail(User $user, string $linkLifetime = '+1 week'): bool
    {
        $return = @mail($user->getEmail(), 'Welcome to Helio',
            vsprintf(self::$confirmationMailContent, [
                $user->getName(),
                ServerUtility::getBaseUrl() . 'panel?token=' .
                JwtUtility::generateToken($user->getId(), $linkLifetime)['token']
            ]), 'From: hello@idling.host', '-f hello@idling.host'
        );

        if (ServerUtility::get('SITE_ENV') === 'DEV') {
            file_put_contents('/tmp/mail.log', vsprintf(self::$confirmationMailContent, [
                $user->getName(),
                ServerUtility::getBaseUrl() . 'panel?token=' .
                JwtUtility::generateToken($user->getId(), $linkLifetime)['token']
            ])."\n\n", FILE_APPEND);
        }
        return $return;
    }
}