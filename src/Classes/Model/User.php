<?php

/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\Common\Collections\Criteria;
use Exception;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\Common\Collections\ArrayCollection;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Preferences\UserPreferences;
use Helio\Panel\Utility\ArrayUtility;
use Helio\Panel\Utility\ServerUtility;

/**
 * @Entity @Table(name="user")
 **/
class User extends AbstractModel implements \JsonSerializable
{
    /**
     * @var int
     *
     * @Column(type="integer")
     */
    protected $status = 0;

    /**
     * @var string
     *
     * @Column
     */
    protected $email = '';

    /**
     * @var string
     *
     * @Column
     */
    protected $role = '';

    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $active = false;

    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $admin = false;

    /**
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=true)
     */
    protected $loggedOut;

    /**
     * @var array
     *
     * @Column(type="json", nullable=true)
     */
    protected $preferences = [];

    /**
     * @var array<Instance>
     *
     * @OneToMany(targetEntity="Instance", mappedBy="owner", cascade={"persist"})
     */
    protected $instances = [];

    /**
     * @var array<Job>
     *
     * @OneToMany(targetEntity="Job", mappedBy="owner", cascade={"persist"})
     */
    protected $jobs = [];

    /**
     * @var string
     *
     * @Column
     */
    protected $origin = '';

    /**
     * User constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->instances = new ArrayCollection();
        $this->jobs = new ArrayCollection();
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
     *
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param string $role
     *
     * @return User
     */
    public function setRole(string $role): User
    {
        $this->role = $role;

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
     *
     * @return User
     */
    public function setActive(bool $active): User
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * @param bool $admin
     *
     * @return User
     */
    public function setAdmin(bool $admin): User
    {
        $this->admin = $admin;

        return $this;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    /**
     * @param array<Instance> $instances
     *
     * @return User
     */
    public function setInstances(array $instances): User
    {
        $this->instances = $instances;

        return $this;
    }

    /**
     * @param Instance $instance
     *
     * @return User
     */
    public function addInstance(Instance $instance): User
    {
        $this->instances[] = $instance;
        if (null === $instance->getOwner()) {
            $instance->setOwner($this);
        }

        return $this;
    }

    /**
     * @param Instance $instanceToRemove
     *
     * @return User
     */
    public function removeInstance(Instance $instanceToRemove): User
    {
        $this->setInstances(array_filter($this->getInstances()->toArray(), function ($instance) use ($instanceToRemove) {
            /* @var Instance $instance */
            return $instance->getId() !== $instanceToRemove->getId();
        }));

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getLoggedOut(): ?DateTime
    {
        return $this->loggedOut;
    }

    /**
     * @param DateTime|null $loggedOut
     *
     * @throws Exception
     */
    public function setLoggedOut(DateTime $loggedOut = null): void
    {
        if (!$loggedOut) {
            $loggedOut = new DateTime('now', ServerUtility::getTimezoneObject());
        }
        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $loggedOut->setTimezone(new DateTimeZone('UTC'));

        $this->loggedOut = $loggedOut;
    }

    public function getPreferences(): UserPreferences
    {
        return new UserPreferences($this->preferences ?? []);
    }

    public function setPreferences(UserPreferences $preferences): self
    {
        $this->preferences = $preferences->jsonSerialize();

        return $this;
    }

    public function getPreference(string $preference)
    {
        return ArrayUtility::getFirstByDotNotation([$this->getPreferences()->jsonSerialize()], [$preference]);
    }

    /**
     * @return Collection
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function getRunningJobsCount(): int
    {
        $criteria = Criteria::create()
            ->where(
                Criteria::expr()->in('status', JobStatus::getRunningStatusCodes())
            );

        return $this->jobs
            ->matching($criteria)
            ->count();
    }

    /**
     * @param array $jobs
     *
     * @return User
     */
    public function setJobs(array $jobs): User
    {
        $this->jobs = $jobs;

        return $this;
    }

    /**
     * @param Job $job
     *
     * @return User
     */
    public function addJob(Job $job): User
    {
        $this->jobs[] = $job;
        if (null === $job->getOwner()) {
            $job->setOwner($this);
        }

        return $this;
    }

    /**
     * @param Job $jobToRemove
     *
     * @return User
     */
    public function removeJob(Job $jobToRemove): User
    {
        $this->setJobs(array_filter($this->getJobs()->toArray(), function ($job) use ($jobToRemove) {
            /* @var Job $job */
            return $job->getId() !== $jobToRemove->getId();
        }));

        return $this;
    }

    public function setStatus(int $status): User
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Specify data which should be serialized to JSON.
     * @see https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
        ];
    }
}
