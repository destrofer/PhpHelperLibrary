<?php
use Destrofer\Platform\SSHSession;

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);

require "../autoloader.php";

$txt = "This is a test file. Safe to remove.";
$test = "";
$fingerprint = "";
$expectedFingerprint = "3BED94BA4B94F301FD7D8AE6A5814ED401840F97";
$file = "/phl-ssh-test.txt";

$ssh = new SSHSession();
try {
	$ssh->logCommands = $ssh->logConsole = $ssh->logFileTransfers = true;
	$ssh->connect("192.168.1.234");
	$fingerprint = $ssh->getFingerprint();
	$ssh->log[] = "Remote host fingerprint: {$fingerprint}";
	$ssh->authenticateUsingKeys("root", "id_rsa", "id_rsa.pub", $expectedFingerprint);
	$ssh->uploadString($txt, $file);
	$ssh->downloadString($file, $test);
	if( $test !== $txt )
		$ssh->log[] = "ERROR: Uploaded content does not match downloaded content!";
	$ssh->exec("rm {$file}"); // this should be ok
	$ssh->exec("rm {$file}"); // this should write error to stderr
	$ssh->disconnect();
	echo implode("\n", $ssh->log) . "\n";
}
catch(Exception $ex) {
	echo implode("\n", $ssh->log) . "\n";
	echo $ex->__toString();
}