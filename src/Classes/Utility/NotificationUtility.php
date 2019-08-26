<?php

namespace Helio\Panel\Utility;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
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
        $content = vsprintf(self::$confirmationMailContent, [
            $user->getName(),
            ServerUtility::getBaseUrl() . 'confirm?signature=' .
            JwtUtility::generateToken($linkLifetime, $user)['token'],
        ]);

        return self::sendMail($user->getEmail(), 'Welcome to Helio', $content);
    }

    /**
     * @param  User   $user
     * @param  string $message
     * @param  int    $context has to be a constant from NotificationPreferences
     * @return bool
     */
    public static function notifyUser(User $user, string $message, int $context): bool
    {
        if (!$user->getNotificationPreference($context)) {
            return false;
        }

        $content = vsprintf(self::$notificationMailTemplate, [
            $user->getName(),
            $message,
        ]);

        return self::sendMail($user->getEmail(), 'New Notification from Helio', $content);
    }

    /**
     * @param  string          $content
     * @return bool
     * @throws GuzzleException
     */
    public static function notifyAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendNotification($content) || self::sendMail('team@helio.exchange', 'Admin Notification from Panel', $content);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  string          $content
     * @return bool
     * @throws GuzzleException
     */
    public static function alertAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendAlert($content) || self::sendMail('team@helio.exchange', 'ADMIN ALERT from Panel', $content);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  string $recepient
     * @param  string $subject
     * @param  string $content
     * @return bool
     */
    protected static function sendMail(string $recepient, string $subject, string $content)
    {
        $return = ServerUtility::isProd() ? @mail($recepient, $subject, $content, 'From: hello@idling.host', '-f hello@idling.host') : true;

        if ($return) {
            LogHelper::info('Sent Mail to ' . $recepient);
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $recepient . '. Reason: ' . $return);
        }

        // write mail to PHPStorm Console
        if (PHP_SAPI === 'cli-server' && 'PROD' !== ServerUtility::get('SITE_ENV')) {
            LogHelper::logToConsole("mail sent to $recepient:\n$content");
        }

        return $return;
    }
}
