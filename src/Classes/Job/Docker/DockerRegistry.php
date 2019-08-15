<?php

namespace Helio\Panel\Job\Docker;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema()
 */
class DockerRegistry
{
    /**
     * @var string
     * @OA\Property();
     */
    public $server;

    /**
     * @var string
     * @OA\Property()
     */
    public $username;

    /**
     * @var string
     * @OA\Property()
     */
    public $password;

    /**
     * @var object
     * @OA\Property(
     *     @OA\Property(
     *         property="env",
     *         example={"SECRET_SAUCE": "https://my.vault.example:42/"}
     *     )
     * )
     */
    public $cliparams;
}
