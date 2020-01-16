<?php

namespace Helio\Panel\Product;

use Helio\Panel\Model\User;

interface Product
{
    public function baseURL(): string;

    public function confirmURL(): string;

    public function callToActionURL(User $user): string;

    public function emailSender(): array;

    public function title(): string;

    public function emailHTMLLayout(): string;

    public function confirmationMailContent(): array;

    public function notificationMailTemplate(): array;

    /**
     * @param  string                    $event
     * @param  array                     $params
     * @return array
     * @throws \InvalidArgumentException if notification event not supported for given product
     */
    public function notificationMessage(string $event, array $params): array;

    public function notify(User $user, string $event, array $params): void;
}
