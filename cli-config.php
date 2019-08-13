<?php

require __DIR__ . '/src/bootstrap.php';

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet(\Helio\Panel\Helper\DbHelper::getInstance()->get());
