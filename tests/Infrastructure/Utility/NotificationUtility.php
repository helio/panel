<?php

namespace Helio\Test\Infrastructure\Utility;

class NotificationUtility extends \Helio\Panel\Utility\NotificationUtility
{
    public static $mails = [];

    protected static function sendMail(string $recipient, string $subject, string $content, string $from = 'hello@idling.host'): bool
    {
        $subject = self::trimNewline($subject);
        $content = self::trimRepeatedWhitespace($content);
        self::$mails[] = ['recipient' => $recipient, 'subject' => $subject, 'content' => $content, 'from' => $from];

        return true;
    }
}
