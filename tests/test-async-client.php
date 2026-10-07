<?php

use Destrofer\Debugging\Logger;
use Destrofer\Net\AsyncClient;
use Destrofer\Net\Packet;

include __DIR__ . "/../vendor/autoload.php";

class TestClient extends AsyncClient {
	protected function onPacketReceived(Packet $packet)
	{
		if( $packet->id === 0 ) {
			if( $this->logger ) $this->logger->notice("Remote says: {$packet->data}");
			$reply = str_shuffle($packet->data);
			if( $this->logger ) $this->logger->notice("I reply: {$reply}");
			$this->sendPacket(new Packet(1, $reply));
		}
		else if( $packet->id === 1 ) {
			if ($this->logger) $this->logger->notice("Remote replies: {$packet->data}");
			$this->disconnect();
		}
	}

	protected function onConnect() {
		parent::onConnect();
		$word = $this->server ? "world" : "hello";
		if ($this->logger) $this->logger->notice("I say: {$word}");
		$this->sendPacket(new Packet(0, $word));
	}

	public function validatePacketHeader($packetId, $payloadSize) {
		if( $packetId < 0 || $packetId > 1 || $payloadSize !== 5 )
			return false;
		return parent::validatePacketHeader($packetId, $payloadSize);
	}
}

$client = new TestClient();
$client->logger = new Logger(__DIR__ . "/test-data/test-async-client.log", true);
$client->connect("127.0.0.1", 2000);
while($client->isValid()) {
	$client->doLoop();
	usleep(100000);
}
