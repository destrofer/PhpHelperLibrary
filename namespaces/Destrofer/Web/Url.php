<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Web;
use \Exception;

class Url {
	public $scheme = "http";
	public $user = null;
	public $pass = null;
	public $host = null;
	public $port = null;
	public $path = null;
	public $query = array();
	public $fragment = null;
	public $pathIsLocal = false;

	public static $basePath = '/';
	private static $_currentUrl = null;

	public function __construct($url = null, $relativeToBase = null) {
		if( $url instanceof Url ) {
			$this->scheme = $url->scheme;
			$this->user = $url->user;
			$this->pass = $url->pass;
			$this->host = $url->host;
			$this->port = $url->port;
			$this->path = $url->path;
			$this->query = $url->query;
			$this->fragment = $url->fragment;
			$this->pathIsLocal = $url->pathIsLocal;
		}
		else if( is_string($url) ) {
			if( empty($url) ) {
				$this->pathIsLocal = true;
			}
			else {
				$parsedData = self::parse_url($url);
				if( !is_array($parsedData) )
					throw new Exception($url, "Cannot parse the given URL");
				foreach( $parsedData as $k => $v ) {
					if( $k == 'query' ) {
						$this->query = array();
						parse_str($v, $this->query);
					}
					else
						$this->$k = $v;
				}
				if( $relativeToBase === null && !isset($parsedData['host']) )
					$relativeToBase = (empty($parsedData['path']) || $parsedData['path'][0] != '/');

				if( !$relativeToBase && isset($_SERVER['HTTP_HOST']) && ($this->host === null || $this->host === $_SERVER['HTTP_HOST']) ) {
					$len = strlen(self::$basePath);
					if( strlen($this->path) >= $len && substr($this->path, 0, $len) === self::$basePath ) {
						$this->path = substr($this->path, $len);
						$this->pathIsLocal = true;
					}
				}
				else if( $relativeToBase )
					$this->pathIsLocal = true;
			}
		}
	}

	public static function parse_url($url, $component = -1) {
		if( preg_match("=^
			(?:([a-z0-9]*:)//|//)? # scheme
			([^@:]+(?::[^@]*)?@)? # user:pass
			(?:([\\p{L}0-9](?:[\\p{L}0-9\\-]*[\\p{L}0-9])?(?:\\.[\\p{L}0-9](?:[\\p{L}0-9\\-]*[\\p{L}0-9])?)*)(?::([0-9]+))?)? # host:port
			(/[^\\?\\#]*)? # path
			(\\?[^\\#]*)? # query
			(\\#.*)? # fragment
		$=isux", $url, $mtc) ) {
			$user = null;
			$pass = null;
			if( $mtc[2] ) {
				$pos = strpos($mtc[2], ":");
				if( $pos === false )
					$user = substr($mtc[2], 0, -1);
				else {
					$user = substr($mtc[2], 0, $pos);
					$pass = substr($mtc[2], $pos + 1, -1);
				}
			}
			$info = array();
			if( isset($mtc[1]) && $mtc[1] !== "" )
				$info["scheme"] = substr($mtc[1], 0, -1);
			if( isset($mtc[3]) && $mtc[3] !== "" )
				$info["host"] = $mtc[3];
			if( isset($mtc[4]) && $mtc[4] !== "" )
				$info["port"] = (int)$mtc[4];
			if( $user !== null )
				$info["user"] = $user;
			if( $pass !== null )
				$info["pass"] = $pass;
			if( isset($mtc[5]) && $mtc[5] !== "" )
				$info["path"] = $mtc[5];
			if( isset($mtc[6]) && $mtc[6] !== "" )
				$info["query"] = substr($mtc[6], 1);
			if( isset($mtc[7]) && $mtc[7] !== "" )
				$info["fragment"] = substr($mtc[7], 1);
			if( !empty($info) ) {
				if( $component !== -1 ) {
					switch( $component ) {
						case PHP_URL_SCHEME: return isset($info["scheme"]) ? $info["scheme"] : null;
						case PHP_URL_HOST: return isset($info["host"]) ? $info["host"] : null;
						case PHP_URL_PORT: return isset($info["port"]) ? $info["port"] : null;
						case PHP_URL_USER: return isset($info["user"]) ? $info["user"] : null;
						case PHP_URL_PASS: return isset($info["pass"]) ? $info["pass"] : null;
						case PHP_URL_PATH: return isset($info["path"]) ? $info["path"] : null;
						case PHP_URL_QUERY: return isset($info["query"]) ? $info["query"] : null;
						case PHP_URL_FRAGMENT: return isset($info["fragment"]) ? $info["fragment"] : null;
					}
					return null;
				}
				return $info;
			}
		}
		return false;
	}

	public function getPort() {
		return $this->port;
	}
	
	public function getDefaultPort() {
		switch($this->scheme) {
			case "ftp": return 21;
			case "ssh": return 22;
			case "http": return 80;
			case "https": return 443;
		}
		return null;
	}

	public function getQueryString() {
		if( is_string($this->query) )
			return $this->query;
		return http_build_query($this->query);
	}

	public function getAbsoluteUrl() {
		$url = $this->scheme . '://';
		if( $this->user !== null ) {
			$url .= $this->user;
			if( $this->pass !== null )
				$url .= ':' . $this->pass;
			$url .= '@';
		}
		$url .= ($this->host !== null) ? $this->host : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		if( $this->port !== null && $this->port != $this->getDefaultPort() )
			$url .= ':' . $this->port;
		return $url . $this->getRelativeUrl(true);
	}

	public function getRelativeUrl($fullPath = false) {
		$url = (string)($fullPath ? $this->getFullPath() : $this->path);
		if( !empty($this->query) )
			$url .= '?' . $this->getQueryString();
		if( !empty($this->fragment) )
			$url .= '#' . $this->fragment;
		return $url;
	}

	public function getFullPath() {
		return (string)($this->pathIsLocal ? (self::$basePath . $this->path) : $this->path);
	}

	public function __toString() {
		if( isset($_SERVER['HTTP_HOST']) && ($this->host === null || $this->host === $_SERVER['HTTP_HOST']) )
			return $this->getRelativeUrl();
		return $this->getAbsoluteUrl();
	}

	static function getCurrentUrl() {
		if( self::$_currentUrl === null ) {
			if( isset($_SERVER['HTTP_HOST']) ) {
				$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
				if( isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== FALSE )
					$url .= $_SERVER['REQUEST_URI'];
				else {
					$pth = preg_replace('/index\\.php$/i', '', $_SERVER['PHP_SELF']);
					$url = $pth . ((isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) ? ('?' . $_SERVER['QUERY_STRING']) : '');
				}
				self::$_currentUrl = new Url($url);
			}
			else
				self::$_currentUrl = new Url();
		}
		return clone self::$_currentUrl;
	}
}