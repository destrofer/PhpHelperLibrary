<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Web\ProxyLists;

use Destrofer\Web\Downloader;
use Destrofer\Web\Proxy;
use Destrofer\Web\ProxyList;

/**
 * @link http://gimmeproxy.com/#how API documentation
 */
class GimmeProxyProxyList extends ProxyList {
	const API_URL = 'http://gimmeproxy.com/api/getProxy';
	
	/**
	 * Begins the asynchronous download process for retreiving list of proxies.
	 *
	 * Please note that according to gimmeproxy.com documentation the response
	 * may return only one proxy so the $limit parameter is ignored.
	 *
	 * @param int $limit
	 * @return false|int Returns ID of the asynchronous download or FALSE in case of an error.
	 * @throws Exception Exception is thrown in case of failed automatic multi-cURL initialization.
	 */
	public function asyncBeginGetProxies($limit = 100) {
		return Downloader::beginDownload([
			'url' => self::API_URL,
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function asyncEndGetProxies($downloadId) {
		if( !$downloadId )
			return null;
		$data = Downloader::endDownload($downloadId);

		if( !$data || $data['http_code'] != 200 )
			return null;

		$result = json_decode($data['body'], true);
		$proxy = new Proxy();

		$ip = is_array($result['ip']) ? reset($result['ip']) : $result['ip'];
		$ip = explode(':', $ip);
		$proxy->ip = $ip[0];
		if( count($ip) > 2 ) // IPv6 is not supported
			return null;
		$proxy->port = (count($ip) == 2) ? $ip[1] : 0;

		if( isset($result['country']) ) {
			$country = is_array($result['country']) ? reset($result['country']) : $result['country'];
			$proxy->country = htmlspecialchars($country);
		}

		switch( $result['type'] ) {
			case "http":
				$proxy->type = "http";
				if( !$proxy->port )
					$proxy->port = 8080;
				break;
			case "socks4":
				$proxy->type = "socks4";
				if( !$proxy->port )
					$proxy->port = 1080;
				break;
			case "socks5":
				$proxy->type = "socks5";
				if( !$proxy->port )
					$proxy->port = 1080;
				break;
			case "socks4/5":
				$proxy->type = "socks5";
				if( !$proxy->port )
					$proxy->port = 1080;
				break;
			default:
				$proxy->type = $result['type'];
				if( !$proxy->port )
					$proxy->port = 8080;
		}

		return [$proxy];
	}
}