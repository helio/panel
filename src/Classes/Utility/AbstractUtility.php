<?php

namespace Helio\Panel\Utility;

use RuntimeException;

/**
 * Class AbstractUtility.
 */
class AbstractUtility
{
    final public function __construct()
    {
        throw new RuntimeException('Utilities cannot be instantiated.', 1564068999);
    }
}
