<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$loader = require $root . '/vendor/autoload.php';
if(!PHP_ZTS){
	throw new RuntimeException('Amber native tests require PHP ZTS');
}
\amber\nethernet\backend\ExtWebRtcPeer::assertAvailable();
$loader->addPsr4('amber\\nethernet\\tests\\', $root . '/vendor/amber-pm/nethernet/tests/phpunit');
