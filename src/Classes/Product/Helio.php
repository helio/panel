<?php

namespace Helio\Panel\Product;

use Helio\Panel\Utility\ServerUtility;

class Helio implements Product
{
    /**
     * @var string
     */
    public const confirmationMailContent = <<<EOM
    Hi {{username}}
    Welcome to Helio. Please click this link to log in:
    {{link}}
EOM;

    /**
     * @var string
     */
    public const notificationMailTemplate = <<<EOM
    Hi {{username}}
    This is an automated notification from {{product}}.
    
    {{message}}
EOM;

    public const notifications = [
        'jobRemoved' => [
            'subject' => 'Job {{name}} ({{id}}) removed',
            'message' => 'Your job with the id {{id}} is now completely removed from {{product}}',
        ],
        'jobReady' => [
            'subject' => 'Job {{name}} ({{id}}) ready',
            'message' => 'Your job with the id {{id}} is now ready to be executed on {{product}}',
        ],
        'executionDone' => [
            'subject' => 'Job {{name}} ({{id}}), Execution {{executionName}} ({{executionId}}) executed',
            'message' => "Your Job {{id}} with id {{executionId}} was successfully executed\nThe results can now be used.",
        ],
        'allExecutionsDone' => [
            'subject' => 'Job {{name}} ({{id}}), All executions executed',
            'message' => "Your Job {{id}} was successfully executed\nThe results can now be used.",
        ],
    ];

    public function baseURL(): string
    {
        if (ServerUtility::isTestEnv()) {
            return 'http://localhost';
        }
        if (ServerUtility::isLocalDevEnv()) {
            return 'https://panel.helio.test';
        }

        return 'https://panel.idling.host';
    }

    public function confirmURL(): string
    {
        return $this->baseURL() . '/confirm?signature=%s';
    }

    public function emailSender(): string
    {
        return 'hello@idling.host';
    }

    public function title(): string
    {
        return 'Helio';
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
