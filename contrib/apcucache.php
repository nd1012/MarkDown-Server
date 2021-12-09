<?php

/*
 * Used to list all APCu cache keys including their time and size
 * 
 * Place this file in the same folder as the mdserver.php, and delete it after usage.
 */

if(!function_exists('apcu_enabled')||!apcu_enabled())
	throw new Exception('APCu is not available or enabled');

$key=__DIR__.'/mdserver.php:';
$len=strlen($key);

$total=0;
foreach(apcu_cache_info()['cache_list'] as $entry){
	if(!isset($entry['info'])||substr($entry['info'],0,$len)!=$key) continue;
	$total++;
	echo nl2br(substr($entry['info'],$len).' ('.date('Y-m-d H:i.s',$entry['mtime']).', '.$entry['mem_size'].' bytes)'.PHP_EOL);
}
echo nl2br('---Summary--------------------'.PHP_EOL);
echo 'Total number of cached entries: '.$total;
