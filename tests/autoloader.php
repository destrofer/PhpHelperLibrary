<?php

spl_autoload_register(function ($cls) {
	if( strpos($cls, '\\') !== false ) {
		$classFile = ltrim($cls, '\\');
		if( DIRECTORY_SEPARATOR != '\\' )
			$classFile = str_replace('\\', DIRECTORY_SEPARATOR, $classFile);
		$classPath = $classFile . '.php';
		$path = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 'namespaces' . DIRECTORY_SEPARATOR . $classPath;
		if( is_file($path) ) {
			require_once $path;
			return true;
		}
	}
	return false;
});
