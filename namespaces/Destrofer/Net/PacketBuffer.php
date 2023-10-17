<?php

namespace Destrofer\Net;

use Destrofer\Net\Exceptions\InvalidPacketBufferException;
use Destrofer\Net\Exceptions\InvalidPacketException;
use Destrofer\Net\Exceptions\PacketBufferNotReadyException;

class PacketBuffer {
	/**
	 * @var string
	 */
	public $data = "";
	/**
	 * @var int
	 */
	public $offset = 0;
	/**
	 * @var int
	 */
	public $length = 8;

	/**
	 * @return Packet
	 * @throws InvalidPacketException
	 * @throws PacketBufferNotReadyException
	 * @throws InvalidPacketBufferException
	 */
	public function getPacket() {
		if( $this->length < 8 )
			throw new InvalidPacketBufferException("Invalid data in buffer: expected packet data size is too small");
		$len = strlen($this->data);
		if( $len < $this->length )
			throw new PacketBufferNotReadyException("Packet buffer is not ready");
		if( $len > $this->length )
			throw new InvalidPacketException("Packet buffer has too much data");
		$info = unpack("Vid/Vlen", $this->data);
		if( $info["len"] !== $this->length - 8 )
			throw new InvalidPacketException("Invalid data in buffer: buffered data size doesn't match packet length");
		return new Packet($info["id"], substr($this->data, 8));
	}

	public function reset() {
		$this->data = "";
		$this->offset = 0;
		$this->length = 8;
	}
}
