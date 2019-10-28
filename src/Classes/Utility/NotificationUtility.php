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
        $content = self::replaceParams($product->confirmationMailContent(), [
            'username' => $user->getName(),
            'product' => $product->title(),
            'link' => sprintf($product->confirmURL(), $token),
        ]);
        $subject = self::replaceParams('Welcome to {{product}}', [
            'product' => $product->title(),
        ]);

        return static::sendMail($user->getEmail(), $subject, $content, $product->emailSender());
    }

    /**
     * @param User    $user
     * @param Product $product
     * @param string  $templateName
     * @param array   $params
     *
     * @return bool
     */
    public static function notifyUser(User $user, Product $product, string $templateName, array $params): bool
    {
        try {
            $template = $product->notificationMessage($templateName);
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
        $content = self::replaceParams($product->notificationMailTemplate(), [
            'username' => $user->getName(),
            'product' => $product->title(),
            'message' => $message,
        ]);

        return static::sendMail($user->getEmail(), $subject . ' - ' . $product->title(), $content, $product->emailSender());
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

    protected static function sendMail(string $recipient, string $subject, string $content, string $from = 'hello@idling.host'): bool
    {
        $subject = self::trimNewline($subject);
        $content = self::trimRepeatedWhitespace($content);
        $return = ServerUtility::isProd() ?
            @mail($recipient,
                $subject,
                $content,
                'From: ' . $from,
                '-f ' . $from
            ) : true;

        if ($return) {
            LogHelper::info('Sent Mail to ' . $recipient);
        } else {
            LogHelper::warn('Failed to sent Mail to ' . $recipient . '. Reason: ' . $return);
        }

        // write mail to stdout
        if (PHP_SAPI === 'cli-server' && 'PROD' !== ServerUtility::get('SITE_ENV')) {
            LogHelper::logToConsole("mail sent to $recipient:\n$subject\n\n$content");
        }

        return $return;
    }

    protected static function replaceParams(string $template, array $params): string
    {
        $matches = [];
        preg_match_all('/{{(\w+)}}/m', $template, $matches, PREG_SET_ORDER);
        if (count($matches) > count($params)) {
            LogHelper::err('found more matches than params', [
                'matches' => $matches,
                'params' => $params,
                'template' => $template,
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
