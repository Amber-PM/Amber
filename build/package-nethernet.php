<?php
declare(strict_types=1);

putenv('TZ=UTC');
date_default_timezone_set('UTC');
$root = dirname(__DIR__);
$library = $argv[1] ?? $root . '/../libraries/NetherNet';
$package = json_decode(file_get_contents($library . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
foreach(['require-dev', 'autoload-dev', 'scripts', 'config'] as $key){
	unset($package[$key]);
}
$files = [
	'composer.json', 'LICENSE', 'phpunit.rtc.xml.dist',
	'tests/phpunit/identity/IdentityFixture.php', 'tests/phpunit/identity/openssl.cnf',
	'tests/phpunit/signaling/AnswerSignatures.php', 'tests/phpunit/transport/RecordingListener.php',
	'tests/phpunit/transport/FakePeer.php'
];
foreach(['src', 'tests/rtc'] as $directory){
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($library . '/' . $directory, FilesystemIterator::SKIP_DOTS));
	foreach($iterator as $file){
		if($file->isFile() && $file->getExtension() === 'php'){
			$files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($library) + 1));
		}
	}
}
sort($files, SORT_STRING);
$directory = $root . '/packages';
if(!is_dir($directory)){ mkdir($directory, 0755, true); }
$temporary = tempnam($directory, 'nethernet-');
$zip = new ZipArchive();
if($zip->open($temporary, ZipArchive::OVERWRITE) !== true){ throw new RuntimeException('Cannot create NetherNet bundle'); }
foreach($files as $relative){
	$name = 'nethernet/' . $relative;
	if(!$zip->addFromString($name, file_get_contents($library . '/' . $relative)) ||
		!$zip->setMtimeName($name, 1767225600) || // fixed release-input timestamp
		!$zip->setCompressionName($name, ZipArchive::CM_DEFLATE, 9) ||
		!$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)){
		throw new RuntimeException('Cannot archive NetherNet file: ' . $relative);
	}
}
if(!$zip->close()){ throw new RuntimeException('Cannot finish NetherNet bundle'); }
$digest = hash_file('sha256', $temporary);
$relative = 'packages/nethernet-' . $digest . '.zip';
if(!rename($temporary, $root . '/' . $relative)){ throw new RuntimeException('Cannot publish NetherNet release input'); }
$package['version'] = 'dev-main'; // no published release tag yet
$package['dist'] = ['type' => 'zip', 'url' => $relative, 'reference' => $digest, 'shasum' => hash_file('sha1', $root . '/' . $relative)];
$manifest = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$manifest['repositories'] = array_values(array_filter($manifest['repositories'], static fn(array $repository) : bool => ($repository['package']['name'] ?? null) !== 'amber-pm/nethernet'));
array_unshift($manifest['repositories'], ['type' => 'package', 'package' => $package]);
$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$json = preg_replace_callback('/^( +)/m', static fn(array $match) : string => str_repeat(' ', intdiv(strlen($match[1]), 4) * 3), $json);
file_put_contents($root . '/composer.json', $json . "\n");
echo 'NetherNet bundle: ' . $relative . "\n";
