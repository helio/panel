<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\{
    ORM\Mapping\Entity,
    ORM\Mapping\Table,
    ORM\Mapping\Id,
    ORM\Mapping\Column,
    ORM\Mapping\GeneratedValue,
    ORM\Mapping\ManyToOne,
    ORM\Mapping\OneToMany
};

use Helio\Panel\Job\JobType;
use Helio\Panel\Job\JobStatus;

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
    protected $gitlabEndpoint = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $gitlabToken = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $gitlabTags = '';


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
    public function getGitlabEndpoint(): string
    {
        return $this->gitlabEndpoint;
    }

    /**
     * @param string $gitlabEndpoint
     * @return Job
     */
    public function setGitlabEndpoint(string $gitlabEndpoint): Job
    {
        $this->gitlabEndpoint = $gitlabEndpoint;
        return $this;
    }

    /**
     * @return string
     */
    public function getGitlabToken(): string
    {
        return $this->gitlabToken;
    }

    /**
     * @param string $gitlabToken
     * @return Job
     */
    public function setGitlabToken(string $gitlabToken): Job
    {
        $this->gitlabToken = $gitlabToken;
        return $this;
    }

    /**
     * @return string
     */
    public function getGitlabTags(): string
    {
        return $this->gitlabTags;
    }

    /**
     * @param string $gitlabTags
     * @return Job
     */
    public function setGitlabTags(string $gitlabTags): Job
    {
        $this->gitlabTags = $gitlabTags;
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
}