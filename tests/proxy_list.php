<?php
// Test the downloader and proxy list

use Web\Url;
use Web\ProxyLists\GimmeProxyProxyList;

require_once "../autoload.php";

$proxyList = new GimmeProxyProxyList();
print_r($proxyList->getProxies());
