<?php

/** @noinspection PhpUnusedAliasInspection */

namespace Helio\Panel\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Helio\Panel\Orchestrator\ManagerStatus;
use OpenApi\Annotations as OA;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @OA\Schema(
 *     type="object",
 *     title="Manager model"
 * )
 *
 * @Entity @Table(name="manager")
 **/
class Manager extends AbstractModel
{
    /**
     * @var string
     *
     * @Column(type="string")
     */
    protected $status = ManagerStatus::UNKNOWN;

    /**
     * @var array<Job>
     *
     * @OneToMany(targetEntity="Job", mappedBy="managers")
     */
    protected $jobs;

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
     * @var string
     *
     * @Column
     */
    protected $workerToken = '';

    /**
     * @var string
     *
     * @Column
     */
    protected $managerToken = '';

    /**
     * @var string potentially random ID that was given to the node by choria
     *
     * @Column(type="string")
     */
    protected $idByChoria = '';

    public function __construct()
    {
        parent::__construct();
        $this->jobs = new ArrayCollection();
    }

    /**
     * summary method for determining the node status.
     *
     * @return bool
     */
    public function works(): bool
    {
        return
            ManagerStatus::isValidActiveStatus($this->getStatus())
            && $this->getManagerToken()
            && $this->getWorkerToken()
            && $this->getIp()
            && $this->getFqdn()
        ;
    }

    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    /**
     * @param Job $job
     *
     * @return Manager
     */
    public function addJob(Job $job): Manager
    {
        $this->jobs[] = $job;

        if (null === $job->getManager()) {
            $job->setManager($this);
        }

        return $this;
    }

    /**
     * @param string $status
     *
     * @return $this|AbstractModel
     */
    public function setStatus($status)
    {
        if (ManagerStatus::isValidStatus($status)) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getFqdn(): string
    {
        return $this->fqdn;
    }

    /**
     * @param  string  $fqdn
     * @return Manager
     */
    public function setFqdn(string $fqdn): Manager
    {
        $this->fqdn = $fqdn;
        $this->setName(explode('.', $this->getFqdn())[0]);

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
     * @param  string  $ip
     * @return Manager
     */
    public function setIp(string $ip): Manager
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return string
     */
    public function getWorkerToken(): string
    {
        return $this->workerToken;
    }

    /**
     * @param  string  $workerToken
     * @return Manager
     */
    public function setWorkerToken(string $workerToken): Manager
    {
        $this->workerToken = $workerToken;

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
     * @param  string  $managerToken
     * @return Manager
     */
    public function setManagerToken(string $managerToken): Manager
    {
        $this->managerToken = $managerToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getIdByChoria(): string
    {
        return $this->idByChoria;
    }

    /**
     * @param  string  $idByChoria
     * @return Manager
     */
    public function setIdByChoria(string $idByChoria): Manager
    {
        $this->idByChoria = $idByChoria;

        return $this;
    }
}
