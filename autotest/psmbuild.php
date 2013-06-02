<?php

$psmDir = realpath('..');

// First, execute the tests
$cmd = "cd $psmDir; phpunit --testsuite PHPSchemaManagerSuite";
$ret = system($cmd);

if (!$ret) {
    die("Unit Test failed");
}

// TODO find a way to check if PSR2 coding standard is installed in the system
$cmd = "cd $psmDir; phpcs --standard=PSR2 PHPSchemaManager";
system($cmd);
