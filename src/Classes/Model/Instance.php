<?php
/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use OpenApi\Annotations as OA;
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
use Helio\Panel\Orchestrator\OrchestratorType;
use Helio\Panel\Master\MasterFactory;

/**
 *
 * @OA\Schema(
 *     type="object",
 *     title="Instance model"
 * )
 *
 * @Entity @Table(name="instance")
 **/
class Instance extends AbstractModel
{


    /**
     * @OA\Property(ref="#/components/schemas/instancestatus")
     *
     * @var int
     *
     * @Column(type="integer")
     */
    protected $status = InstanceStatus::UNKNOWN;


    /**
     * @OA\Property(
     *     description="FQDN of the instance, used for idenfication and ssh purposes.",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $fqdn = '';


    /**
     * @OA\Property(
     *     description="IP Address of the instance.",
     *     format="string"
     * )
     *
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
    protected $instanceType = InstanceType::__DEFAULT;

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
    protected $orchestratorType = OrchestratorType::__DEFAULT;

    /**
     * @var string
     *
     * @Column(type="text")
     */
    protected $snapshotConfig = '';

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
    protected $orchestratorCoordinator = 'control.idling.host';


    /**
     * @OA\Property(
     *     description="Region where the server is phisically located",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $region = '';


    /**
     * @OA\Property(
     *     description="Security Level according to supplier contract.",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $security = '';


    /**
     * @OA\Property(
     *     description="Reference used for pay-outs",
     *     format="string"
     * )
     *
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
     * @var string Docker ID
     */
    protected $runnerId = '';


    /**
     * @OA\Property(
     *     description="Whether or not this instance may run free compute jobs.",
     *     format="boolean"
     * )
     *
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $allowFreeComputing = true;

    /**
     * @OA\Property(
     *     description="Priority within the same supplier. Lower number means more jobs.",
     *     format="integer"
     * )
     *
     * @var int
     *
     * @Column
     */
    protected $priority = 100;


    /**
     * @OA\Property(
     *     description="Object with the specs of the instance.",
     *     format="string"
     * )
     *
     * @var string
     *
     * @Column(type="text")
     */
    protected $inventory = '';


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
    public function getOrchestratorType(): string
    {
        return $this->orchestratorType;
    }

    /**
     * @param string $orchestratorType
     * @return Instance
     */
    public function setOrchestratorType(string $orchestratorType): Instance
    {
        $this->orchestratorType = $orchestratorType;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrchestratorCoordinator(): string
    {
        return $this->orchestratorCoordinator;
    }

    /**
     * @return string
     */
    public function getSnapshotConfig(): string
    {
        return $this->snapshotConfig;
    }

    /**
     * @param string $snapshotConfig
     * @return Instance
     */
    public function setSnapshotConfig(string $snapshotConfig): Instance
    {
        $this->snapshotConfig = $snapshotConfig;
        return $this;
    }


    /**
     * @param string $orchestratorCoordinator
     * @return Instance
     */
    public function setOrchestratorCoordinator(string $orchestratorCoordinator): Instance
    {
        $this->orchestratorCoordinator = $orchestratorCoordinator;
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
    public function getSecurity(): string
    {
        return $this->security;
    }

    /**
     * @param string $security
     * @return Instance
     */
    public function setSecurity(string $security): Instance
    {
        $this->security = $security;
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

    /**
     * @return string
     */
    public function getRunnerId(): string
    {
        return $this->runnerId;
    }

    /**
     * @param string $runnerId
     * @return Instance
     */
    public function setRunnerId(string $runnerId): Instance
    {
        $this->runnerId = $runnerId;
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
     * @return Instance
     */
    public function setPriority(int $priority): Instance
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return array
     */
    public function getInventory(): array
    {
        return json_decode($this->inventory, true) ?? [];
    }

    /**
     * @param string $inventory
     * @return Instance
     */
    public function setInventory(string $inventory): Instance
    {
        $this->inventory = $inventory;
        return $this;
    }

}