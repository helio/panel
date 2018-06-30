<?php
namespace Helio\Panel\Helper;

use Helio\Panel\Model\User;
use Slim\Http\Request;

class MailHelper {




    /**
     * @var string
     */
    protected static $confirmationMailContent = <<<EOM
    Hi %s 
    Welcome to helio. Please klick this link to confirm your subscription:
    %s
EOM;

    /**
     * @param User $user
     *
     * @return bool
     * @throws \Exception
     */
    public static function sendConfirmationMail(User $user): bool
    {
        $return = mail($user->getEmail(), 'Welcome to Helio',
            vsprintf(self::$confirmationMailContent, [
                $user->getName(),
                ServerHelper::getBaseUrl() . 'panel?user=' . $user->hashedId() . '&token=' .
                JwtHelper::generateToken($user->hashedId(), '+1 week')['token']
            ]));
        return $return;
    }
}