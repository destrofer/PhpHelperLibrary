<?php

use Destrofer\Parallel\Coroutine;
use Destrofer\Parallel\CoroutinePool;
use Destrofer\Platform\Promise;
use Destrofer\Web\Downloader;

include __DIR__ . "/../vendor/autoload.php";

$toDownload = [
	"https://microsoft.com/" => __DIR__ . "/test-data/microsoft.com.html",
	"https://apple.com/" => __DIR__ . "/test-data/apply.com.html",
	"https://google.com/" => __DIR__ . "/test-data/google.com.html",
	"https://lipsum.com/" => __DIR__ . "/test-data/lipsum.com.html",
	"https://example.com/" => __DIR__ . "/test-data/example.com.html",
];

foreach ($toDownload as $url => $file) {
	Coroutine::start(function($url, $file) {
		echo "Downloading $url\n";
		$result = Downloader::download([
			"url" => $url,
			"outputFile" => $file,
		]);
		if ($result) {
			echo "Downloaded $url: ";
			var_dump($result);
		}
		else {
			
		}
	}, $url, $file);
}

CoroutinePool::runGlobalPool();

echo "Done\n";