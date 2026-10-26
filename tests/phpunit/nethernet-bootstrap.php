<?php
declare(strict_types=1);

$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('amber\\nethernet\\tests\\', dirname(__DIR__, 2) . '/vendor/amber-pm/nethernet/tests/phpunit');
