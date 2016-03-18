<?php
/**
 * Copyright 2016 Viacheslav Soroka
 * Licensed under GNU Lesser General Public License v3.
 * See LICENSE in repository root folder.
 * @link https://github.com/destrofer/PhpHelperLibrary
 */
define('PHP_HELPER_LIBRARY_NAMESPACES_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'namespaces' . DIRECTORY_SEPARATOR);

spl_autoload_register(function($cls) {
	if( strpos($cls, '\\') !== false ) {
		$classPath = ltrim($cls, '\\');
		if( DIRECTORY_SEPARATOR != '\\' )
			$classPath = str_replace('\\', DIRECTORY_SEPARATOR, $classPath);
	}
	else
		$classPath = $cls;
	$classPath = PHP_HELPER_LIBRARY_NAMESPACES_PATH . $classPath . '.php';
	if( is_file($classPath) ) {
		require_once $classPath;
		if( is_callable("{$cls}::__static_construct") )
			$cls::__static_construct();
		return true;
	}
	return false;
});