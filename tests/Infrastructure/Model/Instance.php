<?php

namespace Helio\Test\Infrastructure\Model;


class Instance extends \Helio\Panel\Model\Instance
{


    /**
     * @param int $id
     * @return Instance $this
     * Allow setting the id
     */
    public function setId(int $id): Instance {
        $this->id = $id;
        return $this;
    }
}