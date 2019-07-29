<?php

namespace Helio\Panel\Utility;

use \RuntimeException;

/**
 * Class AbstractUtility
 * @package Helio\Panel\Utility
 */
class AbstractUtility
{
    public final function __construct()
    {
        throw new RuntimeException('Utilities cannot be instantiated.', 1564068999);
    }
}