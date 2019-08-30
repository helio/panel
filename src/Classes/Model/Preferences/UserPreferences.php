<?php

namespace Helio\Panel\Model\Preferences;

class UserPreferences implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $limits;
    protected $notifications;

    public function __construct(array $preferences = [])
    {
        $this->limits = $preferences['limits'] ?? [];
        $this->notifications = $preferences['notifications'] ?? [];
    }

    public function getLimits(): UserLimits
    {
        return new UserLimits($this->limits);
    }

    public function setLimits(UserLimits $limits): void
    {
        $this->limits = $limits->jsonSerialize();
    }

    public function getNotifications(): UserNotifications
    {
        return new UserNotifications($this->notifications);
    }

    public function setNotifications(UserNotifications $notifications): void
    {
        $this->notifications = $notifications->jsonSerialize();
    }

    public function jsonSerialize(): array
    {
        return [
            'limits' => $this->limits,
            'notifications' => $this->notifications,
        ];
    }
}
