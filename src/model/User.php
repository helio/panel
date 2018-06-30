<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\{
    Common\Collections\Collection,
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToMany
};

use Doctrine\Common\Collections\ArrayCollection;
use Helio\Panel\Helper\JwtHelper;

/**
 * @Entity @Table(name="user")
 **/
class User
{


    /**
     * @var int
     *
     * @Id @Column(type="integer") @GeneratedValue
     */
    protected $id;


    /**
     * @var string
     *
     * @Column
     */
    protected $name = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $email = '';


    /**
     * @var boolean
     *
     * @Column(type="boolean")
     */
    protected $active = false;


    /**
     * @var array<Server>
     *
     * @OneToMany(targetEntity="Server", mappedBy="owner", cascade={"persist"})
     */
    protected $servers = [];


    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->servers = new ArrayCollection();
    }


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return User
     */
    public function setName(string $name): User
    {
        $this->name = $name;

        return $this;
    }


    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }


    /**
     * @param string $email
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;

        return $this;
    }


    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }


    /**
     * @param bool $active
     * @return User
     */
    public function setActive(bool $active): User
    {
        $this->active = $active;

        return $this;
    }


    /**
     * @return Collection
     */
    public function getServers(): Collection
    {
        return $this->servers;
    }


    /**
     * @param array<Server> $servers
     * @return User
     */
    public function setServers(array $servers): User
    {
        $this->servers = $servers;

        /** @var Server $server
         * foreach ($servers as $server) {
         * $server->setOwner($this);
         * }
         */

        return $this;
    }


    /**
     * @param Server $server
     *
     * @return User
     */
    public function addServer(Server $server): User
    {
        $this->servers[] = $server;
        if (null === $server->getOwner()) {
            $server->setOwner($this);
        }

        return $this;
    }


    /**
     * @return string
     */
    public function hashedId(): string
    {
        return substr(md5($this->getId() . JwtHelper::getSecret()), 0, 6);
    }
}