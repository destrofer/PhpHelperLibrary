<?php

namespace Destrofer\Net;

use Destrofer\Debugging\Logger;
use Destrofer\Net\Exceptions\ConnectionLostException;
use Destrofer\Net\Exceptions\InvalidPacketBufferException;
use Destrofer\Net\Exceptions\InvalidPacketException;
use Destrofer\Net\Exceptions\NoConnectionException;
use Destrofer\Net\Exceptions\NonBlockingSocketsNotSupportedException;
use Destrofer\Net\Exceptions\PacketBufferNotReadyException;
use Destrofer\Net\Exceptions\SocketErrorException;
use Exception;

abstract class AsyncClient extends SocketHandler {
	const READ_BUFFER_SIZE = 65536;
	const WRITE_BUFFER_SIZE = 65536;

	const STATE_UNCONNECTED = 0;
	const STATE_CONNECTING = 1;
	const STATE_CONNECTED = 2;

	/**
	 * @var int A unique AsyncClient instance identifier.
	 */
	public $id;

	/**
	 * @var int
	 */
	protected $state = self::STATE_UNCONNECTED;

	/**
	 * @var AsyncServer|null
	 */
	protected $server = null;

	/**
	 * @var PacketBuffer[]
	 */
	private $sendQueue = [];
	/**
	 * @var PacketBuffer|null
	 */
	private $currentRecvBuffer = null;
	/**
	 * @var PacketBuffer|null
	 */
	private $currentSendBuffer = null;

	/**
	 * @var string|null
	 */
	private $remoteAddr = null;
	/**
	 * @var int|null
	 */
	private $remotePort = null;

	/**
	 * @var int
	 */
	private $connectTimeoutTime = 0;

	/**
	 * @param AsyncServer|null $server
	 */
	public function __construct($server = null) {
		static $nextId = 1;
		$this->id = $nextId++;
		$this->server = $server;
		$this->resetClient();
	}

	/**
	 * Sets up client from a socket accepted by {@see socket_accept()}.
	 * @param resource|Socket $socket
	 * @throws NonBlockingSocketsNotSupportedException
	 * @throws Exception
	 */
	public function accept($socket) {
		if( $this->socket )
			throw new Exception("Client can be bound only to one socket");
		$this->socket = $socket;
		$this->state = self::STATE_CONNECTED;
		if( @socket_getpeername($socket, $this->remoteAddr, $this->remotePort) ) {
			if( $this->logger ) $this->logger->notice("Accepting connection from {$this->remoteAddr} port {$this->remotePort}");
		}
		else {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Accepting connection from unknown remote address (socket_getpeername() error)");
		}
		$this->setSocketOptions();
		$this->onConnect();
	}

	/**
	 * Creates a socket, configures it for asynchronous (non-blocking) usage, and tries to connect to remote host.
	 * This method is asynchronous (non-blocking). Whether connection is established or not will be known only after one
	 * of {@see AsyncCLient::doLoop()} method calls.
	 * @param string $remoteAddress Remote address to connect to.
	 * @param int $remotePort Remote port to connect to.
	 * @param int $timeout Number of seconds to consider connection timed out.
	 * @throws NonBlockingSocketsNotSupportedException
	 * @throws SocketErrorException
	 */
	public function connect($remoteAddress, $remotePort, $timeout = 30) {
		$this->createSocket();

		$this->state = self::STATE_CONNECTING;
		$this->remoteAddr = $remoteAddress;
		$this->remotePort = $remotePort;

		if( $this->logger ) $this->logger->notice("Connecting to {$remoteAddress} port {$remotePort}");
		if( !@socket_connect($this->socket, $this->remoteAddr, $this->remotePort) ) {
			$this->updateLastError();
			if( !$this->isLastErrorNonBlockingBusy() ) {
				$msg = "socket_connect(ip={$remoteAddress}, port={$remotePort}) error: [{$this->lastErrorCode}] {$this->lastErrorMessage}";
				if( $this->logger ) $this->logger->error($msg);
				throw new SocketErrorException($msg);
			}
		}

		$this->connectTimeoutTime = time() + $timeout;
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
		if( !socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, self::READ_BUFFER_SIZE) ) {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Read buffer size change failed: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
		if( !socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, self::WRITE_BUFFER_SIZE) ) {
			$this->updateLastError();
			if( $this->logger ) $this->logger->warning("Write buffer size change failed: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
		}
	}

	/**
	 * Called when packet header (8 bytes) is received, but before reading packet payload.
	 * @param int $packetId
	 * @param int $payloadSize
	 * @return bool Must return true if packet header is valid (default behaviour if this method is not extended), or
	 *      false if packet header is invalid. Returning false from this method makes input steam reader to throw
	 *      {@see InvalidPacketException}.
	 */
	public function validatePacketHeader($packetId, $payloadSize) {
		return true;
	}

	/**
	 * Called when client receives a packet from connected remote host.
	 * @param Packet $packet
	 * @return void
	 */
	abstract protected function onPacketReceived(Packet $packet);

	/**
	 * Add packet to internal queue for sending to remote host. Max queue size is affected only by current PHP memory
	 * limit.
	 * @param Packet $packet
	 * @return void
	 */
	public function sendPacket(Packet $packet) {
		if( $this->logger && $this->logger->minLogLevel <= Logger::LEVEL_VERBOSE )
			$this->logger->verbose("Enqueueing to send packet 0x" . dechex($packet->id) . " (" . strlen($packet->data) . " bytes payload)");
		$this->sendQueue[] = $packet->createBuffer();
	}

	protected function onConnect() {
		if( $this->logger ) $this->logger->debug("Client::onConnect()");
	}

	protected function onDisconnect() {
		if( $this->logger ) $this->logger->debug("Client::onDisconnect()");
	}

	public function isConnected() {
		return $this->state === self::STATE_CONNECTED;
	}

	/**
	 * @return void
	 * @throws InvalidPacketBufferException Should never be thrown unless extending class interferes with I/O handling.
	 * @throws PacketBufferNotReadyException Should never be thrown unless extending class interferes with I/O handling.
	 * @throws NoConnectionException Thrown if client is not valid. Check {@see AsyncClient::isValid()} before calling this method.
	 */
	public final function doLoop() {
		if( !$this->socket )
			throw new NoConnectionException();

		try {
			$this->doCustomLoop();
			if( !$this->socket )
				return; // Forcefully disconnected while doing a custom internal loop cycle.

			if( $this->state === self::STATE_CONNECTING ) {
				$read = null;
				$write = [$this->socket];
				$except = null;
				$res = socket_select($read, $write, $except, 0, 0);
				if( $res === false ) {
					$this->updateLastError();
					if( $this->isLastErrorNonBlockingBusy() )
						return;
					if( $this->isLastErrorConnectionLoss() )
						throw new ConnectionLostException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
					throw new SocketErrorException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
				}
				if( !$res ) {
					if( $this->connectTimeoutTime < time() )
						throw new ConnectionLostException("Connection timed out");
					return;
				}
				// At this point it is impossible to detect successful connection on linux - you won't get "connection
				// refused" error until you try to read something from the socket. In such a case client is considered
				// connected and then loses connection right away.
				if( $this->logger ) $this->logger->notice("Connection established with {$this->remoteAddr} port {$this->remotePort}");
				$this->state = self::STATE_CONNECTED;
				$this->onConnect();
			}

			if( $this->state === self::STATE_CONNECTED ) {
				$read = [$this->socket];
				$write = (empty($this->sendQueue) && !$this->currentSendBuffer) ? null : [$this->socket];
				$except = null;
				$res = socket_select($read, $write, $except, 0, 0);
				if( $res === false ) {
					$this->updateLastError();
					if( $this->isLastErrorNonBlockingBusy() )
						return;
					if( $this->isLastErrorConnectionLoss() )
						throw new ConnectionLostException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
					throw new SocketErrorException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
				}
				if( !$res )
					return;

				if( !empty($read) ) {
					foreach( $read as $socket ) {
						while( $this->currentRecvBuffer ) {
							$res = $this->readFromSocket($this->currentRecvBuffer);
							if( !$this->socket )
								return null; // Forcefully disconnected while reading packet.
							if( $res === false ) {
								$this->updateLastError();
								if( $this->isLastErrorNonBlockingBusy() )
									break;
								if( $this->isLastErrorConnectionLoss() )
									throw new ConnectionLostException("Connection lost: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
								throw new SocketErrorException("Socket error: [{$this->lastErrorCode}] {$this->lastErrorMessage}");
							}
							if( $res === true ) {
								// packet is ready
								$packet = $this->currentRecvBuffer->getPacket();
								if( $this->logger && $this->logger->minLogLevel <= Logger::LEVEL_VERBOSE )
									$this->logger->verbose("Received packet 0x" . dechex($packet->id) . " (" . strlen($packet->data) . " bytes payload)");
								$this->onPacketReceived($packet);
								if( !$this->socket )
									return; // Forcefully disconnected while handling received packet.
								$this->currentRecvBuffer->reset(); // prepare for receiving next packet
							}
							else {
								break; // read as much as could
							}
						}
					}
				}

				if( !empty($write) ) {
					foreach( $write as $socket ) {
						while( !empty($this->sendQueue) || $this->currentSendBuffer ) {
							if( !$this->currentSendBuffer )
								$this->currentSendBuffer = array_shift($this->sendQueue);
							$res = $this->writeToSocket($this->currentSendBuffer);
							if( $res === false ) {
								$this->updateLastError();
								if( $this->isLastErrorNonBlockingBusy() )
									break;
								if( $this->isLastErrorConnectionLoss() )
									throw new ConnectionLostException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
								throw new SocketErrorException("[{$this->lastErrorCode}] {$this->lastErrorMessage}");
							}
							if( $res === true ) {
								// packet is fully sent
								$this->currentSendBuffer = null;
								// if( $this->logger ) $this->logger->verbose("Packet fully sent");
							}
							else {
								// only part of packet was sent until socket write buffer got filled
								break;
							}
						}
					}
				}
			}
		}
		catch(SocketErrorException $ex) {
			if( $this->logger ) $this->logger->error((string)$ex);
			$wasConnected = $this->isConnected();
			$this->resetClient();
			if( $wasConnected )
				$this->onDisconnect();
		}
		catch(InvalidPacketException $ex) {
			if( $this->logger ) $this->logger->debug((string)$ex);
			$wasConnected = $this->isConnected();
			$this->resetClient();
			if( $wasConnected )
				$this->onDisconnect();
		}
		catch(ConnectionLostException $ex) {
			if( $this->logger ) {
				$this->logger->debug($ex->getMessage());
				$this->logger->notice("Connection with {$this->remoteAddr} port {$this->remotePort} lost");
			}
			$wasConnected = $this->isConnected();
			$this->resetClient();
			if( $wasConnected )
				$this->onDisconnect();
		}
	}


	/**
	 * @param PacketBuffer $buffer Buffer to write data to.
	 * @return bool|null Returns false on error, null if buffer is not filled yet and socket input buffer is currently empty, and true if buffer is filled and ready to create a packet.
	 * @throws ConnectionLostException
	 * @throws InvalidPacketException
	 */
	private function readFromSocket(PacketBuffer $buffer) {
		if( !$this->socket )
			throw new ConnectionLostException("Not connected");
		$first = true;
		while( $buffer->offset < $buffer->length ) {
			$expectedLen = min(self::READ_BUFFER_SIZE, $buffer->length - $buffer->offset);
			$data = @socket_read($this->socket, $expectedLen);
			if( $data === false )
				return false; // error
			if( $data === "" ) {
				if( $first ) // on linux instead of an error socket_read() function returns an empty string when connection is lost
					throw new ConnectionLostException("First socket_read() after socket_select() returned an empty string");
				return null; // nothing to read from the socket buffer
			}
			$first = false;
			$len = strlen($data);
			if( $this->logger ) $this->logger->verbose("{$len} bytes received");
			$buffer->offset += $len;
			$buffer->data .= $data;
			if( $len < $expectedLen || $buffer->offset < 8 )
				return null;
			if( $buffer->offset === 8 && $buffer->length === 8 ) {
				$info = unpack("Vid/Vlen", $buffer->data);
				if( !$this->validatePacketHeader($info["id"], $info["len"]) )
					throw new InvalidPacketException("Received packet header didn't pass initial validation. packetId=0x" . dechex($info["id"]) . ", payloadSize=" . $info["len"] . " bytes");
				if( !$this->socket )
					return null; // Forcefully disconnected while validating packet header.
				$buffer->length = 8 + $info["len"];
			}
		}
		return true;
	}

	/**
	 * @param PacketBuffer $buffer Buffer to read data from.
	 * @return bool|int Returns false on error, integer number of bytes sent if buffer is not fully sent yet (may return 0 if socket output buffer is full), and true if buffer is fully sent.
	 * @throws ConnectionLostException
	 */
	private function writeToSocket(PacketBuffer $buffer) {
		if( !$this->socket )
			throw new ConnectionLostException("Not connected");
		do {
			$len = @socket_write($this->socket, substr($buffer->data, $buffer->offset, min(self::WRITE_BUFFER_SIZE, $buffer->length - $buffer->offset)));
			if( $len === false )
				return false;
			if( $len > 0 && $this->logger ) $this->logger->verbose("{$len} bytes sent");
			$buffer->offset += $len;
		} while($len > 0);
		return ($buffer->offset < $buffer->length) ? $len : true;
	}

	/**
	 * Called by {@see AsyncCLient::doLoop()} before handling socket I/O. By default does nothing. Override this method
	 * to handle your client internal states.
	 * @return void
	 */
	protected function doCustomLoop() {
	}

	/**
	 * @return void
	 */
	protected function resetClient() {
		if( $this->socket ) {
			if( $this->logger ) $this->logger->verbose("Closing socket");
			@socket_close($this->socket);
		}
		$this->socket = null;
		$this->state = self::STATE_UNCONNECTED;
		$this->currentRecvBuffer = new PacketBuffer();
		$this->currentSendBuffer = null;
		$this->sendQueue = [];
	}

	public function disconnect() {
		$wasConnected = $this->isConnected();
		if( $wasConnected && $this->logger ) $this->logger->notice("Disconnecting from {$this->remoteAddr} port {$this->remotePort}");
		$this->resetClient();
		if( $wasConnected )
			$this->onDisconnect();
	}
}
