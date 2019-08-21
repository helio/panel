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

        $return = ServerUtility::isProd() ? @mail($user->getEmail(), 'Welcome to Helio', $content, 'From: hello@idling.host', '-f hello@idling.host') : true;
        if ($return) {
            LogHelper::info('Sent Confirmation Mail to ' . $user->getEmail());
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $user->getEmail() . '. Reason: ' . $return);
        }

        // write mail to PHPStorm Console
        if (PHP_SAPI === 'cli-server' && 'PROD' !== ServerUtility::get('SITE_ENV')) {
            LogHelper::logToConsole($content);
        }

        return $return;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    public static function notifyAdmin(string $content = ''): bool
    {
        if (ServerUtility::get('SLACK_WEBHOOK', '')) {
            try {
                $return = App::getSlackHelper()->sendNotification($content);
            } catch (GuzzleException $e) {
                $return = false;
            } catch (Exception $e) {
                $return = false;
            }
        } else {
            $return = ServerUtility::isProd() ? @mail('team@helio.exchange', 'Admin Notification from Panel', $content, 'From: hello@idling.host', '-f hello@idling.host') : true;
        }
        if ($return) {
            LogHelper::info('Sent Confirmation Mail to admin');
        } else {
            LogHelper::warn('Failed to sent Mail to admin. Reason: ' . $return);
        }

        return $return;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    public static function alertAdmin(string $content = ''): bool
    {
        if (ServerUtility::get('SLACK_WEBHOOK_ALERT', '')) {
            try {
                $return = App::getSlackHelper()->sendNotification($content);
            } catch (GuzzleException $e) {
                $return = false;
            } catch (Exception $e) {
                $return = false;
            }
        } else {
            $return = ServerUtility::isProd() ? @mail('team@helio.exchange', 'ADMIN ALERT from Panel', $content, 'From: hello@idling.host', '-f hello@idling.host') : true;
        }
        if ($return) {
            LogHelper::info('Sent Alert Mail to admin');
        } else {
            LogHelper::warn('Failed to sent alert Mail to admin. Reason: ' . $return);
        }

        return $return;
    }
}
