<?php

namespace Helio\Panel\Utility;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\User;
use Helio\Panel\Product\Product;

class NotificationUtility extends AbstractUtility
{
    /**
     * @param User    $user
     * @param Product $product
     * @param string  $linkLifetime
     *
     * @return bool
     *
     * @throws Exception
     */
    public static function sendConfirmationMail(User $user, Product $product, string $linkLifetime = '+1 week'): bool
    {
        $token = JwtUtility::generateToken($linkLifetime, $user)['token'];
        $confirmLink = sprintf($product->confirmURL(), $token);

        $params = [
            'username' => $user->getName(),
            'product' => $product->title(),
            'link' => $confirmLink,
        ];
        $confirmationMailContent = $product->confirmationMailContent();
        $content = [
            'text' => self::replaceParams($confirmationMailContent['text'], $params),
            'html' => self::replaceParams($confirmationMailContent['html'], $params),
        ];
        $subject = 'Please confirm your e-mail address';

        return static::sendMail($user->getEmail(), $subject, $content, ['text' => 'Confirm Email & Login', 'link' => $confirmLink], $product->emailSender(), $product->emailHTMLLayout());
    }

    public static function notifyUser(User $user, Product $product, string $templateName, array $params): bool
    {
        try {
            $template = $product->notificationMessage($templateName, $params);
        } catch (\InvalidArgumentException $e) {
            LogHelper::info("template name ${templateName} not supported for product", ['product title' => $product->title()]);

            return false;
        }

        $params = array_merge([
            'product' => $product->title(),
            'baseURL' => $product->baseURL(),
            'username' => $user->getName(),
        ], $params);
        $message = self::replaceParams($template['message'], $params);
        $subject = self::replaceParams($template['subject'], $params);

        $params = [
            'username' => $user->getName(),
            'product' => $product->title(),
            'baseURL' => $product->baseURL(),
            'message' => $message,
        ];
        $notificationMailTemplate = $product->notificationMailTemplate();
        $content = [
            'text' => self::replaceParams($notificationMailTemplate['text'], $params),
            'html' => self::replaceParams($notificationMailTemplate['html'], $params),
        ];

        $buttonText = $template['buttonText'] ?? 'Open page';

        return static::sendMail($user->getEmail(), $subject, $content, ['text' => $buttonText, 'link' => $product->baseURL()], $product->emailSender(), $product->emailHTMLLayout());
    }

    public static function notifyAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendNotification($content) || static::sendMail('team@helio.exchange', 'Admin Notification from Panel', $content, ['text' => 'Open panel', 'link' => 'https://panel.idling.host']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function alertAdmin(string $content = ''): bool
    {
        try {
            return App::getSlackHelper()->sendAlert($content) || static::sendMail('team@helio.exchange', 'ADMIN ALERT from Panel', $content, ['text' => 'Open panel', 'link' => 'https://panel.idling.host']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function sendMail(string $recipient, string $subject, $content, array $button, array $from = ['hello@idling.host' => 'Helio'], string $htmlTemplate = null): bool
    {
        $subject = self::trimNewline($subject);
        if (is_array($content)) {
            $content['text'] = self::trimRepeatedWhitespace($content['text']);
        } else {
            $content = ['text' => self::trimRepeatedWhitespace($content)];
        }

        $transport = (new \Swift_SendmailTransport())->setLocalDomain(explode('@', array_key_first($from))[1]);
        $mailer = new \Swift_Mailer($transport);

        $message = (new \Swift_Message())
            ->setSubject($subject)
            ->setFrom($from)
            ->setTo($recipient)
            ->setBody(strip_tags($content['text']));

        if ($htmlTemplate && array_key_exists('html', $content)) {
            $html = self::replaceParams(
                $htmlTemplate,
                [
                    'title' => $subject,
                    'body' => $content['html'],
                    'buttonLink' => $button['link'],
                    'buttonText' => $button['text'],
                ]
            );
            $message->addPart($html, 'text/html');
        }

        try {
            $return = ServerUtility::isProd() ? $mailer->send($message) : true;
        } catch (\Swift_TransportException $e) {
            LogHelper::warn($e->getMessage());
            $return = false;
        }

        if ($return) {
            LogHelper::info('Sent Mail to ' . $recipient);
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $recipient . '. Reason: ' . $return);
        }

        // write mail to stdout
        if (PHP_SAPI === 'cli-server' && 'PROD' !== ServerUtility::get('SITE_ENV')) {
            LogHelper::logToConsole("mail sent to $recipient:\n$subject\n\n${content['text']}");
        }

        return $return;
    }

    protected static function replaceParams(string $template, array $params): string
    {
        $matches = [];
        preg_match_all('/{{(\w+)}}/m', $template, $matches, PREG_SET_ORDER);

        $countMatches = count(
            array_unique(
                array_map(
                    function ($match) {
                        return $match[0];
                    },
                    $matches
                )
            )
        );
        if ($countMatches > count($params)) {
            LogHelper::err('found more matches than params', [
                'matches' => $matches,
                'params' => $params,
            ]);
            throw new \InvalidArgumentException('params amount must be at least the size of found matches');
        }
        foreach ($matches as $match) {
            if (!isset($params[$match[1]])) {
                LogHelper::err('missing param found in template', [
                    'matches' => $matches,
                    'params' => $params,
                    'template' => $template,
                ]);
                throw new \InvalidArgumentException('all params need to be supplied');
            }

            $template = str_replace('{{' . $match[1] . '}}', $params[$match[1]], $template);
        }

        return $template;
    }

    protected static function trimNewline(string $str): string
    {
        return str_replace("\n", '', $str);
    }

    protected static function trimRepeatedWhitespace(string $str): string
    {
        $str = preg_replace('/ {2,}/m', ' ', $str);
        if (false === $str) {
            throw new \Exception(preg_last_error());
        }

        return trim($str);
    }
}
