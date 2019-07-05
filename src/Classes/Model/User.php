<?php
/** @noinspection PhpUnusedAliasInspection */

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

use Doctrine\Common\Collections\ArrayCollection;
use Helio\Panel\Utility\ArrayUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;

/**
 * @Entity @Table(name="user")
 **/
class User extends AbstractModel
{


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
    protected $token = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $role = '';


    /**
     * @var boolean
     *
     * @Column(type="boolean")
     */
    protected $active = false;


    /**
     * @var boolean
     *
     * @Column(type="boolean")
     */
    protected $admin = false;


    /**
     * @var \DateTime $loggedOut
     *
     * @Column(type="datetimetz", nullable=true)
     */
    protected $loggedOut;


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
     * User constructor.
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
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return User
     */
    public function setToken(string $token): User
    {
        $this->token = $token;
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
     * @return User
     */
    public function setAdmin(bool $admin): User
    {
        $this->admin = $admin;

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
            /** @var Instance $instance */
            return $instance->getId() !== $instanceToRemove->getId();
        }));

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getLoggedOut(): ?\DateTime
    {
        return $this->loggedOut;
    }

    /**
     * @param \DateTime|null $loggedOut
     * @throws \Exception
     */
    public function setLoggedOut(\DateTime $loggedOut = null): void
    {
        if (!$loggedOut) {
            $loggedOut = new \DateTime('now', ServerUtility::getTimezoneObject());
        }
        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $loggedOut->setTimezone(new \DateTimeZone('UTC'));

        $this->loggedOut = $loggedOut;
    }


    /**
     * @return Collection
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    /**
     * @param array $jobs
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
            /** @var Job $job */
            return $job->getId() !== $jobToRemove->getId();
        }));

        return $this;
    }

    /**
     * @param int $status
     * @return User
     */
    public function setStatus(int $status): User
    {

        $this->status = $status;
        return $this;
    }
}