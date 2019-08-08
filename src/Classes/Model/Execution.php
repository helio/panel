<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use \Exception;
use \DateTime;
use \DateTimeZone;

use OpenApi\Annotations as OA;
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
use Helio\Panel\Execution\ExecutionStatus;

/**
 *
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
     *     format="integer"
     * )
     *
     * @var int
     *
     * @Column
     */
    protected $priority = 100;


    /**
     *
     * @OA\Property(
     *     description="Execution statistics. The content depends on the JobType.",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column(type="text")
     */
    protected $stats = '';


    /**
     * @OA\Property(
     *     description="DateTime Object of when the worker last reported operations.",
     *     format="string"
     * )
     *
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
     * @return Execution
     */
    public function setJob(Job $job): Execution
    {
        $add = true;
        /** @var Job $job */
        foreach ($job->getExecutions() as $execution) {
            if ($execution->getId() === $this->getId()) {
                $add = false;
            }
        }
        if ($add) {
            $job->addExecution($this);
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
        if (ExecutionStatus::isValidStatus($status)) {
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
     * @return Execution
     */
    public function setStats(string $stats): Execution
    {
        $this->stats = $stats;
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
     * @return Execution
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