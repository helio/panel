<?php

namespace Helio\Panel\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema()
 */
class ServerInitResponse extends DefaultResponse
{
    /**
     * @OA\Property(description="User id")
     * @var int
     */
    public $user_id;

    /**
     * @OA\Property(description="Server id")
     * @var int
     */
    public $server_id;

    public function __construct(int $user_id, int $server_id, string $message)
    {
        parent::__construct(true, $message);

        $this->id = $id;
        $this->token = $token;
        $this->html = $html;
    }
}
