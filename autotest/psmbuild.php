<?php

// First, execute the tests
$cmd = "phpunit --testsuite PHPSchemaManagerSuite";
system($cmd);

// TODO find a way to check if PSR2 coding standard is installed in the system
$cmd = "phpcs --standard=PSR2 PHPSchemaManager";
system($cmd);