<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Web;

class Proxy {
	public $ip;
	public $port;
	public $type;
	public $country;
	public $username;
	public $password;

	public function __construct($ip = null, $port = null, $type = null, $country = null, $username = null, $password = null) {
		$this->ip = $ip;
		$this->port = $port;
		$this->type = $type;
		$this->country = $country;
		$this->username = $username;
		$this->password = $password;
	}

	public function getCurlType() {
		switch( $this->type ) {
			case "socks4": return CURLPROXY_SOCKS4;
			case "socks5": return CURLPROXY_SOCKS5;
		}
		return CURLPROXY_HTTP;
	}
}