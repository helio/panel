<?php

namespace Helio\Panel\Utility;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\User;

/**
 * Class MailUtility.
 */
class NotificationUtility extends AbstractUtility
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
     * @var string
     */
    protected static $notificationMailTemplate = <<<EOM
    Hi %s 
    This is an automated notification from the Helio platform.
    
    %s
EOM;

    /**
     * @param User   $user
     * @param string $linkLifetime
     *
     * @return bool
     *
     * @throws Exception
     */
    public static function sendConfirmationMail(User $user, string $linkLifetime = '+1 week'): bool
    {
        $content = vsprintf(static::$confirmationMailContent, [
            $user->getName(),
            // FIXME(mw): allow to use a different base URL (koala.farm case)
            ServerUtility::getBaseUrl() . 'confirm?signature=' .
            JwtUtility::generateToken($linkLifetime, $user)['token'],
        ]);

        return static::sendMail($user->getEmail(), 'Welcome to Helio', $content);
    }

    /**
     * @param  User   $user
     * @param  string $subject
     * @param  string $message
     * @return bool
     */
    public static function notifyUser(User $user, string $subject, string $message): bool
    {
        $content = vsprintf(static::$notificationMailTemplate, [
            $user->getName(),
            $message,
        ]);

        return static::sendMail($user->getEmail(), $subject . ' - Helio', $content);
    }

    public static function notifyAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendNotification($content) || static::sendMail('team@helio.exchange', 'Admin Notification from Panel', $content);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function alertAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendAlert($content) || static::sendMail('team@helio.exchange', 'ADMIN ALERT from Panel', $content);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function sendMail(string $recipient, string $subject, string $content): bool
    {
        $subject = str_replace("\n", '', $subject);
        $return = ServerUtility::isProd() ?
            @mail($recipient,
                $subject,
                $content,
                'From: hello@idling.host',
                '-f hello@idling.host'
            ) : true;

        if ($return) {
            LogHelper::info('Sent Mail to ' . $recipient);
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $recipient . '. Reason: ' . $return);
        }

        // write mail to PHPStorm Console
        if (PHP_SAPI === 'cli-server' && 'PROD' !== ServerUtility::get('SITE_ENV')) {
            LogHelper::logToConsole("mail sent to $recipient:\n$subject\n\n$content");
        }

        return $return;
    }
}
