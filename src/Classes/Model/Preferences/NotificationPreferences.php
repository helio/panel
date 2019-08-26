<?php

namespace Helio\Panel\Model\Preferences;

class NotificationPreferences extends AbstractPreferences
{
    const MUTE_ADMIN = 1;
    const EMAIL_ON_JOB_READY = 2;
    const EMAIL_ON_JOB_DELETED = 4;
    const EMAIL_ON_EXECUTION_STARTED = 8;
    const EMAIL_ON_EXECUTION_ENDED = 16;
}
