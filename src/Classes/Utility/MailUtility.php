<?php
namespace Helio\Panel\Utility;

use Helio\Panel\Model\User;
use Slim\Http\Request;

class MailUtility {




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
                ServerUtility::getBaseUrl() . 'panel?token=' .
                JwtUtility::generateToken($user->getId(), '+1 week')['token']
            ]));
        return $return;
    }
}