<?php

namespace Helio\Panel\Model\Preferences;

class UserPreferences implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $limits;

    public function __construct(array $preferences = [])
    {
        $this->limits = $preferences['limits'] ?? [];
    }

    public function getLimits(): UserLimits
    {
        return new UserLimits($this->limits);
    }

    public function setLimits(UserLimits $limits): void
    {
        $this->limits = $limits->jsonSerialize();
    }

    public function jsonSerialize(): array
    {
        return [
            'limits' => $this->limits,
        ];
    }
}
