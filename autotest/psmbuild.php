<?php
require('Build.php');

$branch = empty($argv[1]) ? "" : $argv[1];

$build = new Build($branch);
$build->execute();