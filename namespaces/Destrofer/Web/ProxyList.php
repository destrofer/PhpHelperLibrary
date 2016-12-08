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
	 * @param int $limit
	 * @return Proxy[]|null
	 */
	public abstract function getProxies($limit = 100);
}