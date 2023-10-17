<?php

namespace Destrofer\Net;

use Exception;

class IPAddress {
	/** @var int[] */
	public $parts = array();
	public $isV6;

	/**
	 * @param string $ip
	 */
	public function __construct($ip) {
		$splitParts = explode(".", (string)$ip);
		$this->parts = array();
		if( count($splitParts) == 4 ) {
			foreach( $splitParts as $part ) {
				if( !is_numeric($part) )
					break;
				$num = intval($part);
				if( $num < 0 || $num > 255 )
					break;
				$this->parts[] = $num;
			}
			if( count($this->parts) !== 4 )
				$this->parts = array();
		}
		else {
			$hasOmittedParts = true;
			$nonOmittedParts = explode("::", $ip);
			if( count($nonOmittedParts) === 1 ) {
				$nonOmittedParts[] = "";
				$hasOmittedParts = false;
			}
			if( count($nonOmittedParts) === 2 ) {
				$splitParts = ($nonOmittedParts[0] === "") ? array() : explode(":", $nonOmittedParts[0]);
				$hasErrors = false;
				if( ($hasOmittedParts && count($splitParts) < 8) || (!$hasOmittedParts && count($splitParts) === 8) ) {
					foreach( $splitParts as $part ) {
						if( !preg_match("/^[0-9a-f]{1,4}$/i", $part) ) {
							$hasErrors = true;
							break;
						}
						$num = hexdec($part);
						if( $num < 0 || $num > 65535 ) {
							$hasErrors = true;
							break;
						}
						$this->parts[] = $num;
					}
				}
				if( !$hasErrors && $hasOmittedParts ) {
					$splitParts = ($nonOmittedParts[1] === "") ? array() : explode(":", $nonOmittedParts[1]);
					if( count($this->parts) < 8 ) {
						$omittedLength = 8 - count($splitParts) - count($this->parts);
						if( $omittedLength > 0 ) {
							for( $i = 0; $i < $omittedLength; $i++ )
								$this->parts[] = 0;
							foreach( $splitParts as $part ) {
								if( !preg_match("/^[0-9a-f]{1,4}$/i", $part) )
									break;
								$num = hexdec($part);
								if( $num < 0 || $num > 65535 )
									break;
								$this->parts[] = $num;
							}
						}
					}
				}
			}
			if( count($this->parts) !== 8 )
				$this->parts = array();
			else
				$this->isV6 = true;
		}
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return $this->parts && count($this->parts) === ($this->isV6 ? 8 : 4);
	}

	/**
	 * @return null|string
	 */
	public function getNetClass($numeric = false) {
		if( $this->isV6 || !$this->isValid() )
			return null;
		if( ($this->parts[0] & 128) == 0 )
			return $numeric ? 8 : "/8";
		if( ($this->parts[0] & 64) == 0 )
			return $numeric ? 16 : "/16";
		if( ($this->parts[0] & 32) == 0 )
			return $numeric ? 24 : "/24";
		return $numeric ? 32 : "/32";
	}

	public function getNetAddress($forceClass = null) {
		if( $this->isV6 || !$this->isValid() )
			return null;
		$class = ($forceClass !== null) ? $forceClass : $this->getNetClass();
		if( !is_numeric($class) )
			$class = ltrim($class, "/");
		$bits = (int)$class;
		if( $bits < 1 || $bits > 32 )
			throw new Exception("Invalid bit count in network address");

		$netIp = clone $this;
		for( $i = 0; $i < 4; $i++, $bits -= 8 ) {
			$mask = (255 << (8 - min(8, $bits))) & 255;
			/*
			echo sprintf("%08d / %08d => %08d / %s\n",
				(int)decbin($mask),
				(int)decbin($netIp->parts[$i]),
				(int)decbin($netIp->parts[$i] & $mask),
				$bits
			);
			*/
			$netIp->parts[$i] &= $mask;
		}

		return $netIp;
	}

	public function isInNetwork($netAddr) {
		$parts = explode("/", $netAddr);
		if( count($parts) !== 2 )
			throw new Exception("Invalid network address. Use 0.0.0.0/0 format.");
		list($netIp, $bits) = $parts;
		if( !is_numeric($bits) || $bits < 1 || $bits > 32 )
			throw new Exception("Invalid bit count in network address");
		$netIp = IPAddress::parse($netIp);
		if( !$netIp || !$netIp->isValid()  )
			throw new Exception("Invalid network IP address");
		if( $netIp->isV6 || $this->isV6 )
			throw new Exception("IPv6 is not supported");

		for( $i = 0; $i < 4 && $bits > 0; $i++, $bits -= 8 ) {
			$mask = (255 << (8 - min(8, $bits))) & 255;
			/* echo sprintf("%08d / %08d => %08d / %08d => %08d / %s\n",
				(int)decbin($mask),
				(int)decbin($netIp->parts[$i]),
				(int)decbin($netIp->parts[$i] & $mask),
				(int)decbin($this->parts[$i]),
				(int)decbin($this->parts[$i] & $mask),
				$bits
			); */
			if( ($netIp->parts[$i] & $mask) !== ($this->parts[$i] & $mask) )
				return false;
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function isLoopBackAddress() {
		if( $this->isV6 )
			return $this->parts[0] == 0 && $this->parts[1] == 0 && $this->parts[2] == 0 && $this->parts[3] == 0 && $this->parts[4] == 0 && $this->parts[5] == 0 && $this->parts[6] == 0 && $this->parts[7] == 1;
		return $this->parts[0] == 127;
	}

	/**
	 * @return bool
	 */
	public function isUnspecifiedAddress() {
		if( $this->isV6 )
			return $this->parts[0] == 0 && $this->parts[1] == 0 && $this->parts[2] == 0 && $this->parts[3] == 0 && $this->parts[4] == 0 && $this->parts[5] == 0 && $this->parts[6] == 0 && $this->parts[7] == 0;
		return $this->parts[0] == 0 && $this->parts[1] == 0 && $this->parts[2] == 0 && $this->parts[3] == 0 && $this->parts[4] == 0;
	}

	/**
	 * @return bool
	 */
	public function isLocalAddress() {
		if( !count($this->parts) )
			return false;
		if( $this->isLoopBackAddress() )
			return true;
		if( $this->isV6 )
			return (
				(($this->parts[0] & 0xFE00) == 0xFC00) || // Private network
				(($this->parts[0] & 0xFFC0) == 0xFE80) // Link
			);
		return (
			($this->parts[0] == 10) ||
			($this->parts[0] == 192 && $this->parts[1] == 168) ||
			($this->parts[0] == 100 && $this->parts[1] >= 64 && $this->parts[1] <= 127) ||
			($this->parts[0] == 172 && $this->parts[1] >= 16 && $this->parts[1] <= 31)
		);
	}

	/**
	 * @return bool
	 */
	public function isNetworkAddress() {
		return count($this->parts) ? $this->parts[count($this->parts) - 1] === 0 : false;
	}

	/**
	 * @return bool
	 */
	public function isBroadcastAddress() {
		return count($this->parts) ? $this->parts[count($this->parts) - 1] === ($this->isV6 ? 65535 : 255) : false;
	}


	/**
	 * @param string $ip
	 * @return null|IPAddress
	 */
	public static function parse($ip) {
		$ipInst = new IPAddress((string)$ip);
		return $ipInst->isValid() ? $ipInst : null;
	}

	/**
	 * @param string|IPAddress $ip1
	 * @param string|IPAddress $ip2
	 * @return int
	 */
	public static function compare($ip1, $ip2) {
		$ip1Inst = ($ip1 instanceof IPAddress) ? $ip1 : new IPAddress($ip1);
		$ip2Inst = ($ip2 instanceof IPAddress) ? $ip2 : new IPAddress($ip2);
		if( count($ip1Inst->parts) == count($ip2Inst->parts) ) {
			for( $i = 0, $il = count($ip1Inst->parts); $i < $il; $i++ ) {
				if( $ip1Inst->parts[$i] < $ip2Inst->parts[$i] )
					return -1;
				if( $ip1Inst->parts[$i] > $ip2Inst->parts[$i] )
					return 1;
			}
			return 0;
		}
		return (count($ip1Inst->parts) < count($ip2Inst->parts)) ? -1 : 1;
	}

	public static function compareNet($net1, $net2) {
		return self::compare(preg_replace("#/[0-9]+$#", "", $net1), preg_replace("#/[0-9]+$#", "", $net2));
	}

	/**
	 * @return string
	 */
	public function __toString() {
		if( !$this->parts )
			return "";
		if( $this->isV6 ) {
			$omitted = false;
			$omitting = false;
			$parts = array();
			for( $i = 0, $il = count($this->parts); $i < $il; $i++ ) {
				$part = $this->parts[$i];
				if( $part === 0 ) {
					if( $omitted ) {
						if( $omitting )
							continue;
						$parts[] = "0";
					}
					else {
						if( $i == 0 )
							$parts[] = ":";
						else
							$parts[] = "";
						$omitted = true;
						$omitting = true;
					}
				}
				else {
					$omitting = false;
					$parts[] = dechex($part);
				}
			}
			if( $omitting && count($parts) < 8 )
				$parts[] = "";
			return implode(":", $parts);
		}
		return implode(".", $this->parts);
	}
}

