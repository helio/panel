<?php

namespace Helio\Panel\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema()
 */
class TokenResponse extends DefaultResponse
{
    /**
     * @OA\Property(description="Auth token")
     * @var string
     */
    public $token;

    public function __construct(string $token)
    {
        parent::__construct(true, '');

        $this->token = $token;
    }
}
