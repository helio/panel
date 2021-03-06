<?php

namespace Helio\Panel\Model;

use Exception;
use RuntimeException;
use DateTime;
use DateTimeZone;
use OpenApi\Annotations as OA;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Utility\ArrayUtility;
use Helio\Panel\Utility\ServerUtility;

abstract class AbstractModel
{
    /**
     * @OA\Property(
     *     format="integer",
     *     description="Id of the item to manipulate. If empty, a new one will be created"
     * )
     *
     * @var int
     *
     * @Id @Column(type="integer") @GeneratedValue
     */
    protected $id;

    /**
     * @OA\Property(
     *     format="string",
     *     description="Name of the item"
     * )
     *
     * @var string
     *
     * @Column
     */
    protected $name = '';

    /**
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Column(type="datetimetz", nullable=TRUE)
     */
    protected $latestAction;

    /**
     * @var string
     *
     * @Column
     */
    protected $timezone = '';

    /**
     * @var bool
     *
     * @Column(type="boolean")
     */
    protected $hidden = false;

    /**
     * @var string
     *
     * @Column(type="text")
     */
    protected $config = '';

    /**
     * AbstractModel constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->timezone = ServerUtility::getTimezoneObject()->getName();
        $this->setCreated();
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new DateTimeZone($this->getTimezone()));
        }

        return $this->created;
    }

    /**
     * @param DateTime|null $created
     *
     * @return AbstractModel
     *
     * @throws Exception
     */
    public function setCreated(DateTime $created = null): self
    {
        if (!$created) {
            $created = new DateTime('now', new DateTimeZone($this->getTimezone()));
        }
        $created->setTimezone(new DateTimeZone('UTC'));

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $this->created = $created;

        return $this;
    }

    /**
     * @param int $timestamp
     *
     * @return AbstractModel
     *
     * @throws Exception
     *
     * NOTE: Don't use ths method! It's for testing purposes only!
     *
     * @internal
     */
    public function setCreatedByTimestamp(int $timestamp): self
    {
        LogHelper::warn('SetCreatedByTimestamp called');
        $this->created = (new DateTime('now', ServerUtility::getTimezoneObject()))->setTimestamp($timestamp);

        return $this;
    }

    public function getLatestAction(): ?DateTime
    {
        if ($this->created->getTimezone()->getName() !== $this->getTimezone()) {
            $this->created->setTimezone(new DateTimeZone($this->getTimezone()));
        }

        return $this->latestAction;
    }

    /**
     * @param DateTime|null $latestAction
     *
     * @return AbstractModel
     *
     * @throws Exception
     */
    public function setLatestAction(DateTime $latestAction = null): self
    {
        if (null === $latestAction) {
            $latestAction = new DateTime('now', new DateTimeZone($this->getTimezone()));
        }

        // Fix Timezone because Doctrine assumes persistend DateTime Objects are always UTC
        $latestAction->setTimezone(new DateTimeZone('UTC'));

        $this->latestAction = $latestAction;

        return $this;
    }

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @param bool $hidden
     *
     * @return $this
     */
    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        if (!$this->timezone) {
            $this->setTimezone(ServerUtility::getTimezoneObject()->getName());
        }

        return $this->timezone;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param string $option
     * @param mixed  $default
     *
     * @return mixed|string
     */
    public function getConfig(string $option = '', $default = '')
    {
        if ($option) {
            if (!strlen($this->config)) {
                return $default;
            }
            $decodedConfig = \GuzzleHttp\json_decode($this->config, true);

            return ArrayUtility::getFirstByDotNotation([$decodedConfig], [$option]) ?? $default;
        }

        return $this->config;
    }

    /**
     * @param string|array $config
     *
     * @return $this
     */
    public function setConfig($config): self
    {
        if (is_array($config)) {
            $config = json_encode($config);
        }
        $this->config = $config;

        return $this;
    }

    /**
     * @param int $id
     *
     * @return $this $this
     *               Allow setting the id
     */
    public function setId(int $id): self
    {
        if (0 !== $id && ServerUtility::isProd()) {
            throw new RuntimeException('You cannot force IDs, they are auto-incremented on DB-level.', 1548053101);
        }
        $this->id = $id;

        return $this;
    }
}
