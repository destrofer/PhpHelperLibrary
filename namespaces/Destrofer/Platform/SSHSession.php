<?php
/**
 * Copyright 2017 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Platform;

use Destrofer\Platform\Exceptions\SSHAuthenticationException;
use Destrofer\Platform\Exceptions\SSHConnectionException;
use Destrofer\Platform\Exceptions\SSHExecException;
use Destrofer\Platform\Exceptions\SSHFileTransferException;

class SSHSession {
	/** @var string|null */
	protected $host = null;
	/** @var resource|null */
	protected $session = null;
	/** @var bool */
	protected $authenticated = false;
	/** @var string|null */
	protected $user = null;
	/** @var bool */
	public $logCommands = false;
	/** @var bool */
	public $logFileTransfers = false;
	/** @var bool */
	public $logConsole = false;
	/** @var string[] */
	public $log = [];

	/**
	 * SSHSession constructor.
	 *
	 * Constructor will call connect() method automatically if host parameter is not NULL.
	 *
	 * @param string|null $host (optional) Remote host to connect to. Must be either NULL or in format "host" or "host:port". Defaults to NULL.
	 * @throws \RuntimeException
	 * @throws SSHConnectionException
	 */
	public function __construct($host = null) {
		if( !extension_loaded("ssh2") )
			throw new \RuntimeException("SSH2 extension is required to use " . __CLASS__ . " class.");
		if( $host !== null )
			$this->connect($host);
	}

	/**
	 * Connects to a remote host.
	 *
	 * @param string $host Remote host to connect to. Must be in format either "host" or "host:port".
	 * @return $this
	 * @throws SSHConnectionException
	 */
	public function connect($host) {
		$this->host = $host;
		$this->authenticated = false;

		$info = explode(":", $host, 2);

		// This condition is required since ssh2_connect tries to connect to port 0 when second argument is present and equals to NULL.
		if( !empty($info[1]) )
			$this->session = ssh2_connect($info[0], $info[1]);
		else
			$this->session = ssh2_connect($info[0]);

		if( !$this->session )
			throw new SSHConnectionException("Couldn't connect to node SSH daemon.");

		return $this;
	}

	/**
	 * Returns uppercase hex SHA1 fingerprint of remote host.
	 *
	 * @return string
	 * @throws SSHConnectionException
	 */
	public function getFingerprint() {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open.");
		return strtoupper(ssh2_fingerprint($this->session, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX));
	}

	/**
	 * Checks remote host fingerprint and authenticates session using private and public key pair.
	 *
	 * @param string $user User name on remote host to authenticate session with.
	 * @param string $privateKeyFile Path to a private key file.
	 * @param string $publicKeyFile Path to a public key file.
	 * @param string|null $expectedFingerprint (optional) An expected hex SHA1 fingerprint string. May be either a 40 characters long hex string or NULL to disable fingerprint check. Defaults to NULL.
	 * @param string|null $passPhrase (optional) Password that must be used to decrypt encrypted private key file. Passing NULL means that password is not needed to decrypt private key file or the key file is not encrypted. Defaults to NULL.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 */
	public function authenticateUsingKeys($user, $privateKeyFile, $publicKeyFile, $expectedFingerprint = null, $passPhrase = null) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open.");
		if( !$this->authenticated ) {
			if( !is_file($privateKeyFile) )
				throw new SSHAuthenticationException("Private key file not found.");
			if( !is_readable($privateKeyFile) )
				throw new SSHAuthenticationException("Private key file is not readable.");
			if( !is_file($publicKeyFile) )
				throw new SSHAuthenticationException("Public key file not found.");
			if( !is_readable($publicKeyFile) )
				throw new SSHAuthenticationException("Public key file is not readable.");

			if( $expectedFingerprint !== null ) {
				$fingerprint = $this->getFingerprint();
				if( strtoupper($expectedFingerprint) !== $fingerprint )
					throw new SSHAuthenticationException("Authentication is not safe since SSH fingerprint ({$fingerprint}) differs from the one expected.");
			}
			$result = ssh2_auth_pubkey_file($this->session, $user, $publicKeyFile, $privateKeyFile, $passPhrase);
			if( !$result )
				throw new SSHAuthenticationException("Authentication failed");
			$this->authenticated = true;
			$this->user = $user;
		}
		return $this;
	}

	/**
	 * @param string $user User name on remote host to authenticate session with.
	 * @param string $password password to use when authenticating.
	 * @param string|null $expectedFingerprint (optional) An expected hex SHA1 fingerprint string. May be either a 40 characters long hex string or NULL to disable fingerprint check. Defaults to NULL.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 */
	public function authenticateUsingPassword($user, $password, $expectedFingerprint = null) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open.");
		if( !$this->authenticated ) {
			if( $expectedFingerprint !== null ) {
				$fingerprint = $this->getFingerprint();
				if( strtoupper($expectedFingerprint) !== $fingerprint )
					throw new SSHAuthenticationException("Authentication is not safe since SSH fingerprint ({$fingerprint}) differs from the one expected.");
			}
			$result = ssh2_auth_password($this->session, $user, $password);
			if( !$result )
				throw new SSHAuthenticationException("Authentication failed");
			$this->authenticated = true;
			$this->user = $user;
		}
		return $this;
	}

	/**
	 * @param string $command
	 * @param int $exitStatus (optional) (out) Executed command exit status code.
	 * @param string $stdOut (optional) (out) Executed command output to stdout.
	 * @param string $stdErr (optional) (out) Executed command output to stderr.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHExecException
	 */
	public function exec($command, &$exitStatus = 0, &$stdOut = "", &$stdErr = "") {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open");
		if( !$this->authenticated )
			throw new SSHAuthenticationException("SSH session is not authenticated");
		$suffix = ($command != "exit") ? ";echo -en \"\\n~SSHExecExitStatus=\$?~\"" : "";
		if( $this->logCommands && $command != "exit" )
			$this->log[] = "{$this->user}@{$this->host}# {$command}";
		if (!($stdOutStream = ssh2_exec($this->session, $command . $suffix)))
			throw new SSHExecException('SSH exec failed');
		$stdErrStream = ssh2_fetch_stream($stdOutStream, SSH2_STREAM_STDERR);
		stream_set_blocking($stdOutStream, true);
		stream_set_blocking($stdErrStream, true);
		$stdOutCombined = stream_get_contents($stdOutStream);
		$stdErrText = stream_get_contents($stdErrStream);
		fclose($stdErrStream);
		fclose($stdOutStream);
		if( $command == "exit" ) {
			$this->session = null;
			$this->authenticated = false;
			$stdOut = $stdOutCombined;
			$exitStatus = ($stdErrText === "") ? 0 : 1;
		}
		else {
			if( ! preg_match( "/^(.*)\n~SSHExecExitStatus=([^~]*)~$/s", $stdOutCombined, $matches ) )
				throw new SSHExecException("Could not get command exit status");
			$stdOut = $matches[1];
			$exitStatus = intval($matches[2]);
			if( $this->logConsole ) {
				if( $stdErr !== $stdOut )
					$this->log[] = $stdOut;
				if( $stdErr !== "" )
					$this->log[] = $stdErr;
				if( $exitStatus )
					$this->log[] = "NON-ZERO EXIT STATUS: {$exitStatus}";
			}
		}
		$stdErr = $stdErrText;
		return $this;
	}

	/**
	 * Disconnects from remote node.
	 *
	 * Calling this method is same as calling $sshSession->exec("exit").
	 *
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHExecException
	 */
	public function disconnect() {
		return $this->exec("exit");
	}

	/**
	 * Uploads file to the remote host.
	 *
	 * @param string $localFile Local path to the file to be uploaded.
	 * @param string $remoteFile Remote path for the file to be uploaded to.
	 * @param int $chmod (optional) Mode to apply on the remote file when it is created. Defaults to 0644.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHFileTransferException
	 */
	public function upload($localFile, $remoteFile, $chmod = 0644) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open");
		if( !$this->authenticated )
			throw new SSHAuthenticationException("SSH session is not authenticated");
		if( !ssh2_scp_send($this->session, $localFile, $remoteFile, $chmod) ) {
			if( $this->logFileTransfers )
				$this->log[] = "Failed to upload {$localFile} to {$remoteFile}";
			throw new SSHFileTransferException("Failed to upload file on remote host");
		}
		else if( $this->logFileTransfers )
			$this->log[] = "Uploaded {$localFile} to {$remoteFile}";
		return $this;
	}

	/**
	 * @param string $remoteFile Remote path to the file to be downloaded.
	 * @param string $localFile Local path for the file to be downloaded to.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHFileTransferException
	 */
	public function download($remoteFile, $localFile) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open");
		if( !$this->authenticated )
			throw new SSHAuthenticationException("SSH session is not authenticated");
		if( !ssh2_scp_recv($this->session, $remoteFile, $localFile) ) {
			if( $this->logFileTransfers )
				$this->log[] = "Failed to download {$remoteFile} to {$localFile}";
			throw new SSHFileTransferException("Failed to download file from remote host");
		}
		else if( $this->logFileTransfers )
			$this->log[] = "Downloaded {$remoteFile} to {$localFile}";
		return $this;
	}

	/**
	 * @param string $content Content to write to the remote file.
	 * @param string $remoteFile Remote path to the file to write content to.
	 * @param int $chmod (optional) Mode to apply on the remote file when it is created. Defaults to 0644.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHFileTransferException
	 */
	public function uploadString($content, $remoteFile, $chmod = 0644) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open");
		if( !$this->authenticated )
			throw new SSHAuthenticationException("SSH session is not authenticated");
		$fp = tmpfile();
		if( $fp === false ) {
			if( $this->logFileTransfers )
				$this->log[] = "Failed to create temporary file for uploading {$remoteFile} content";
			throw new SSHFileTransferException("Failed to create temporary file for sending to remote host");
		}
		$meta = stream_get_meta_data($fp);
		fwrite($fp, $content);
		$log = $this->logFileTransfers;
		$this->logFileTransfers = false;
		try {
			$this->upload($meta["uri"], $remoteFile, $chmod);
		}
		catch(SSHFileTransferException $ex) {
			if( $log )
				$this->log[] = "Failed to upload content to {$remoteFile}";
			throw new SSHFileTransferException("Failed to upload content to a remote file", 0, $ex);
		}
		finally {
			$this->logFileTransfers = $log;
			fclose($fp);
		}
		if( $log )
			$this->log[] = "Uploaded content to {$remoteFile}";
		return $this;
	}

	/**
	 * @param string $remoteFile Remote path to the file to read content from.
	 * @param string $content (out) Variable to write remote file content to.
	 * @return $this
	 * @throws SSHAuthenticationException
	 * @throws SSHConnectionException
	 * @throws SSHFileTransferException
	 */
	public function downloadString($remoteFile, &$content) {
		if( !$this->session )
			throw new SSHConnectionException("SSH session is not open");
		if( !$this->authenticated )
			throw new SSHAuthenticationException("SSH session is not authenticated");
		$tmpName = tempnam(sys_get_temp_dir(), "PHLSSDS");
		if( $tmpName === false ) {
			if( $this->logFileTransfers )
				$this->log[] = "Failed to create temporary file for downloading {$remoteFile} content";
			throw new SSHFileTransferException("Failed to create temporary file for receiving from remote host");
		}
		$log = $this->logFileTransfers;
		$this->logFileTransfers = false;
		try {
			$this->download($remoteFile, $tmpName);
			$content = file_get_contents($tmpName);
		}
		catch(SSHFileTransferException $ex) {
			if( $log )
				$this->log[] = "Failed to download content of {$remoteFile}";
			throw new SSHFileTransferException("Failed to download content of a remote file", 0, $ex);
		}
		finally {
			$this->logFileTransfers = $log;
			unlink($tmpName);
		}
		if( $log )
			$this->log[] = "Downloaded content of {$remoteFile}";
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isConnected() {
		return !!$this->session;
	}

	/**
	 * @return bool
	 */
	public function isAuthenticated() {
		return $this->authenticated;
	}

	/**
	 * Returns host of last connection attempt.
	 *
	 * @return string|null Host specified when connecting or NULL if no attempt was made yet.
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * Returns user used in session authentication.
	 *
	 * @return string|null Host specified when connecting or NULL if no attempt was made yet.
	 */
	public function getUser() {
		return $this->user;
	}
}