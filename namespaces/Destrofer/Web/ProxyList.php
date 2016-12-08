<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */

namespace Destrofer\Web;

abstract class ProxyList {
	/**
	 * Returns a list of proxies.
	 *
	 * @param int $limit
	 * @return Proxy[]|null Returns a list of proxies retreived from the provider or NULL in case of an error.
	 */
	public function getProxies($limit = 100) {
		try {
			$id = $this->asyncBeginGetProxies($limit);
			if( !$id )
				return null;
		}
		catch(Exception $ex) {
			return null;
		}
		return $this->asyncEndGetProxies($id);
	}

	/**
	 * Begins the asynchronous download process for retreiving list of proxies.
	 *
	 * @param int $limit
	 * @return false|int Returns ID of the asynchronous download or FALSE in case of an error.
	 * @throws Exception Exception is thrown in case of failed automatic multi-cURL initialization.
	 */
	public abstract function asyncBeginGetProxies($limit = 100);

	/**
	 * Finishes download and returns a list of retreived proxies.
	 * 
	 * Please not that if download is not finished the method will block until it is finished and the data is received.
	 *
	 * @param int $downloadId ID of the download returned by asyncBeginGetProxies().
	 * @return Proxy[]|null Returns a list of proxies retreived from the provider or NULL in case of an error.
	 */
	public abstract function asyncEndGetProxies($downloadId);
	
}