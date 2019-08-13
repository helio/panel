<?php

namespace Helio\Panel\Utility;

use Adbar\Dot;
use Helio\Panel\Model\AbstractModel;

class ArrayUtility extends AbstractUtility
{
    /**
     * @param array      $dataBags
     * @param array      $possiblePaths
     * @param mixed|null $default
     *
     * @return mixed|mixed
     */
    public static function getFirstByDotNotation(array $dataBags, array $possiblePaths, $default = null)
    {
        foreach ($dataBags as $bag) {
            $dot = new Dot($bag);
            foreach ($possiblePaths as $path) {
                if ($dot->has($path)) {
                    return $dot->get($path);
                }
            }
        }

        return $default;
    }

    /**
     * @param array<AbstractModel> $models
     *
     * @return string
     */
    public static function modelsToStringOfIds(array $models): string
    {
        return array_reduce($models, function ($carry, $item) {
            /* @var AbstractModel $item */
            if ('' !== $carry) {
                $carry .= ',';
            }

            return $carry . $item->getId();
        }, '');
    }
}
