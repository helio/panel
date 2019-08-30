<?php

namespace Helio\Panel\Model\Preferences;

class UserNotifications implements \JsonSerializable
{
    private const DEFAULT_MUTE_ADMIN = false;
    private const DEFAULT_EMAIL_ON_JOB_READY = true;
    private const DEFAULT_EMAIL_ON_JOB_DELETED = false;
    private const DEFAULT_EMAIL_ON_EXECUTION_STARTED = false;
    private const DEFAULT_EMAIL_ON_EXECUTION_ENDED = true;
    private const DEFAULT_EMAIL_ON_AUTOSCHEDULED_EXECUTION_ENDED = true;

    /** @var bool */
    protected $muteAdmin = false;

    /** @var bool */
    protected $emailOnJobReady = true;

    /** @var bool */
    protected $emailOnJobDeleted = false;

    /** @var bool */
    protected $emailOnExecutionStarted = false;

    /** @var bool */
    protected $emailOnExecutionEnded = true;

    /** @var bool */
    protected $emailOnAutoscheduledExecutionEnded = true;

    public function __construct(array $notifications = [])
    {
        $this->muteAdmin = $notifications['mute_admin'] ?? self::DEFAULT_MUTE_ADMIN;
        $this->emailOnJobReady = $notifications['email_on_job_ready'] ?? self::DEFAULT_EMAIL_ON_JOB_READY;
        $this->emailOnJobDeleted = $notifications['email_on_job_deleted'] ?? self::DEFAULT_EMAIL_ON_JOB_DELETED;
        $this->emailOnExecutionStarted = $notifications['email_on_execution_started'] ?? self::DEFAULT_EMAIL_ON_EXECUTION_STARTED;
        $this->emailOnExecutionEnded = $notifications['email_on_execution_ended'] ?? self::DEFAULT_EMAIL_ON_EXECUTION_ENDED;
        $this->emailOnAutoscheduledExecutionEnded = $notifications['email_on_autoscheduled_execution_ended'] ?? self::DEFAULT_EMAIL_ON_AUTOSCHEDULED_EXECUTION_ENDED;
    }

    /**
     * @return bool
     */
    public function isMuteAdmin(): bool
    {
        return $this->muteAdmin;
    }

    /**
     * @param  bool              $muteAdmin
     * @return UserNotifications
     */
    public function setMuteAdmin(bool $muteAdmin): UserNotifications
    {
        $this->muteAdmin = $muteAdmin;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmailOnJobReady(): bool
    {
        return $this->emailOnJobReady;
    }

    /**
     * @param  bool              $emailOnJobReady
     * @return UserNotifications
     */
    public function setEmailOnJobReady(bool $emailOnJobReady): UserNotifications
    {
        $this->emailOnJobReady = $emailOnJobReady;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmailOnJobDeleted(): bool
    {
        return $this->emailOnJobDeleted;
    }

    /**
     * @param  bool              $emailOnJobDeleted
     * @return UserNotifications
     */
    public function setEmailOnJobDeleted(bool $emailOnJobDeleted): UserNotifications
    {
        $this->emailOnJobDeleted = $emailOnJobDeleted;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmailOnExecutionStarted(): bool
    {
        return $this->emailOnExecutionStarted;
    }

    /**
     * @param  bool              $emailOnExecutionStarted
     * @return UserNotifications
     */
    public function setEmailOnExecutionStarted(bool $emailOnExecutionStarted): UserNotifications
    {
        $this->emailOnExecutionStarted = $emailOnExecutionStarted;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmailOnExecutionEnded(): bool
    {
        return $this->emailOnExecutionEnded;
    }

    /**
     * @param  bool              $emailOnExecutionEnded
     * @return UserNotifications
     */
    public function setEmailOnExecutionEnded(bool $emailOnExecutionEnded): UserNotifications
    {
        $this->emailOnExecutionEnded = $emailOnExecutionEnded;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmailOnAutoscheduledExecutionEnded(): bool
    {
        return $this->emailOnAutoscheduledExecutionEnded;
    }

    /**
     * @param  bool              $emailOnAutoscheduledExecutionEnded
     * @return UserNotifications
     */
    public function setEmailOnAutoscheduledExecutionEnded(bool $emailOnAutoscheduledExecutionEnded): UserNotifications
    {
        $this->emailOnAutoscheduledExecutionEnded = $emailOnAutoscheduledExecutionEnded;

        return $this;
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
            'mute_admin' => $this->muteAdmin,
            'email_on_job_ready' => $this->emailOnJobReady,
            'email_on_job_deleted' => $this->emailOnJobDeleted,
            'email_on_execution_started' => $this->emailOnExecutionStarted,
            'email_on_execution_ended' => $this->emailOnExecutionEnded,
            'email_on_autoscheduled_execution_ended' => $this->emailOnAutoscheduledExecutionEnded,
        ];
    }
}
