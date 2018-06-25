<?php

require __DIR__ . '/src/bootstrap.php';

try {
    return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet(\Helio\Panel\Helper\DbHelper::get());
} catch (\Doctrine\ORM\ORMException $e) {
    echo $e->getMessage();
    return 2;
}
