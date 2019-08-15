<?php

namespace Helio\Panel\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema()
 */
class JobCreateResponse extends DefaultResponse
{
    /**
     * @OA\Property(description="ID of the created job")
     * @var int
     */
    public $id;

    /**
     * @OA\Property(description="Generated JWT token for the job")
     * @var string
     */
    public $token;

    /**
     * @OA\Property(description="HTML content to use in UIs")
     * @var string
     */
    public $html;

    public function __construct(int $id, string $token, string $html = '', string $message = '')
    {
        parent::__construct(true, $message);

        $this->id = $id;
        $this->token = $token;
        $this->html = $html;
    }
}
