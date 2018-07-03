<?php

namespace Helio\Test\Functional\Fixture\Model;


class Server extends \Helio\Panel\Model\Server
{


    /**
     * @param int $id
     * Allow setting the id
     */
    public function setId(int $id): void {
        $this->id = $id;
    }
}