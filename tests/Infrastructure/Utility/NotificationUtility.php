<?php

namespace Helio\Test\Infrastructure\Utility;

class NotificationUtility extends \Helio\Panel\Utility\NotificationUtility
{
    public static $mails = [];

    protected static function sendMail(string $recipient, string $subject, string $content): bool
    {
        self::$mails[] = ['recipient' => $recipient, 'subject' => $subject, 'content' => $content];

        return true;
    }
}
