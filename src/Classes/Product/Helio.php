<?php

namespace Helio\Panel\Product;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;
use Helio\Panel\Utility\ViewUtility;

class Helio implements Product
{
    private const confirmationMailContent = <<<EOM
    Hi {{username}}
    Welcome to Helio. Please click this link to log in:
    {{link}}
EOM;
    private const confirmationMailHTMLContent = <<<EOM
Hi {{username}}<br>
Welcome to Helio. Please click the button below to log in:
EOM;

    private const notificationMailTemplate = <<<EOM
    Hi {{username}}
    This is an automated notification from {{product}}.
    
    {{message}}
EOM;
    private const notificationMailHTMLTemplate = <<<EOM
Hi {{username}}<br>
This is an automated notification from {{product}}.<br>
<br>
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

    public function callToActionURL(User $user): string
    {
        return $this->baseURL();
    }

    public function confirmURL(): string
    {
        return $this->baseURL() . '/confirm?signature=%s';
    }

    public function emailSender(): array
    {
        return ['hello@idling.host' => $this->title()];
    }

    public function title(): string
    {
        return 'Helio';
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

        return self::notifications[$event];
    }

    public function emailHTMLLayout(): string
    {
        return ViewUtility::getEmailTemplate('helio');
    }
}
