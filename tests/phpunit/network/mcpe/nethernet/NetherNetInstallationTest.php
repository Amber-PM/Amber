<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use PHPUnit\Framework\TestCase;

final class NetherNetInstallationTest extends TestCase{
	private string $directory;
	protected function setUp() : void{
		$this->directory = sys_get_temp_dir() . '/amber-install-' . bin2hex(random_bytes(6));
		mkdir($this->directory, 0700);
	}
	protected function tearDown() : void{
		foreach(glob($this->directory . '/*') as $file){ unlink($file); }
		rmdir($this->directory);
	}
	public function testNormalInstallationDerivesPortAndPersistsIdentity() : void{
		$config = NetherNetConfig::fromArray([], $this->directory, 25565, '127.0.0.1', 'My Amber Server');
		self::assertSame('tcp://127.0.0.1:25565', $config->getBind());
		$config->createServerIdentity();
		$first = file_get_contents($this->directory . '/nethernet.key');
		self::assertNotFalse(openssl_pkey_get_private($first));
		$config->createServerIdentity();
		self::assertSame($first, file_get_contents($this->directory . '/nethernet.key'));
		if(PHP_OS_FAMILY !== 'Windows'){ self::assertSame(0600, fileperms($this->directory . '/nethernet.key') & 0777); }
	}
	public function testExplicitEndpointAndOldPreferencesRemainUnchanged() : void{
		$config = NetherNetConfig::fromArray(['bind' => 'tcp://[::1]:19133', 'max-per-ip' => 7], $this->directory, 25565);
		self::assertSame('tcp://[::1]:19133', $config->getBind());
		self::assertSame(7, $config->getMaxPerIp());
	}
	public function testIpv6AndExplicitSignalingPort() : void{
		$config = NetherNetConfig::fromArray(['signaling-port' => 25566], $this->directory, 25565, '::1');
		self::assertSame('tcp://[::1]:25566', $config->getBind());
	}
	public function testCorruptExistingIdentityIsNeverRegenerated() : void{
		file_put_contents($this->directory . '/nethernet.key', 'corrupt');
		try{
			NetherNetConfig::fromArray([], $this->directory)->createServerIdentity();
			self::fail('Invalid persistent identity must fail');
		}catch(\InvalidArgumentException $e){ self::assertStringContainsString('key', $e->getMessage()); }
		self::assertSame('corrupt', file_get_contents($this->directory . '/nethernet.key'));
	}
	public function testNormalAuthenticationDoesNotRequireOrReadAdministratorPemFiles() : void{
		$provider = $this->createMock(\pocketmine\network\mcpe\auth\AuthKeyProvider::class);
		$provider->expects(self::never())->method('getKey');
		$config = NetherNetConfig::fromArray(['private-key-file' => '', 'operator-domain' => '', 'trusted-key-files' => []], $this->directory);
		self::assertInstanceOf(MinecraftIdentityHandler::class, $config->createIdentityHandler($provider));
		self::assertFileExists($this->directory . '/nethernet.key');
	}
	public function testInvalidUdpRangeIsRejectedEvenWithoutManualTrustedKeys() : void{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('ice-port-range');
		NetherNetConfig::fromArray(['ice-port-range' => [30000, 20000]], $this->directory);
	}
	public function testAdvertisedAddressCapabilityIsExplicitlyBlocked() : void{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('no advertised-address API');
		NetherNetConfig::fromArray(['advertised-address' => '203.0.113.1'], $this->directory);
	}
	public function testIdentityDomainUsesMotdOrSafeFallback() : void{
		foreach(['My Amber Server' => 'my-amber-server', '💛' => 'self'] as $motd => $expected){
			$config = NetherNetConfig::fromArray([], $this->directory, motd: $motd);
			$assertion = $config->createServerIdentity()->signServer('{"fingerprint":[{"algorithm":"sha-256","digest":"' . implode(':', array_fill(0, 32, '11')) . '"}]}', time());
			$envelope = json_decode(base64_decode($assertion), true, flags: JSON_THROW_ON_ERROR);
			self::assertSame($expected, $envelope['idp']['domain']);
		}
	}
	public function testWindowsIdentityHasPrivateOwnerAcl() : void{
		if(PHP_OS_FAMILY !== 'Windows'){ self::markTestSkipped('Windows ACL assertion'); }
		$script = '$path=[System.IO.Path]::GetDirectoryName($env:AMBER_TEST_KEY_PATH); $acl=[System.IO.Directory]::GetAccessControl($path); $everyone=New-Object System.Security.Principal.SecurityIdentifier("S-1-1-0"); $rule=New-Object System.Security.AccessControl.FileSystemAccessRule($everyone,"ReadAndExecute","ContainerInherit,ObjectInherit","None","Allow"); $acl.AddAccessRule($rule); [System.IO.Directory]::SetAccessControl($path,$acl);';
		self::assertSame(0, $this->windowsAclCommand($script));
		NetherNetConfig::fromArray([], $this->directory)->createServerIdentity();
		$script = '$acl=[System.IO.File]::GetAccessControl($env:AMBER_TEST_KEY_PATH); $sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User; $rules=$acl.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier]); if(!$acl.AreAccessRulesProtected -or $rules.Count -ne 1 -or $rules[0].IdentityReference.Value -ne $sid.Value){exit 1}';
		self::assertSame(0, $this->windowsAclCommand($script));
		$first = file_get_contents($this->directory . '/nethernet.key');
		NetherNetConfig::fromArray([], $this->directory)->createServerIdentity();
		self::assertSame($first, file_get_contents($this->directory . '/nethernet.key'));
		self::assertSame(0, $this->windowsAclCommand($script));
	}
	private function windowsAclCommand(string $script) : int{
		$process = proc_open([getenv('SystemRoot') . '/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))], [], $pipes, env_vars: array_merge(getenv(), ['AMBER_TEST_KEY_PATH' => $this->directory . '/nethernet.key']));
		self::assertIsResource($process);
		return proc_close($process);
	}
}
