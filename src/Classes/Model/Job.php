<?php

/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\Common\Collections\Criteria;
use Exception;
use OpenApi\Annotations as OA;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Execution\ExecutionStatus;

/**
 * @OA\Schema(
 *     type="object",
 *     title="Job model"
 * )
 *
 * @Entity(repositoryClass="Helio\Panel\Repositories\JobRepository")
 * @Table(name="job")
 **/
class Job extends AbstractModel
{
    /**
     * @OA\Property(
     *     description="Job type specific configuration",
     *     oneOf={
     *         @OA\Schema(ref="#/components/schemas/docker"),
     *         @OA\Schema(ref="#/components/schemas/gitlab"),
     *         @OA\Schema(ref="#/components/schemas/blender")
     *     },
     *     @OA\Discriminator(
     *         propertyName="type"
     *     ),
     *     example={"image":"nginx:1.8","env":{"SOURCE_PATH":"https://account-name.zone-name.web.core.windows.net","TARGET_PATH":"https://bucket.s3.aws-region.amazonaws.com"},"registry":{"server":"example.azurecr.io","username":"$DOCKER_USER","password":"$DOCKER_PASSWORD","email":"docker@example.com"},"cliparams":{"env":{"SECRET_SOURCE":"https://my.vault.example:42/"}}}
     * )
     *
     * @var object
     *
     * @Column(type="text")
     */
    protected $config = '';

    /**
     * @var int
     *
     * @Column(type="integer")
     */
    protected $status = JobStatus::UNKNOWN;

    /**
     * @OA\Property(ref="#/components/schemas/jobtype")
     *
     * @var string
     *
     * @Column
     */
    protected $type = JobType::UNKNOWN;

    /**
     * @OA\Property(
     *     description="Let us know if you won't pay anything for your job (e.g. you're an Open Source project)",
     *     type="boolean"
     * )
     *
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $isCharity = false;

    /**
     * @OA\Property(
     *     description="Specify how much CPUs this job ideally gets",
     *     type="number"
     * ),
     *
     * @var int
     *
     * @Column
     */
    protected $cpus = 0;

    /**
     * @OA\Property(
     *     description="Specify how much GPUs this job ideally gets",
     *     type="number"
     * ),
     *
     * @var int
     *
     * @Column
     */
    protected $gpus = 0;

    /**
     * @OA\Property(
     *     description="Specify in which location this job should run",
     *     type="string",
     *     example="europe-west:switzerland-zurich"
     * ),
     *
     * @var string
     *
     * @Column
     */
    protected $location = '';

    /**
     * @OA\Property(
     *     description="A billing reference (e.g. your customer's order number)",
     *     type="customer-project-1502-0B"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $billingReference = '';

    /**
     * @OA\Property(
     *     description="We terminate jobs automatically once they have reached the maximum budget set here",
     *     type="number",
     *     example="10000"
     * )
     *
     * @var int
     *
     * @Column
     */
    protected $budget = 0;

    /**
     * @var float
     *
     * @Column
     */
    protected $budgetUsed = 0.0;

    /**
     * @OA\Property(
     *     description="Cron Schedule to automatically execute the Job",
     *     type="string",
     *     example="30 6 * * *"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $autoExecSchedule = '';

    /**
     * @OA\Property(
     *     description="Set this if you want to keep this job always ready, even after long idle time. Will affect costs.",
     *     type="boolean",
     *     default=false
     * )
     *
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $persistent = false;

    /**
     * @OA\Property(
     *     description="Priority. The lower, the more urgent the execution.",
     *     type="integer",
     *     example="100"
     * )
     *
     * @var int
     *
     * @Column
     */
    protected $priority = 100;

    /**
     * @var User
     *
     * @ManyToOne(targetEntity="User", inversedBy="jobs", cascade={"persist"})
     */
    protected $owner;

    /**
     * @var array<Execution>
     *
     * @OneToMany(targetEntity="Execution", mappedBy="job", cascade={"persist"})
     */
    protected $executions = [];

    /**
     * @var Manager
     *
     * @ManyToOne(targetEntity="Manager", inversedBy="jobs", cascade={"persist"})
     */
    protected $manager;

    /**
     * @var int
     *
     * @internal
     */
    private $numberOfActiveExecutions;

    /**
     * @var string[]
     *
     * @Column(type="simple_array", nullable=TRUE)
     */
    protected $labels = [];

    /**
     * Job constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->executions = new ArrayCollection();
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
     *
     * @return Job
     */
    public function setOwner(User $owner): Job
    {
        $add = true;
        /** @var Job $job */
        foreach ($owner->getJobs() as $job) {
            if ($job === $this || ($this->getId() && $job->getId() === $this->getId())) {
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
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
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
     *
     * @return Job
     */
    public function setIsCharity(bool $isCharity): Job
    {
        $this->isCharity = $isCharity;

        return $this;
    }

    public function setStatus(int $status): Job
    {
        if (JobStatus::isValidStatus($status)) {
            $this->status = $status;
        }

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getCpus(): int
    {
        return $this->cpus;
    }

    /**
     * @param int $cpus
     *
     * @return Job
     */
    public function setCpus(int $cpus): Job
    {
        $this->cpus = $cpus;

        return $this;
    }

    /**
     * @return int
     */
    public function getGpus(): int
    {
        return $this->gpus;
    }

    /**
     * @param int $gpus
     *
     * @return Job
     */
    public function setGpus(int $gpus): Job
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
     *
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
     *
     * @return Job
     */
    public function setBillingReference(string $billingReference): Job
    {
        $this->billingReference = $billingReference;

        return $this;
    }

    /**
     * @return int
     */
    public function getBudget(): int
    {
        return $this->budget;
    }

    /**
     * @param int $budget
     *
     * @return Job
     */
    public function setBudget(int $budget): Job
    {
        $this->budget = $budget;

        return $this;
    }

    /**
     * @return float
     */
    public function getBudgetUsed(): float
    {
        return (float) $this->budgetUsed;
    }

    /**
     * @param float $budgetUsed
     *
     * @return Job
     */
    public function setBudgetUsed(float $budgetUsed): Job
    {
        $this->budgetUsed = $budgetUsed;

        return $this;
    }

    /**
     * @return string
     */
    public function getAutoExecSchedule(): string
    {
        return $this->autoExecSchedule;
    }

    /**
     * @param string $autoExecSchedule
     *
     * @return Job
     */
    public function setAutoExecSchedule(string $autoExecSchedule): Job
    {
        $this->autoExecSchedule = $autoExecSchedule;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * @param  bool $persistent
     * @return Job
     */
    public function setPersistent(bool $persistent): Job
    {
        $this->persistent = $persistent;

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
     *
     * @return Job
     */
    public function setPriority(int $priority): Job
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @param Execution $execution
     *
     * @return Job
     */
    public function addExecution(Execution $execution): Job
    {
        $this->executions[] = $execution;
        if (null === $execution->getJob()) {
            $execution->setJob($this);
        }

        $this->numberOfActiveExecutions = null;

        return $this;
    }

    /**
     * @param Execution $executionToRemove
     *
     * @return Job
     */
    public function removeExecution(Execution $executionToRemove): Job
    {
        $this->setExecutions(array_filter($this->getExecutions()->toArray(), function ($execution) use ($executionToRemove) {
            /* @var Execution $execution */
            return $execution->getId() !== $executionToRemove->getId();
        }));
        $this->numberOfActiveExecutions = null;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getExecutions(): Collection
    {
        return $this->executions;
    }

    /**
     * @param Manager|null $manager
     *
     * @return Job
     */
    public function setManager(Manager $manager = null): Job
    {
        $this->manager = $manager;
        if (null === $manager) {
            return $this;
        }

        $add = true;
        foreach ($manager->getJobs() as $job) {
            if ($job === $this || ($this->getId() && $job->getId() === $this->getId())) {
                $add = false;
            }
        }

        if ($add) {
            $manager->addJob($this);
        }

        return $this;
    }

    /**
     * @return Manager|null
     */
    public function getManager(): ?Manager
    {
        return $this->manager;
    }

    public function getReadyAndRunningExecutionsCount(): int
    {
        $criteria = Criteria::create()->where(Criteria::expr()->in('status', ExecutionStatus::getRunningStatusCodes()));

        return $this->executions->matching($criteria)->count();
    }

    public function getStartedExecutionsCount(): int
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->in('status', ExecutionStatus::getRunningStatusCodes()))
            ->andWhere(Criteria::expr()->eq('replicas', 1));

        return $this->executions->matching($criteria)->count();
    }

    /**
     * @param array $executions
     *
     * @return Job
     */
    public function setExecutions(array $executions): Job
    {
        $this->executions = $executions;
        $this->numberOfActiveExecutions = null;

        return $this;
    }

    /**
     * @return int
     */
    public function getActiveExecutionCount(): int
    {
        return count(array_filter($this->getExecutions()->toArray(), function (Execution $execution) {
            return ExecutionStatus::isValidPendingStatus($execution->getStatus()) || ExecutionStatus::isRunning($execution->getStatus());
        }));
    }

    /**
     * @param string $label
     *
     * @return Job
     */
    public function addLabel(string $label): self
    {
        if (!in_array($label, $this->labels, true)) {
            $this->labels[] = $label;
        }

        return $this;
    }

    /**
     * @param string $labelToRemove
     *
     * @return Job
     */
    public function removeLabel(string $labelToRemove): self
    {
        $this->setLabels(array_filter($this->getLabels(), function ($label) use ($labelToRemove) {
            if (trim($label) === trim($labelToRemove)) {
                return false;
            }

            return true;
        }));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @param  string[] $labels
     * @return Job
     */
    public function setLabels(array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }
}
