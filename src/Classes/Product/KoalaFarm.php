<?php

namespace Helio\Panel\Product;

use Helio\Panel\Utility\ServerUtility;

class KoalaFarm implements Product
{
    private const confirmationMailContent = <<<EOM
    Hi {{username}}
    Welcome to Koala farm. Please click this link to log in:
    {{link}}
EOM;

    private const notificationMailTemplate = <<<EOM
    Hi {{username}}
    Thanks for using Koala farm!
    
    {{message}}
EOM;

    private const notifications = [
        'executionDone' => [
            'subject' => 'Rendering completed!',
            'message' => 'A new render completed successfully! Please visit {{baseURL}} to download the results.',
        ],
    ];

    public function baseURL(): string
    {
        return ServerUtility::get('KOALA_FARM_ORIGIN');
    }

    public function confirmURL(): string
    {
        // hash token to prevent logging of token in server side logs.
        return $this->baseURL() . '#token=%s';
    }

    public function emailSender(): string
    {
        return 'hello@koala.farm';
    }

    public function title(): string
    {
        return 'Koala Farm';
    }

    public function confirmationMailContent(): string
    {
        return self::confirmationMailContent;
    }

    public function notificationMailTemplate(): string
    {
        return self::notificationMailTemplate;
    }

    public function notificationMessage(string $event): array
    {
        if (!isset(self::notifications[$event])) {
            throw new \InvalidArgumentException("notification message ${event} not implemented");
        }

        return self::notifications[$event];
    }
}
