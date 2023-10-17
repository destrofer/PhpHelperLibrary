<?php

namespace Destrofer\Net;

use Destrofer\Net\Exceptions\InvalidPacketBufferException;
use Destrofer\Net\Exceptions\NoConnectionException;
use Destrofer\Net\Exceptions\NonBlockingSocketsNotSupportedException;
use Destrofer\Net\Exceptions\PacketBufferNotReadyException;
use Destrofer\Net\Exceptions\SocketErrorException;

abstract class AsyncServer extends SocketHandler {
	/** @var AsyncClient[] */
	private $clients = [];

	public function __construct() {
	}

	/**
	 * Creates server instance and starts listening for TCP connections.
	 * @param int $port Port to listen on.
	 * @param string $bindAddress Address (interface) to listen on. Defaults to "0.0.0.0", which means to listen on all interfaces.
	 * @throws SocketErrorException
	 * @throws NonBlockingSocketsNotSupportedException
	 */
	public function listen($port, $bindAddress = "0.0.0.0", $backlog = 8) {
		$this->createSocket();

		if( $this->logger ) $this->logger->notice("Listening on port {$port} ip {$bindAddress}");
		if( !socket_bind($this->socket, $bindAddress, $port) ) {
			$this->updateLastError();
			throw new SocketErrorException("socket_bind(ip={$bindAddress}, port={$port}) failed: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
		if( !socket_listen($this->socket, $backlog) ) {
			$this->updateLastError();
			throw new SocketErrorException("socket_listen(backlog={$backlog}) failed: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
	}

	protected function setSocketOptions() {
		if( !socket_set_nonblock($this->socket) ) {
			$this->updateLastError();
			$msg = "Unable to set socket to be non-blocking: [{$this->lastErrorCode}] {$this->lastErrorMessage}";
			if( $this->logger ) $this->logger->error($msg);
			throw new NonBlockingSocketsNotSupportedException($msg);
		}
		if( !socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 0, "usec" => 0]) ) {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Unable to change socket RCV timeout: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
		if( !socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ["sec" => 0, "usec" => 0]) ) {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Unable to change socket SND timeout: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
		if( !socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1) ) {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Disabling TCP delay failed: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
	}

	/**
	 * @return AsyncClient
	 */
	protected abstract function createClient();

	/**
	 * @param resource|Socket $socket
	 * @return void
	 * @throws NonBlockingSocketsNotSupportedException
	 */
	protected function onConnect($socket) {
		$client = $this->createClient();
		$client->logger = $this->logger;
		if( $this->logger ) $this->logger->debug("Adding client {$client->id} to pool");
		$this->clients[$client->id] = $client;
		$client->accept($socket);
	}

	/**
	 * @param AsyncClient $client
	 * @return void
	 */
	protected function onDisconnect(AsyncClient $client) {
		if( $this->logger ) $this->logger->debug("Removing client {$client->id} from pool");
		unset($this->clients[$client->id]);
	}

	/**
	 * @return void
	 * @throws InvalidPacketBufferException Should never be thrown unless class extending {@see AsyncClient} interferes with I/O handling.
	 * @throws NoConnectionException Should never be thrown.
	 * @throws PacketBufferNotReadyException Should never be thrown unless class extending {@see AsyncClient} interferes with I/O handling.
	 * @throws NonBlockingSocketsNotSupportedException Should never be thrown since AsyncServer was already successfully created.
	 * @throws SocketErrorException
	 */
	public function doLoop() {
		foreach( $this->clients as $client ) {
			if( $client->isValid() )
				$client->doLoop();
			else
				$this->onDisconnect($client);
		}
		if( !$this->socket )
			return;
		while( $newSocket = @socket_accept($this->socket) ) {
			$this->onConnect($newSocket);
		}
		$this->updateLastError();
		if( $this->lastErrorCode && !$this->isLastErrorNonBlockingBusy() ) {
			$msg = "Socket error while using socket_accept(): [{$this->lastErrorCode}] {$this->lastErrorMessage}";
			if( $this->logger ) $this->logger->error($msg);
			throw new SocketErrorException($msg);
		}
	}

	/**
	 * @return AsyncClient[]
	 */
	public function getClients() {
		return $this->clients;
	}
}
