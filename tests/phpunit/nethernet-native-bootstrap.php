<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$loader = require $root . '/vendor/autoload.php';
if(!PHP_ZTS){
	throw new RuntimeException('Amber native tests require PHP ZTS');
}
if(extension_loaded('webrtc')){
	throw new RuntimeException('ext-webrtc is not supported and must not be loaded with ext-rtc');
}
\amber\nethernet\backend\ExtRtcPeer::assertAvailable();
$loader->addPsr4('amber\\nethernet\\tests\\', $root . '/vendor/amber-pm/nethernet/tests/phpunit');
