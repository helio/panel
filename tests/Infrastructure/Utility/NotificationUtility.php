<?php

namespace Helio\Test\Infrastructure\Utility;

class NotificationUtility extends \Helio\Panel\Utility\NotificationUtility
{
    public static $mails = [];

    protected static function sendMail(string $recipient, string $subject, $content, array $button, array $from = ['hello@idling.host' => 'Helio'], string $htmlTemplate = null): bool
    {
        $subject = self::trimNewline($subject);
        if (is_array($content)) {
            $content['text'] = self::trimRepeatedWhitespace($content['text']);
        } else {
            $content = ['text' => self::trimRepeatedWhitespace($content)];
        }
        self::$mails[] = ['recipient' => $recipient, 'subject' => $subject, 'button' => $button, 'content' => $content, 'from' => $from];

        return true;
    }
}
