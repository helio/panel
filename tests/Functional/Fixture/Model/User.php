<?php

namespace Helio\Test\Functional\Fixture\Model;


class User extends \Helio\Panel\Model\User
{


    /**
     * @param int $id
     *
     * Allow setting the id
     *
     * @return User
     */
    public function setId(int $id): User
    {
        $this->id = $id;

        return $this;
    }
}