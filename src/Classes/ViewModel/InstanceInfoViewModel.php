<?php

namespace Helio\Panel\ViewModel;

use Helio\Panel\Utility\ArrayUtility;

class InstanceInfoViewModel
{
    protected $rawData;
    /**
     * @var int
     */
    protected $gpus;
    /**
     * @var int
     */
    protected $cpus;
    /**
     * @var int
     */
    protected $memory;
    /**
     * @var int
     */
    protected $uptime;
    /**
     * @var string
     */
    protected $architecture;
    /**
     * @var string
     */
    protected $platform;

    /**
     * Pass in an array of all possible information sources.
     *
     * InstanceInfoViewModel constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->rawData = $data;
    }

    public function toArray(): array
    {
        foreach (array_keys(get_object_vars($this)) as $property) {
            if ('rawData' !== $property) {
                $name = 'get' . ucfirst($property);
                $this->$name();
            }
        }

        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getGpus(): int
    {
        return $this->gpus ?? $this->setGpus()->getGpus();
    }

    /**
     * @param int $gpus
     *
     * @return InstanceInfoViewModel
     */
    public function setGpus(int $gpus = null): InstanceInfoViewModel
    {
        if (null === $gpus) {
            $gpus = (int) ArrayUtility::getFirstByDotNotation($this->rawData, ['gpus', 'Description.Resources.NanoGPUs'], 0);
            if ($gpus > 100000000) { // 0.1 CPUs in NanoGPUs
                $gpus /= 1000000000;
            }
        }
        $this->gpus = $gpus;

        return $this;
    }

    /**
     * @return int
     */
    public function getCpus(): int
    {
        return $this->cpus ?? $this->setCpus()->getCpus();
    }

    /**
     * @param int $cpus
     *
     * @return InstanceInfoViewModel
     */
    public function setCpus(int $cpus = null): InstanceInfoViewModel
    {
        if (null === $cpus) {
            $cpus = (int) ArrayUtility::getFirstByDotNotation($this->rawData, ['processors', 'Description.Resources.NanoCPUs'], 0);
            if ($cpus > 100000000) { // 0.1 CPUs in NanoCPUs
                $cpus /= 1000000000;
            }
        }
        $this->cpus = (int) $cpus;

        return $this;
    }

    /**
     * @return int
     */
    public function getMemory(): int
    {
        return $this->memory ?? $this->setMemory()->getMemory();
    }

    /**
     * @param int $memory
     *
     * @return InstanceInfoViewModel
     */
    public function setMemory(int $memory = null): InstanceInfoViewModel
    {
        if (null === $memory) {
            $memoryFrom = ArrayUtility::getFirstByDotNotation($this->rawData, ['memorysize', 'Description.Resources.MemoryBytes'], 0);
            $matches = [];
            if (preg_match('/[0-9\.]+ ?([M|G|K|T|P])i?[bB]/', $memoryFrom, $matches)) {
                $memoryFrom = (float) $memoryFrom;
                switch ($matches[1]) {
                    /* @noinspection PhpMissingBreakStatementInspection */
                    case 'P':
                        $memoryFrom *= 1024;
                    /* @noinspection PhpMissingBreakStatementInspection */
                    // no break
                    case 'T':
                        $memoryFrom *= 1024;
                    /* @noinspection PhpMissingBreakStatementInspection */
                    // no break
                    case 'G':
                        $memoryFrom *= 1024;
                    /* @noinspection PhpMissingBreakStatementInspection */
                    // no break
                    case 'M':
                        $memoryFrom *= 1024;
                        // no break
                    case 'K':
                        $memoryFrom *= 1024;
                        break;
                    default:
                        break;
                }
            }
            $memory = (int) $memoryFrom;
        }
        $this->memory = $memory;

        return $this;
    }

    /**
     * @return int
     */
    public function getUptime(): int
    {
        return $this->uptime ?? $this->setUptime()->getUptime();
    }

    /**
     * @param int $uptime
     *
     * @return InstanceInfoViewModel
     */
    public function setUptime(int $uptime = null): InstanceInfoViewModel
    {
        if (null === $uptime) {
            $uptime = (int) ArrayUtility::getFirstByDotNotation($this->rawData, ['uptime']);
        }
        $this->uptime = $uptime;

        return $this;
    }

    /**
     * @return string
     */
    public function getArchitecture(): string
    {
        return $this->architecture ?? $this->setArchitecture()->getArchitecture();
    }

    /**
     * @param string $architecture
     *
     * @return InstanceInfoViewModel
     */
    public function setArchitecture(string $architecture = null): InstanceInfoViewModel
    {
        $this->architecture = $architecture ?? ArrayUtility::getFirstByDotNotation($this->rawData, ['processor0', 'Description.Platform.Architecture'], '');

        return $this;
    }

    /**
     * @return string
     */
    public function getPlatform(): string
    {
        return $this->platform ?? $this->setPlatform()->getPlatform();
    }

    /**
     * @param string $platform
     *
     * @return InstanceInfoViewModel
     */
    public function setPlatform(string $platform = null): InstanceInfoViewModel
    {
        $this->platform = $platform ?? ArrayUtility::getFirstByDotNotation($this->rawData, ['os', 'Description.Platform.OS'], '');

        return $this;
    }
}
