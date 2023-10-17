<?php

namespace Destrofer\Net;

use Destrofer\Debugging\Logger;
use Destrofer\Net\Exceptions\SocketErrorException;
use Exception;

abstract class SocketHandler {
	/**
	 * @var resource|Socket|null
	 */
	protected $socket = null;
	/**
	 * @var int
	 */
	public $lastErrorCode = 0;
	/**
	 * @var string
	 */
	public $lastErrorMessage = "";

	/**
	 * @var Logger|null
	 */
	public $logger = null;

	public function __destruct() {
		if( $this->logger ) $this->logger->verbose("Socket handler instance is being destroyed");
		if( $this->socket ) {
			if( $this->logger ) $this->logger->verbose("Closing socket");
			@socket_close($this->socket);
		}
	}

	public function isValid() {
		return !!$this->socket;
	}

	/**
	 * Fills in lastErrorCode and lastErrorMessage fields with information about last socket error.
	 */
	protected function updateLastError() {
		if( $this->socket ) {
			$this->lastErrorCode = socket_last_error($this->socket);
			$this->lastErrorMessage = socket_strerror($this->lastErrorCode);
			socket_clear_error($this->socket);
		}
		else {
			$this->lastErrorCode = -1;
			$this->lastErrorMessage = "Unable to create socket";
		}
	}

	/**
	 * Checks if last socket error is related to connection loss.
	 * @return bool
	 */
	protected function isLastErrorConnectionLoss() {
		return (
			$this->lastErrorCode === 10054 // Windows: An existing connection was forcibly closed by the remote host
			|| $this->lastErrorCode === SOCKET_ECONNABORTED
			|| $this->lastErrorCode === SOCKET_ECONNRESET // Linux: connection reset - but it seems to never happen. Instead of this error reading from socket returns 0 bytes while socket_select() tells that there is something to read.
			|| $this->lastErrorCode === SOCKET_ECONNREFUSED
			|| $this->lastErrorCode === SOCKET_ETIMEDOUT
		);
	}

	/**
	 * Errors of this type can be safely ignored: usually it happens when trying to read from or write to socket I/O
	 * buffers while there is nothing to read or while write buffer is full.
	 * @return bool
	 */
	protected function isLastErrorNonBlockingBusy() {
		return ( $this->lastErrorCode === 10035 // Windows: A non-blocking socket operation could not be completed immediately
			|| $this->lastErrorCode === SOCKET_EINPROGRESS
			|| $this->lastErrorCode === SOCKET_EALREADY
			|| $this->lastErrorCode === SOCKET_EAGAIN // Linux: Resource temporarily unavailable
		);
	}

	/**
	 * @return void
	 * @throws SocketErrorException
	 * @throws Exception
	 */
	protected function createSocket() {
		if( $this->socket )
			throw new Exception("Socket is already created");
		if( $this->logger ) $this->logger->debug("Creating a socket");
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if( !$this->socket ) {
			$this->updateLastError();
			throw new SocketErrorException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
		$this->setSocketOptions();
	}

	/**
	 * @return void
	 */
	protected abstract function setSocketOptions();

	/**
	 * Checks socket state, and handles socket I/O. This method is non-blocking.
	 * @return void
	 */
	public abstract function doLoop();
}
