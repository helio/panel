<?php

namespace Helio\Panel\Orchestrator;

final class OrchestratorType
{
    public const CHORIA = 'choria';

    public const __DEFAULT = self::CHORIA;

    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return self::CHORIA === $type;
    }
}
