<?php

namespace Helio\Panel\Utility;

class ViewUtility extends AbstractUtility
{
    public static $fileExtension = '.phtml';

    public static function includePartial(string $partialName, array $params = []): void
    {
        self::include(['panel', 'partial', $partialName . self::$fileExtension], $params);
    }

    public static function includeShared(string $sharedName, array $params = []): void
    {
        self::include(['shared', $sharedName . self::$fileExtension], $params);
    }

    private static function include(array $name, array $params = []): void
    {
        if ($params) {
            foreach ($params as $key => $value) {
                $$key = $value;
            }
        }

        $filename = ServerUtility::getTemplatesPath($name);
        if (\file_exists($filename)) {
            /** @noinspection PhpIncludeInspection */
            include $filename;
        }
    }
}
