<?php

namespace Helio\Panel\Utility;

use Helio\Panel\Helper\LogHelper;
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
        $content = vsprintf(self::$confirmationMailContent, [
            $user->getName(),
            ServerUtility::getBaseUrl() . 'panel?token=' .
            JwtUtility::generateToken($user->getId(), $linkLifetime)['token']
        ]);
        $return = @mail($user->getEmail(), 'Welcome to Helio', $content, 'From: hello@idling.host', '-f hello@idling.host'
        );
        if ($return) {
            LogHelper::info('Sent Confirmation Mail to ' . $user->getEmail());
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $user->getEmail() . '. Reason: ' . $return);
        }

        // write mail to PHPStorm Console
        if (PHP_SAPI === 'cli-server' && ServerUtility::get('SITE_ENV') === 'DEV') {
            LogHelper::logToConsole($content);
        }

        return $return;
    }


    /**
     * @param string $content
     * @return bool
     */
    public static function sendMailToAdmin(string $content = ''): bool
    {
        return @mail('team@opencomputing.cloud', 'Admin Notification from Panel', $content, 'From: hello@idling.host', '-f hello@idling.host');
    }
}