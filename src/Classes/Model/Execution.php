<?php

/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Exception;
use DateTime;
use DateTimeZone;
use OpenApi\Annotations as OA;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Version;
use Helio\Panel\Execution\ExecutionStatus;

/**
 * @OA\Schema(
 *     type="object",
 *     title="Execution model"
 * )
 *
 * @Entity @Table(name="execution")
 **/
class Execution extends AbstractModel
{
    /**
     * @OA\Property(ref="#/components/schemas/executionstatus")
     *
     * @var int
     *
     * @Column(type="integer")
     */
    protected $status = ExecutionStatus::UNKNOWN;

    /**
     * @var int
     *
     * @Version @Column(type="integer")
     */
    private $version;

    /**
     * @var Job
     *
     * @ManyToOne(targetEntity="Job", inversedBy="executions", cascade={"persist"})
     */
    protected $job;

    /**
     * @OA\Property(
     *     description="The priority of the execution within each job. The lower, the more important.",
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
     * @var int Count of replicas the execution shall have. When in doubt, keep it null
     *
     * @Column(nullable=TRUE)
     */
    protected $replicas;

    /**
     * @OA\Property(
     *     description="Estimated Runtime on ideal Hardware in Seconds; 0 means the execution won't terminate itself.",
     *     type="integer",
     *     example="3600"
     * )
     *
     * @var int
     *
     * @Column
     */
    protected $estimatedRuntime = 0;

    /**
     * @OA\Property(
     *     description="Execution statistics. The content depends on the JobType.",
     *     type="string",
     *     example=""
     * )
     *
     * @var string
     *
     * @Column(type="text")
     */
    protected $stats = '';

    /**
     * @OA\Property(
     *     description="DateTime Object of when the worker started working on this execution.",
     *     type="string",
     *     format="date-time"
     * )
     *
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $started;

    /**
     * @OA\Property(
     *     description="DateTime Object of when the worker last reported operations.",
     *     type="string",
     *     format="date-time"
     * )
     *
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $latestHeartbeat;

    /**
     * @var bool
     *
     * @Column(type="boolean", nullable=TRUE)
     */
    protected $autoExecuted = false;

    public function getJob(): ?Job
    {
        return $this->job;
    }

    /**
     * @param Job $job
     *
     * @return Execution
     */
    public function setJob(Job $job): Execution
    {
        $add = true;
        /** @var Job $job */
        foreach ($job->getExecutions() as $execution) {
            if ($execution === $this || (null !== $this->getId() && $execution->getId() === $this->getId())) {
                $add = false;
            }
        }
        if ($add) {
            $job->addExecution($this);
        }
        $this->job = $job;

        return $this;
    }

    public function setStatus(int $status): self
    {
        if (ExecutionStatus::isValidStatus($status)) {
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
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     *
     * @return Execution
     */
    public function setPriority(int $priority): Execution
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return int
     */
    public function getReplicas(): ?int
    {
        return $this->replicas;
    }

    /**
     * @param int $replicas
     *
     * @return Execution
     */
    public function setReplicas(int $replicas): Execution
    {
        $this->replicas = $replicas;

        return $this;
    }

    /**
     * @return Execution
     */
    public function resetReplicas(): Execution
    {
        $this->replicas = null;

        return $this;
    }

    /**
     * @return int
     */
    public function getEstimatedRuntime(): int
    {
        return $this->estimatedRuntime;
    }

    /**
     * @param int $estimatedRuntime
     *
     * @return Execution
     */
    public function setEstimatedRuntime(int $estimatedRuntime): Execution
    {
        $this->estimatedRuntime = $estimatedRuntime;

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
     *
     * @return Execution
     */
    public function setStats(string $stats): Execution
    {
        $this->stats = $stats;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoExecuted(): bool
    {
        return $this->autoExecuted;
    }

    /**
     * @param  bool      $autoExecuted
     * @return Execution
     */
    public function setAutoExecuted(bool $autoExecuted): Execution
    {
        $this->autoExecuted = $autoExecuted;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getLatestHeartbeat(): ?DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new DateTimeZone($this->getTimezone()));
        }

        return $this->latestHeartbeat;
    }

    /**
     * @param DateTime|null $latestHeartbeat
     *
     * @return Execution
     *
     * @throws Exception
     */
    public function setLatestHeartbeat(DateTime $latestHeartbeat = null): self
    {
        if (null === $latestHeartbeat) {
            $latestHeartbeat = new DateTime('now', new DateTimeZone($this->getTimezone()));
        }

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $latestHeartbeat->setTimezone(new DateTimeZone('UTC'));

        $this->latestHeartbeat = $latestHeartbeat;

        return $this;
    }

    /**
     * @return Execution
     */
    public function resetLatestHeartbeat(): self
    {
        $this->latestHeartbeat = null;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getStarted(): ?DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new DateTimeZone($this->getTimezone()));
        }

        return $this->started;
    }

    /**
     * @param DateTime|null $started
     *
     * @return Execution
     *
     * @throws Exception
     */
    public function setStarted(DateTime $started = null): self
    {
        if (null === $started) {
            $started = new DateTime('now', new DateTimeZone($this->getTimezone()));
        }

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $started->setTimezone(new DateTimeZone('UTC'));

        $this->started = $started;

        return $this;
    }

    /**
     * @return Execution
     */
    public function resetStarted(): self
    {
        $this->started = null;

        return $this;
    }

    public function getServiceName(): string
    {
        $job = $this->getJob();

        return $job->getType() . '-' . $job->getId() . '-' . $this->getId();
    }
}
