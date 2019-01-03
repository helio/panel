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
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Instance\InstanceType;
use Helio\Panel\Master\MasterType;
use Helio\Panel\Runner\RunnerType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Runner\RunnerFactory;

/**
 * @Entity @Table(name="instance")
 **/
class Instance extends AbstractModel
{


    /**
     * @var string
     *
     * @Column
     */
    protected $fqdn = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $ip = '';

    /**
     * @var User
     *
     * @ManyToOne(targetEntity="User", inversedBy="instances", cascade={"persist"})
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
    protected $instanceType = InstanceType::__DEFAULT;

    /**
     * @var string
     *
     * @Column
     */
    protected $runnerType = RunnerType::__DEFAULT;

    /**
     * @var string
     *
     * @Column
     */
    protected $masterType = MasterType::__DEFAULT;

    /**
     * @var string
     *
     * @Column
     */
    protected $runnerCoordinator = 'swarm.idling.host';

    /**
     * @var string
     *
     * @Column
     */
    protected $masterCoordinator = 'master-a.idling.host';


    /**
     * @var string
     *
     * @Column
     */
    protected $region = '';


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
    protected $supervisorApi = '';


    /**
     * @var string
     *
     * @Column
     */
    protected $supervisorToken = '';

    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $allowFreeComputing = true;

    /**
     * @return string
     */
    public function getFqdn(): string
    {
        return $this->fqdn;
    }


    /**
     * @param string $fqdn
     * @return Instance
     */
    public function setFqdn(string $fqdn): Instance
    {
        $this->fqdn = $fqdn;

        return $this;
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
     * @return Instance
     */
    public function setOwner(User $owner): Instance
    {
        $add = true;
        /** @var Instance $instance */
        foreach ($owner->getInstances() as $instance) {
            if ($instance->getId() === $this->getId()) {
                $add = false;
            }
        }
        if ($add) {
            $owner->addInstance($this);
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
     * @return Instance $this
     */
    public function setToken(string $token): Instance
    {
        $this->token = $token;
        return $this;
    }


    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }


    /**
     * @param string $ip
     * @return Instance $this
     */
    public function setIp(string $ip): Instance
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @param int $status
     * @return Instance $this
     */
    public function setStatus(int $status): Instance
    {
        if (InstanceStatus::isValidStatus($status)) {
            $this->status = $status;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getInstanceType(): string
    {
        return $this->instanceType;
    }

    /**
     * @param string $instanceType
     * @return Instance
     */
    public function setInstanceType(string $instanceType): Instance
    {
        if (InstanceType::isValidType($instanceType)) {
            $this->instanceType = $instanceType;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getRunnerType(): string
    {
        return $this->runnerType;
    }

    /**
     * @param string $runnerType
     * @return Instance $this
     */
    public function setRunnerType(string $runnerType): Instance
    {
        if (RunnerType::isValidType($runnerType)) {
            $this->runnerType = $runnerType;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getRunnerCoordinator(): string
    {
        return $this->runnerCoordinator;
    }

    /**
     * @param string $runnerCoordinator
     * @return Instance $this
     */
    public function setRunnerCoordinator(string $runnerCoordinator): Instance
    {
        $this->runnerCoordinator = $runnerCoordinator;
        return $this;
    }

    /**
     * @return string
     */
    public function getMasterType(): string
    {
        return $this->masterType;
    }

    /**
     * @param string $masterType
     * @return Instance $this
     */
    public function setMasterType(string $masterType): Instance
    {
        if (MasterType::isValidType($masterType)) {
            $this->masterType = $masterType;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getMasterCoordinator(): string
    {
        return $this->masterCoordinator;
    }

    /**
     * @param string $masterCoordinator
     * @return Instance $this
     */
    public function setMasterCoordinator(string $masterCoordinator): Instance
    {
        $this->masterCoordinator = $masterCoordinator;

        return $this;
    }

    /**
     * @return string
     */
    public function getHostname(): string
    {
        return substr($this->getFqdn(), 0, strpos($this->getFqdn(), '.'));
    }

    /**
     * @return bool
     */
    public function isAllowFreeComputing(): bool
    {
        return $this->allowFreeComputing;
    }

    /**
     * @param bool $allowFreeComputing
     * @return Instance
     */
    public function setAllowFreeComputing(bool $allowFreeComputing): Instance
    {
        $this->allowFreeComputing = $allowFreeComputing;
        return $this;
    }

    /**
     * @return string
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * @param string $region
     * @return Instance
     */
    public function setRegion(string $region): Instance
    {
        $this->region = $region;
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
     * @return Instance
     */
    public function setBillingReference(string $billingReference): Instance
    {
        $this->billingReference = $billingReference;
        return $this;
    }

    /**
     * @return string
     */
    public function getSupervisorApi(): string
    {
        return $this->supervisorApi;
    }

    /**
     * @param string $supervisorApi
     * @return Instance
     */
    public function setSupervisorApi(string $supervisorApi): Instance
    {
        $this->supervisorApi = $supervisorApi;
        return $this;
    }

    /**
     * @return string
     */
    public function getSupervisorToken(): string
    {
        return $this->supervisorToken;
    }

    /**
     * @param string $supervisorToken
     * @return Instance
     */
    public function setSupervisorToken(string $supervisorToken): Instance
    {
        $this->supervisorToken = $supervisorToken;
        return $this;
    }

}