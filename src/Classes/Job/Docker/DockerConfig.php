<?php

namespace Helio\Panel\Job\Docker;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="docker"
 * )
 */
class DockerConfig
{
    /**
     * Docker image.
     *
     * @var string
     * @OA\Property(
     *     example="nginx:1.8"
     * )
     */
    public $image;

    /**
     * @var object
     * @OA\Property(
     *     example={"SOURCE_PATH":"https://account-name.zone-name.web.core.windows.net", "TARGET_PATH": "https://bucket.s3.aws-region.amazonaws.com"}
     * )
     */
    public $env;

    /**
     * @var DockerRegistry
     * @OA\Property()
     */
    public $registry;
}
