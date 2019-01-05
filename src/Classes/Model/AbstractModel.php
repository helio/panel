<?php

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
use Helio\Panel\Utility\ServerUtility;

abstract class AbstractModel
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
     * @var \DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $created;


    /**
     * @var \DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $latestAction;

    /**
     * @var string
     *
     * @Column
     */
    protected $timezone = '';

    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $hidden = false;

    /**
     * @var int
     *
     * @Column(type="integer")
     */
    protected $status = 0;


    public function __construct()
    {
        $this->timezone = ServerUtility::getTimezoneObject()->getName();
    }

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
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new \DateTimeZone($this->getTimezone()));
        }
        return $this->created;
    }

    /**
     * @param \DateTime $created
     * @return $this
     */
    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getLatestAction(): \DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new \DateTimeZone($this->getTimezone()));
        }
        return $this->latestAction;
    }

    /**
     * @param \DateTime $latestAction
     * @return $this
     */
    public function setLatestAction(\DateTime $latestAction = null): self
    {
        if ($latestAction === null) {
            $latestAction = new \DateTime('now', $this->getTimezone());
        }

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $latestAction->setTimezone(new \DateTimeZone('UTC'));

        $this->latestAction = $latestAction;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @param bool $hidden
     * @return $this
     */
    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * @param string
     * @return $this
     */
    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }


    /**
     * @param int $status
     * @return $this
     */
    /** @noinspection ReturnTypeCanBeDeclaredInspection */
    abstract public function setStatus(int $status);
}
