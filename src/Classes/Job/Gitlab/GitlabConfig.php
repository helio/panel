<?php

namespace Helio\Panel\Job\Gitlab;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="gitlab"
 * )
 */
class GitlabConfig
{
    /**
     * Endpoint.
     *
     * @var string
     * @OA\Property()
     */
    public $gitlabEndpoint;

    /**
     * Endpoint.
     *
     * @var string
     * @OA\Property()
     */
    public $gitlabToken;

    /**
     * Tags.
     *
     * @var string
     * @OA\Property()
     */
    public $gitlabTags;
}
