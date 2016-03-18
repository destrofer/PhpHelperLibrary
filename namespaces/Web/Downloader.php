<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Web;

use \Exception;
use Web\Proxy;

class Downloader {
	private static $registeredShutdown = false;
	private static $handle = null;
	private static $maxConnections = 10;
	private static $activeDownloads = [];
	private static $activeDownloadsIndex = [];
	private static $active = null;
	private static $lastErrorCode = CURLM_OK;
	private static $nextDownloadId = 1;

	/**
	 * Sets CURLMOPT_MAXCONNECTS multi-cURL option.
	 * @param int $max
	 */
	public static function setMaxConnections($max) {
		self::$maxConnections = $max;
		if( self::$handle )
			curl_multi_setopt(self::$handle, CURLMOPT_MAXCONNECTS, self::$maxConnections);
	}

	/**
	 * Initializes the multi-cURL handle to use for downloads.
	 *
	 * You don't have to call this method as it is called automatically when
	 * needed.
	 *
	 * @throws Exception
	 */
	public static function init() {
		if( self::$handle )
			return;
		self::$handle = curl_multi_init();
		if( !self::$handle )
			throw new Exception("Failed to initialize multi-cURL");
		curl_multi_setopt(self::$handle, CURLMOPT_MAXCONNECTS, self::$maxConnections);
		if( !self::$registeredShutdown )
			register_shutdown_function(get_class() . '::shutdown');
		self::$registeredShutdown = true;
	}

	/**
	 * Cancels all active downloads and deinitializes multi-cURL.
	 *
	 * You don't have to call this method as it is called automatically when
	 * script ends.
	 */
	public static function shutdown() {
		if( !self::$handle )
			return;
		foreach( self::$activeDownloads as &$dl )
			curl_multi_remove_handle(self::$handle, $dl['handle']);
		self::$activeDownloads = [];
		self::$activeDownloadsIndex = [];

		curl_multi_close(self::$handle);
		self::$handle = null;
	}

	/**
	 * Handles multi-cURL activity.
	 */
	private static function doLoop() {
		do {
			self::$lastErrorCode = curl_multi_exec(self::$handle, self::$active);
			if( self::$lastErrorCode == CURLM_OK ) {
				while( $result = curl_multi_info_read(self::$handle) ) {
					$cid = (string)$result['handle'];
					if( !isset(self::$activeDownloadsIndex[$cid]) )
						continue;
					$cid = self::$activeDownloadsIndex[$cid];
					if( !self::$activeDownloads[$cid]['done'] ) {
						self::$activeDownloads[$cid]['done'] = true;
						self::$activeDownloads[$cid]['body'] = curl_multi_getcontent($result['handle']);
						self::$activeDownloads[$cid]['request'] = curl_getinfo($result['handle'], CURLINFO_HEADER_OUT);
						self::$activeDownloads[$cid]['http_code'] = curl_getinfo($result['handle'], CURLINFO_HTTP_CODE);
						self::$activeDownloads[$cid]['last_url'] = curl_getinfo($result['handle'], CURLINFO_EFFECTIVE_URL);

						if( $result['result'] != CURLE_OK ) {
							self::$activeDownloads[$cid]['error_code'] = $result['result'];
							self::$activeDownloads[$cid]['error'] = curl_error($result['handle']);
						}
					}
				}
			}
		} while (self::$lastErrorCode == CURLM_CALL_MULTI_PERFORM);
	}

	/**
	 * Returns status of downloader.
	 * 
	 * WARNING: Downloads that have finished, but are still in the download
	 * queue are still considered active. To pull finished downloads from
	 * the download queue use endDownload() and/or endAnyDownload() methods.
	 * This method will return FALSE only when the download queue is empty.
	 *
	 * @return bool Returns TRUE if there are any downloads in the download queue.
	 */
	public static function isActive() {
		return !empty(self::$activeDownloads);
	}

	/**
	 * @return int Returns last multi-cURL error code. See CURLM_* constants.
	 */
	public static function getLastError() {
		return self::$lastErrorCode;
	}

	/**
	 * Adds a download request to the queue.
	 *
	 * @param array $options An associative array of download options.
	 *
	 *  - `url` string Request URL.
	 *
	 *  - `type` string (optional) GET, POST, or any request method acceptable by HTTP. Defaults to POST if `data` is specified and not NULL. Otherwise defaults to GET.
	 *
	 *  - `data` string|array|null (optional) Data to be posted to the URL. Defaults to NULL.
	 *
	 *  - `outputFile` string|null (optional) Will save the response to the specified file instead of returning it in the download result body. Defaults to NULL.
	 *
	 *  - `verify` bool (optional) TRUE to verify SSL certificates on HTTPS request. Defaults to TRUE.
	 *
	 *  - `maxRedirs` int (optional) Maximum number of redirects to follow. Defaults to 0.
	 *
	 *  - `timeout` float (optional) Number of seconds before download operation is considered timed out and fails.
	 *
	 *  - `proxy` Web\Proxy (optional) Instance of the proxy to use for connection. Defaults to NULL.
	 *
	 *  - `header` array (optional) An associative array of additional HTTP request headers to be sent. Key of array element is the header name. First request line also may be modified if array contains an element with the empty string key.
	 *
	 *  - `curlOptions` array (optional) An associative array to be passed to curl_setopt_array() function before starting the download.
	 *
	 * @return false|int Returns ID of the download in the queue or FALSE in case of an error.
	 * @throws Exception
	 */
	public static function beginDownload($options) {
		$url = $options['url'];
		$type = isset($options['type']) ? $options['type'] : null;
		$postData = isset($options['data']) ? $options['data'] : null;
		$outputFile = isset($options['outputFile']) ? $options['outputFile'] : null;
		$verify = isset($options['verify']) ? $options['verify'] : null;
		$maxRedirs = isset($options['maxRedirs']) ? intval($options['maxRedirs']) : 0;
		$timeout = isset($options['timeout']) ? floatval($options['timeout']) * 1000 : 30000;
		/** @var Proxy $proxy */
		$proxy = isset($options['proxy']) ? $options['proxy'] : null;

		$uinf = parse_url($url);
		$host = $uinf["host"];
		$path = $uinf["path"];
		$path .= (isset($uinf['query']) && $uinf['query']) ? ('?'.$uinf['query']) : '';

		if( $outputFile ) {
			$fl = fopen( $outputFile, "w+" );
			if( !$fl )
				return FALSE;
		}
		else
			$fl = null;

		if( $type === null )
			$type = (($postData !== null) ? "POST" : "GET");

		$headers = [];
		$headers[''] = "$type $path HTTP/1.1";
		$headers['Host'] = $host;
		$headers['User-Agent'] = "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36";

		if( isset($options['headers']) )
			$headers = array_merge($headers, $options['headers']);

		$ch = curl_init();

		$downloadResult = [
			'handle' => $ch,
			'shd' => $headers,
			'request' => '',
			'header' => [],
			'body' => null,
			'http_code' => null,
			'last_url' => $url,
			'done' => false,
		];

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify ? 1 : 0);

		curl_setopt($ch, CURLOPT_URL, $url);
		if( $outputFile )
			curl_setopt($ch, CURLOPT_FILE, $fl);
		else
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$downloadResult) {
			$head = rtrim($header, "\r\n");
			if( $head != '' ) {
				$head = explode(':', $head, 2);
				if( isset($head[1]) )
					$downloadResult['header'][strtoupper($head[0])] = ltrim($head[1]);
				else
					$downloadResult['header'][null] = $head[0];
			}
			return strlen($header);
		});

		$sendHeaders = [];
		foreach( $headers as $k => $v )
			$sendHeaders[] = ($k == '' || is_numeric($k)) ? $v : "$k: $v";

		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, ($maxRedirs > 0) ? 1 : 0);
		curl_setopt($ch, CURLOPT_MAXREDIRS, $maxRedirs);

		if( $postData ) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		}

		if( $proxy ) {
			curl_setopt($ch, CURLOPT_PROXY, $proxy->ip . ':' . $proxy->port);
			curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port);
			curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy->getCurlType());
			if( $proxy->username ) {
				curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->username . ':' . $proxy->password);
			}
		}

		if( isset($options['curlOptions']) )
			curl_setopt_array($ch, $options['curlOptions']);

		if( !self::$handle )
			self::init();

		self::$lastErrorCode = curl_multi_add_handle(self::$handle, $ch);
		if( self::$lastErrorCode == CURLM_OK ) {
			self::doLoop();
			self::$activeDownloads[self::$nextDownloadId] = &$downloadResult;
			self::$activeDownloadsIndex[(string)$ch] = self::$nextDownloadId;
			return self::$nextDownloadId++;
		}
		curl_close($ch);
		return false;
	}

	/**
	 * Waits for all active downloads to finish.
	 *
	 * Be aware, that if operation times out it may return less download IDs than the number of downloads in the queue, or even an empty array.
	 *
	 * @param null|float $timeout (optional) Maximum time in seconds to wait for all downloads to finish. NULL means to wait indefinitely. Defaults to NULL.
	 * @return array|false Returns either an array of download IDs that are finished (independent from separate download results), or FALSE if there was an error with multi-cURL.
	 */
	public static function waitAllDownloads($timeout = null) {
		if( !self::$handle )
			return false;
		if( empty(self::$activeDownloads) )
			return [];
		self::$lastErrorCode = CURLM_OK;
		$time = microtime(true);
		while (self::$active && self::$lastErrorCode == CURLM_OK) {
			$timeLeft = ($timeout === null) ? 1.0 : ($timeout - (microtime(true) - $time));
			if( $timeLeft <= 0 ) {
				$finished = [];
				foreach( self::$activeDownloads as $id => &$dl )
					if( $dl['done'] )
						$finished[] = $id;
				return $finished;
			}
			$select = curl_multi_select(self::$handle, $timeLeft);
		    if ( $select == -1)
				usleep(100);
			self::doLoop();
		}
		return (self::$lastErrorCode == CURLM_OK) ? array_keys(self::$activeDownloads) : false;
	}

	/**
	 * Waits for a specific download operation to finish.
	 *
	 * @param int $id ID of the download given by beginDownload() method.
	 * @param null|float $timeout (optional) Maximum time in seconds to wait for download to finish. NULL means to wait indefinitely. Defaults to NULL.
	 * @return int|false Returns given download ID if it has finished, 0 if operation times out, or FALSE if there was an error with multi-cURL.
	 */
	public static function waitDownload($id, $timeout = null) {
		if( !self::$handle || !isset(self::$activeDownloads[$id]) )
			return false;
		if( self::$activeDownloads[$id]['done'] )
			return $id;
		self::$lastErrorCode = CURLM_OK;
		$time = microtime(true);
		while (self::$active && self::$lastErrorCode == CURLM_OK) {
			$timeLeft = ($timeout === null) ? 1.0 : ($timeout - (microtime(true) - $time));
			if( $timeLeft <= 0 )
				return 0;
			$select = curl_multi_select(self::$handle, $timeLeft);
		    if ( $select == -1)
				usleep(100);

			self::doLoop();
			if( self::$activeDownloads[$id]['done'] )
				return $id;
		}
		return false;
	}

	/**
	 * Waits for any download operation to finish.
	 *
	 * @param null|float $timeout (optional) Maximum time in seconds to wait for any download to finish. NULL means to wait indefinitely. Defaults to NULL.
	 * @return int|false Returns download ID that has finished, 0 if operation times out, -1 if there are no active downloads, or FALSE if there was an error with multi-cURL.
	 */
	public static function waitAnyDownload($timeout = null) {
		if( !self::$handle )
			return false;
		if( empty(self::$activeDownloads) )
			return -1;
		foreach( self::$activeDownloads as $id => &$dl )
			if( $dl['done'] )
				return $id;
		self::$lastErrorCode = CURLM_OK;
		$time = microtime(true);
		while (self::$active && self::$lastErrorCode == CURLM_OK) {
			$timeLeft = ($timeout === null) ? 1.0 : ($timeout - (microtime(true) - $time));
			if( $timeLeft <= 0 )
				return 0;
			$select = curl_multi_select(self::$handle, $timeLeft);
		    if ( $select == -1)
				usleep(10);

			self::doLoop();

			foreach( self::$activeDownloads as $id => &$dl )
				if( $dl['done'] )
					return $id;
		}
		return false;
	}

	/**
	 * Gets the result of queued download and removes the download from the queue.
	 * 
	 * This method will block until download finishes. This method will return
	 * NULL on operation timeout if the timeout parameter is not NULL. If the
	 * operation times out the download is not removed from the queue.
	 *
	 * @param int $id ID of the download returned by beginDownload(), waitAllDownloads(), waitDownload() or waitAnyDownload().
	 * @param null|float $timeout (optional) Maximum time in seconds to wait for the download to finish. NULL means to wait indefinitely. Defaults to NULL.
	 * @return array|false|null Returns an associative array of the download result, NULL if operation times out, or FALSE if there was an error with multi-cURL.
	 */
	public static function endDownload($id, $timeout = null) {
		if( !self::$handle || !isset(self::$activeDownloads[$id]) )
			return false;
		$res = self::waitDownload($id, $timeout);
		if( $res === false )
			return false;
		if( !$res )
			return null;
		$data = &self::$activeDownloads[$id];
		curl_multi_remove_handle(self::$handle, $data['handle']);
		unset(self::$activeDownloads[$id], self::$activeDownloadsIndex[(string)$data['handle']], $data['handle']);
		return $data;
	}

	/**
	 * Gets the result of first queued download that has finished and removes that download from the queue.
	 * 
	 * This method will block until any queued download finishes. This method
	 * will return NULL on operation timeout if the timeout	parameter is not
	 * NULL.
	 *
	 * @param null|float $timeout (optional) Maximum time in seconds to wait for any download to finish. NULL means to wait indefinitely. Defaults to NULL.
	 * @return array|false|null Returns an associative array of the finished download result, NULL if operation times out, or FALSE if there was an error with multi-cURL.
	 */
	public static function endAnyDownload($timeout = null) {
		if( !self::$handle || empty(self::$activeDownloads) )
			return false;
		$id = self::waitAnyDownload($timeout);
		if( $id === false )
			return false;
		if( !$id )
			return null;
		$data = &self::$activeDownloads[$id];
		curl_multi_remove_handle(self::$handle, $data['handle']);
		unset(self::$activeDownloads[$id], self::$activeDownloadsIndex[(string)$data['handle']], $data['handle']);
		return $data;
	}

	/**
	 * Cancels the download and removes it from the download queue.
	 *
	 * @param int $id ID of the download returned by beginDownload(), waitAllDownloads(), waitDownload() or waitAnyDownload().
	 */
	public static function cancelDownload($id) {
		if( !self::$handle || !isset(self::$activeDownloads[$id]) )
			return;
		$data = self::$activeDownloads[$id];
		curl_multi_remove_handle(self::$handle, $data['handle']);
		unset(self::$activeDownloads[$id], self::$activeDownloadsIndex[(string)$data['handle']], $data['handle']);
	}

	/**
	 * Downloads a signle result.
	 *
	 * This method blocks until download finishes. It can be used when there is
	 * no need in multiple simultaneous downloads.
	 *
	 * @param array $options Download options. See beginDownload() method.
	 * @return array|false Returns an array of download result or FALSE in case of unknown error with cURL.
	 * @see beginDownload()
	 */
	public static function download($options) {
		$id = self::beginDownload($options);
		if( !$id )
			return false;
		return self::endDownload($id);
	}
}