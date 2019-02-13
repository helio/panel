<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\{Common\Collections\ArrayCollection,
    Common\Collections\Collection,
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToOne,
    ORM\Mapping\OneToMany};

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Task\TaskStatus;

/**
 * @Entity @Table(name="job")
 **/
class Job extends AbstractModel
{


    /**
     * @var User
     *
     * @ManyToOne(targetEntity="User", inversedBy="jobs", cascade={"persist"})
     */
    protected $owner;


    /**
     * @var string
     *
     * @Column
     */
    protected $token = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $type = JobType::UNKNOWN;


    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $isCharity = false;


    /**
     * @var string
     *
     * @Column
     */
    protected $cpus = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $gpus = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $location = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $billingReference = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $budget = '';


    /**
     * @var array<Task>
     *
     * @OneToMany(targetEntity="Task", mappedBy="job", cascade={"persist"})
     */
    protected $tasks = [];


    /**
     * @var int
     *
     * @Column
     */
    protected $priority = 100;

    /**
     * @var Instance
     *
     * @ManyToOne(targetEntity="Instance", inversedBy="tasks", cascade={"persist"})
     */
    protected $dispatchedInstance;

    private $numberOfActiveTasks;

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->tasks = new ArrayCollection();
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
     * @return Job
     */
    public function setOwner(User $owner): Job
    {
        $add = true;
        /** @var Job $job */
        foreach ($owner->getJobs() as $job) {
            if ($job->getId() === $this->getId()) {
                $add = false;
            }
        }
        if ($add) {
            $owner->addJob($this);
        }
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return Job
     */
    public function setToken(string $token): Job
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return Job
     */
    public function setType(string $type): Job
    {
        if (JobType::isValidType($type)) {
            $this->type = $type;
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isCharity(): bool
    {
        return $this->isCharity;
    }

    /**
     * @param bool $isCharity
     * @return Job
     */
    public function setIsCharity(bool $isCharity): Job
    {
        $this->isCharity = $isCharity;
        return $this;
    }

    /**
     * @param int $status
     * @return Job
     */
    public function setStatus(int $status): Job
    {
        if (JobStatus::isValidStatus($status)) {
            $this->status = $status;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getCpus(): string
    {
        return $this->cpus;
    }

    /**
     * @param string $cpus
     * @return Job
     */
    public function setCpus(string $cpus): Job
    {
        $this->cpus = $cpus;
        return $this;
    }

    /**
     * @return string
     */
    public function getGpus(): string
    {
        return $this->gpus;
    }

    /**
     * @param string $gpus
     * @return Job
     */
    public function setGpus(string $gpus): Job
    {
        $this->gpus = $gpus;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @param string $location
     * @return Job
     */
    public function setLocation(string $location): Job
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @return string
     */
    public function getBillingReference(): string
    {
        return $this->billingReference;
    }

    /**
     * @param string $billingReference
     * @return Job
     */
    public function setBillingReference(string $billingReference): Job
    {
        $this->billingReference = $billingReference;
        return $this;
    }

    /**
     * @return string
     */
    public function getBudget(): string
    {
        return $this->budget;
    }

    /**
     * @param string $budget
     * @return Job
     */
    public function setBudget(string $budget): Job
    {
        $this->budget = $budget;
        return $this;
    }


    /**
     * @return Collection
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /**
     * @param array $tasks
     * @return Job
     */
    public function setTasks(array $tasks): Job
    {
        $this->tasks = $tasks;
        $this->numberOfActiveTasks = null;
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
     * @return Job
     */
    public function setPriority(int $priority): Job
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return null|Instance
     */
    public function getDispatchedInstance(): ?Instance
    {
        return $this->dispatchedInstance;
    }

    /**
     * @param Instance $dispatchedInstance
     * @return Job
     */
    public function setDispatchedInstance(Instance $dispatchedInstance): Job
    {
        $this->dispatchedInstance = $dispatchedInstance;
        return $this;
    }

    /**
     * @param Task $task
     *
     * @return Job
     */
    public function addTask(Task $task): Job
    {
        $this->tasks[] = $task;
        if (null === $task->getJob()) {
            $task->setJob($this);
        }

        $this->numberOfActiveTasks = null;

        return $this;
    }


    /**
     * @param Task $taskToRemove
     *
     * @return Job
     */
    public function removeTask(Task $taskToRemove): Job
    {
        $this->setTasks(array_filter($this->getTasks()->toArray(), function ($task) use ($taskToRemove) {
            /** @var Task $task */
            return $task->getId() !== $taskToRemove->getId();
        }));
        $this->numberOfActiveTasks = null;

        return $this;
    }


    /**
     * @return int
     */
    public function getActiveTaskCount(): int
    {
        return $this->numberOfActiveTasks ?? $this->numberOfActiveTasks = \count(array_filter($this->getTasks()->toArray(), function (Task $task) {
                return TaskStatus::isValidPendingStatus($task->getStatus());
            }));
    }
}