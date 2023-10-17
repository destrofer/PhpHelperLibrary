<?php

namespace Destrofer\Net;

class Packet {
	/**
	 * @var int
	 */
	public $id;
	/**
	 * @var string
	 */
	public $data;

	/**
	 * @param int $id A 32bit integer packet identifier.
	 * @param string $data Packet payload.
	 */
	public function __construct($id, $data = "") {
		$this->id = $id;
		$this->data = $data;
	}

	/**
	 * @return PacketBuffer
	 */
	public function createBuffer() {
		$buffer = new PacketBuffer();
		$len = strlen($this->data);
		$buffer->length = 8 + $len;
		$buffer->data = pack("VV", $this->id, $len) . $this->data;
		return $buffer;
	}
}