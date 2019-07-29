<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use \Exception;
use \DateTime;
use \DateTimeZone;

use Doctrine\{Common\Collections\Collection,
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToMany,
    ORM\Mapping\Version
};

use Doctrine\Common\Collections\ArrayCollection;
use Helio\Panel\Task\TaskStatus;

/**
 * @Entity @Table(name="task")
 **/
class Task extends AbstractModel
{


    /**
     * @var int
     *
     * @Version @Column(type="integer")
     */
    private $version;


    /**
     * @var Job
     *
     * @ManyToOne(targetEntity="Job", inversedBy="tasks", cascade={"persist"})
     */
    protected $job;


    /**
     * @var int
     *
     * @Column
     */
    protected $priority = 100;


    /**
     * @var string
     *
     * @Column(type="text")
     */
    protected $stats = '';


    /**
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $latestHeartbeat;


    /**
     * @return Job
     */
    public function getJob(): ?Job
    {
        return $this->job;
    }

    /**
     * @param Job $job
     * @return Task
     */
    public function setJob(Job $job): Task
    {
        $add = true;
        /** @var Job $job */
        foreach ($job->getTasks() as $task) {
            if ($task->getId() === $this->getId()) {
                $add = false;
            }
        }
        if ($add) {
            $job->addTask($this);
        }
        $this->job = $job;
        return $this;
    }

    /**
     * @param int $status
     * @return $this|AbstractModel
     */
    public function setStatus(int $status)
    {
        if (TaskStatus::isValidStatus($status)) {
            $this->status = $status;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return Task
     */
    public function setPriority(int $priority): Task
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version ?? 0;
    }

    /**
     * @return string
     */
    public function getStats(): string
    {
        return $this->stats;
    }

    /**
     * @param string $stats
     * @return Task
     */
    public function setStats(string $stats): Task
    {
        $this->stats = $stats;
        return $this;
    }


    /**
     * @return DateTime
     */
    public function getLatestHeartbeat(): DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new DateTimeZone($this->getTimezone()));
        }
        return $this->latestHeartbeat;
    }

    /**
     * @param DateTime|null $latestHeartbeat
     * @return Task
     * @throws Exception
     */
    public function setLatestHeartbeat(DateTime $latestHeartbeat = null): self
    {
        if ($latestHeartbeat === null) {
            $latestHeartbeat = new DateTime('now', new DateTimeZone($this->getTimezone()));
        }

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $latestHeartbeat->setTimezone(new DateTimeZone('UTC'));

        $this->latestHeartbeat = $latestHeartbeat;
        return $this;
    }
}