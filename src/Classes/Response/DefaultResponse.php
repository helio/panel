<?php

namespace Helio\Panel\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema()
 */
class DefaultResponse extends AbstractResponse
{
    /**
     * @OA\Property(description="Describes if the response is successful")
     * @var bool
     */
    public $success;

    /**
     * @OA\Property(description="Human readable status message")
     * @var string
     */
    public $message;

    /**
     * @OA\Property(description="HTML notification data for UIs")
     * @var string
     */
    public $notification;

    public function __construct(bool $success, string $message)
    {
        $this->success = $success;
        $this->message = $message;
    }

    public function setNotification(string $notification): void
    {
        $this->notification = $notification;
    }

    public static function fromThrowable(\Throwable $t): DefaultResponse
    {
        return new DefaultResponse(false, $t->getMessage());
    }
}
