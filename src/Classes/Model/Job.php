<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use \Exception;
use OpenApi\Annotations as OA;
use Doctrine\{
    Common\Collections\Collection,
    Common\Collections\ArrayCollection,
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToMany
};
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Execution\ExecutionStatus;

/**
 *
 * @OA\Schema(
 *     type="object",
 *     title="Job model"
 * )
 *
 * @Entity @Table(name="job")
 **/
class Job extends AbstractModel
{


    /**
     * @OA\Property(
     *         format="object",
     *         description=">- Your Job specific JSON config looking like this:

            {
                ""container"": ""nginx:1.8"",
                ""env"": [
                    {""SOURCE_PATH"":""https://account-name.zone-name.web.core.windows.net""},
                    {""TARGET_PATH"":""https://bucket.s3.aws-region.amazonaws.com""}
                ],
                ""registry"": {
                    ""server"": ""example.azurecr.io"",
                    ""username"": ""$DOCKER_USER"",
                    ""password"": ""$DOCKER_PASSWORD"",
                    ""email"": ""docker@example.com""
                },
     *         ""cliparams"": {
     *             ""env"": [
     *                 {""SECRET_SOURCE"":""https://my.vautl:42/""}
     *             ]
     *          }
     *     }",
     * )
     *
     * @var string
     *
     * @Column(type="text")
     */
    protected $config = '';

    /**
     * @OA\Property(ref="#/components/schemas/jobstatus")
     *
     * @var string
     *
     * @Column
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
     *     format="boolean"
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
     *     format="number"
     * ),
     *
     * @var string
     *
     * @Column
     */
    protected $cpus = '';


    /**
     * @OA\Property(
     *     description="Specify how much GPUs this job ideally gets",
     *     format="number"
     * ),
     *
     * @var string
     *
     * @Column
     */
    protected $gpus = '';


    /**
     * @OA\Property(
     *     description="Specify in which location this job should run",
     *     format="string"
     * ),
     *
     * @var string
     *
     * @Column
     */
    protected $location = '';


    /**
     * @OA\Property(
     *     description="A billing reference (e.g. your customer's order number)"
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
     *     format="number"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $budget = '';

    /**
     * @OA\Property(
     *     description="Cron Schedule to automatically execute the Job",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $autoExecSchedule = '';


    /**
     * @OA\Property(
     *     description="Priority. The lower, the more urgent the execution.",
     *     format="integer"
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
     * @var string
     *
     * @Column
     */
    protected $initManagerIp = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $clusterToken = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $managerToken = '';


    /**
     * @var array<string>
     *
     * @Column(type="simple_array", nullable=TRUE)
     */
    protected $managerNodes = [];


    private $numberOfActiveExecutions;

    /**
     * Job constructor.
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
     * @return string
     */
    public function getAutoExecSchedule(): string
    {
        return $this->autoExecSchedule;
    }

    /**
     * @param string $autoExecSchedule
     * @return Job
     */
    public function setAutoExecSchedule(string $autoExecSchedule): Job
    {
        $this->autoExecSchedule = $autoExecSchedule;
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
            /** @var Execution $execution */
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
     * @param array $executions
     * @return Job
     */
    public function setExecutions(array $executions): Job
    {
        $this->executions = $executions;
        $this->numberOfActiveExecutions = null;
        return $this;
    }

    /**
     * @param string $managerNode
     * @return Job
     */
    public function addManagerNode(string $managerNode): Job
    {
        if (!in_array($managerNode, $this->managerNodes, true)) {
            $this->managerNodes[] = $managerNode;
        }
        return $this;
    }

    /**
     * @param string $nodeToRemove
     * @return Job
     */
    public function removeManagerNode(string $nodeToRemove): Job
    {
        $this->setManagerNodes(array_filter($this->getManagerNodes(), function ($node) use ($nodeToRemove) {
            if (trim($node) === trim($nodeToRemove)) {
                return false;
            }
            if (strpos(trim($node), trim($nodeToRemove . '.')) === 0) {
                return false;
            }
            return true;
        }));

        return $this;
    }

    /**
     * @return array
     */
    public function getManagerNodes(): array
    {
        return $this->managerNodes;
    }

    /**
     * @param array $managerNodes
     * @return Job
     */
    public function setManagerNodes(array $managerNodes): Job
    {
        $this->managerNodes = $managerNodes;
        return $this;
    }

    /**
     * @return string
     */
    public function getInitManagerIp(): string
    {
        return $this->initManagerIp;
    }

    /**
     * @param string $initManagerIp
     * @return Job
     */
    public function setInitManagerIp(string $initManagerIp): Job
    {
        $this->initManagerIp = $initManagerIp;
        return $this;
    }

    /**
     * @return string
     */
    public function getClusterToken(): string
    {
        return $this->clusterToken;
    }

    /**
     * @param string $clusterToken
     * @return Job
     */
    public function setClusterToken(string $clusterToken): Job
    {
        $this->clusterToken = $clusterToken;
        return $this;
    }

    /**
     * @return string
     */
    public function getManagerToken(): string
    {
        return $this->managerToken;
    }

    /**
     * @param string $managerToken
     * @return Job
     */
    public function setManagerToken(string $managerToken): Job
    {
        $this->managerToken = $managerToken;
        return $this;
    }


    /**
     * @return int
     */
    public function getActiveExecutionCount(): int
    {
        return count(array_filter($this->getExecutions()->toArray(), function (Execution $execution) {
            return ExecutionStatus::isValidPendingStatus($execution->getStatus());
        }));
    }
}