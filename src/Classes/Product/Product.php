<?php

namespace Helio\Panel\Product;

interface Product
{
    public function baseURL(): string;

    public function confirmURL(): string;

    public function emailSender(): string;

    public function title(): string;

    public function confirmationMailContent(): string;

    public function notificationMailTemplate(): string;

    /**
     * @param  string                    $event
     * @return array
     * @throws \InvalidArgumentException if notification event not supported for given product
     */
    public function notificationMessage(string $event): array;
}
