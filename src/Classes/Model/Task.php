<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

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
    protected $config;


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
     * @return string
     */
    public function getConfig(): string
    {
        return $this->config;
    }

    /**
     * @param string $config
     * @return Task
     */
    public function setConfig(string $config): Task
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }
}