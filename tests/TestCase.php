<?php

namespace Helio\Test;


/**
 * Class TestCase serves as root for all cases
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class TestCase extends \PHPUnit_Framework_TestCase
{


    /**
     *
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!\defined('APPLICATION_ROOT')) {
            \define('APPLICATION_ROOT', __DIR__ . '/..');
            \define('SITE_ENV', 'TEST');
            \define('LOG_DEST', APPLICATION_ROOT . '/log/app-test.log');
            \define('LOG_LVL', 100);
        }
        $_SERVER['JWT_SECRET'] = 'ladida';
        $_SERVER['ZAPIER_HOOK_URL'] = '/blah';
    }
}