<?php

namespace Helio\Panel\Product;

use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Panel\Utility\ViewUtility;
use Intercom\IntercomClient;

class KoalaFarm implements Product
{
    private const confirmationMailContent = <<<EOM
    Hi {{username}}
    Welcome to Koala farm. Please click this link to log in:
    {{link}}
EOM;
    private const confirmationMailHTMLContent = <<<EOM
Hi {{username}}<br>
Welcome to Koala farm. Please click the button below to log in:
EOM;

    private const notificationMailTemplate = <<<EOM
    Hi {{username}}
    Thanks for using Koala farm!
    Please visit {{baseURL}} to view the results.
    
    {{message}}
EOM;
    private const notificationMailHTMLTemplate = <<<EOM
Hi {{username}}<br>
Thanks for using Koala farm!<br>
<br>
{{message}}
EOM;

    private const notifications = [
        'allExecutionsDone' => [
            'estimation' => [
                'subject' => '{{name}} ready to render!',
                'message' => 'The estimation for {{name}} has been completed. Open the page to view the estimation and start rendering!',
                'buttonText' => 'Start rendering',
            ],
            'render' => [
                'subject' => '{{name}} finished rendering!',
                'message' => 'A koality render is waiting for you! Download the rendered files now :)',
                'buttonText' => 'Download files',
            ],
        ],
    ];

    public function baseURL(): string
    {
        return ServerUtility::get('KOALA_FARM_ORIGIN');
    }

    public function callToActionURL(User $user): string
    {
        $token = JwtUtility::generateToken('+1 week', $user)['token'];

        return sprintf($this->confirmURL(), $token);
    }

    public function confirmURL(): string
    {
        // hash token to prevent logging of token in server side logs.
        return $this->baseURL() . '#token=%s';
    }

    public function emailSender(): array
    {
        return ['hello@koala.farm' => $this->title()];
    }

    public function title(): string
    {
        return 'Koala Render Farm';
    }

    public function emailHTMLLayout(): string
    {
        return ViewUtility::getEmailTemplate('koala-farm');
    }

    public function confirmationMailContent(): array
    {
        return ['text' => self::confirmationMailContent, 'html' => self::confirmationMailHTMLContent];
    }

    public function notificationMailTemplate(): array
    {
        return ['text' => self::notificationMailTemplate, 'html' => self::notificationMailHTMLTemplate];
    }

    public function notificationMessage(string $event, array $params): array
    {
        if (!isset(self::notifications[$event])) {
            throw new \InvalidArgumentException("notification message ${event} not implemented");
        }

        $config = json_decode($params['config'], true);
        if (array_key_exists('type', $config)) {
            return self::notifications[$event][$config['type']];
        }

        return self::notifications[$event]['render'];
    }

    public function notify($user, $event, $params): void
    {
        self::IntercomEvent($user, $event, $params);
    }

    public static function IntercomEvent(User $user, string $event, array $metadata): bool
    {
        $apiKey = ServerUtility::get('INTERCOM_API_KEY', false);
        if (false == $apiKey) {
            return false;
        }

        $client = new IntercomClient($apiKey);
        try {
            $client->users->update([
                'email' => $user->getEmail(),
                'user_id' => $user->getId(),
            ]);

            $eventPayload = [
                'email' => $user->getEmail(),
                'event_name' => $event,
                'created_at' => ServerUtility::getCurrentUTCTimestamp(),
            ];

            if (count($metadata) > 0) {
                $eventPayload['metadata'] = $metadata;
            }

            $client->events->create($eventPayload);

            return true;
        } catch (\Throwable $t) {
            LogHelper::warn('could not send Event to Intercom', ['err' => $t, 'event' => $event, 'email' => $email, 'metadata' => $metadata]);
        }

        return false;
    }
}
