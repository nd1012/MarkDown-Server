<?php

/*
 * Used to delete all APCu cache keys
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
	echo nl2br('DELETE '.substr($entry['info'],$len).PHP_EOL);
	apcu_delete($entry['info']);
}
echo nl2br('---Summary--------------------'.PHP_EOL);
echo 'Total number of deleted cached entries: '.$total;
