<?php

namespace Helio\Panel\Exception;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="http errors",
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         description="Always false in this case"
 *     ),
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         description="Error message"
 *     )
 * )
 */
class HttpException extends \RuntimeException implements \JsonSerializable
{
    /**
     * @var int
     */
    private $statusCode;

    public function __construct(int $statusCode, string $message = null, \Throwable $previous = null, int $code = 0)
    {
        parent::__construct($message, $code, $previous);

        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function jsonSerialize()
    {
        return [
            'success' => false,
            'error' => $this->message,
        ];
    }
}
