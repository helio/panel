<?php

namespace Helio\Test\Infrastructure\Model;


class Server extends \Helio\Panel\Model\Server
{


    /**
     * @param int $id
     * @return Server $this
     * Allow setting the id
     */
    public function setId(int $id): Server {
        $this->id = $id;
        return $this;
    }
}