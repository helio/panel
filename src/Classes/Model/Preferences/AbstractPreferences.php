<?php

namespace Helio\Panel\Model\Preferences;

abstract class AbstractPreferences
{
    /** @var int */
    protected $flags;

    public function __construct(int $flags)
    {
        $this->flags = $flags;
    }

    public function getIntegerValue(): int
    {
        return $this->flags;
    }

    public function isFlagSet($flag)
    {
        return ($this->flags & $flag) == $flag;
    }

    public function toggleFlag($flag)
    {
        $this->setFlag($flag, !$this->isFlagSet($flag));
    }

    public function setFlag($flag, $value)
    {
        if ($value) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }
}
