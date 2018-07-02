<?php

namespace Helio\Test\Functional\Fixture;

use \Helio\Panel\Model\User as UserModel;

class User extends UserModel
{


    /**
     * @param int $id
     * Allow setting the id
     */
    public function setId(int $id): void {
        $this->id = $id;
    }
}