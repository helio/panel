<?php

namespace Helio\Panel\Response;

class AbstractResponse implements \JsonSerializable
{
    /**
     * Returns all accessible properties of that object to serialize to JSON.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Returns all accessible properties of that object.
     */
    public function toArray()
    {
        return \get_object_vars($this);
    }
}
