<?php

namespace Helio\Test\Infrastructure\Model;


use Helio\Panel\Utility\ServerUtility;

class Instance extends \Helio\Panel\Model\Instance
{


    /**
     * @param int $id
     * @return Instance $this
     * Allow setting the id
     */
    public function setId(int $id): Instance
    {
        $this->id = $id;
        return $this;
    }

    public function setCreatedByTimestamp(int $timestamp)
    {
        $this->created = (new \DateTime('now', ServerUtility::getTimezoneObject()))->setTimestamp($timestamp);
        return $this;

    }
}