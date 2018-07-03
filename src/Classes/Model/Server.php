<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\{
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToMany
};

/**
 * @Entity @Table(name="server")
 **/
class Server
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
    protected $fqdn = '';


    /**
     * @var boolean
     *
     * @Column(type="boolean")
     */
    protected $active = false;


    /**
     * @var \DateTime
     *
     * @Column(type="datetime", nullable=TRUE)
     */
    protected $created;


    /**
     * @var \DateTime
     *
     * @Column(type="datetime", nullable=TRUE)
     */
    protected $latestAction;


    /**
     * @var User
     *
     * @ManyToOne(targetEntity="User", inversedBy="servers", cascade={"persist"})
     */
    protected $owner;


    /**
     * @return int
     */
    public function getId(): ?int
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
     * @return Server
     */
    public function setName(string $name): Server
    {
        $this->name = $name;

        return $this;
    }


    /**
     * @return string
     */
    public function getFqdn(): string
    {
        return $this->fqdn;
    }


    /**
     * @param string $fqdn
     * @return Server
     */
    public function setFqdn(string $fqdn): Server
    {
        $this->fqdn = $fqdn;

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
     * @return Server
     */
    public function setActive(bool $active): Server
    {
        $this->active = $active;

        return $this;
    }


    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }


    /**
     * @param \DateTime $created
     * @return Server
     */
    public function setCreated(\DateTime $created): Server
    {
        $this->created = $created;

        return $this;
    }


    /**
     * @return \DateTime
     */
    public function getLatestAction(): \DateTime
    {
        return $this->latestAction;
    }


    /**
     * @param \DateTime $latestAction
     * @return Server
     */
    public function setLatestAction(\DateTime $latestAction): Server
    {
        $this->latestAction = $latestAction;

        return $this;
    }


    /**
     * @return User
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return Server
     */
    public function setOwner(User $owner): Server
    {
        $add = true;
        /** @var Server $server */
        foreach ($owner->getServers() as $server) {
            if ($server->getId() === $this->getId()) {
                $add = false;
            }
        }
        if ($add) {
            $owner->addServer($this);
        }
        $this->owner = $owner;

        return $this;
    }
}