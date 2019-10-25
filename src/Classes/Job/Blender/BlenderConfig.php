<?php

namespace Helio\Panel\Job\Blender;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="blender"
 * )
 */
class BlenderConfig
{
    /**
     * Render type indicates whether this was used for an estimation or for an actual render.
     *
     * @var string
     * @OA\Property(
     *     type="string",
     *     enum={"estimation", "render"}
     * )
     */
    public $type;

    /**
     * @var object
     * @OA\Property()
     */
    public $settings;

    /**
     * @var object
     * @OA\Property()
     */
    public $analyzeData;

    /**
     * @var object
     * @OA\Property()
     */
    public $estimation;
}
