<?php

/*
 * Used to delete all APCu cache keys that belong to MarkDown source files that don't exist anymore
 *
 * Place this file in the same folder as the mdserver.php, and delete it after usage.
 */

if(!function_exists('apcu_enabled')||!apcu_enabled())
	throw new Exception('APCu is not available or enabled');
	
$key=__DIR__.'/mdserver.php:';
$len=strlen($key);

$total=0;
$deleted=0;
foreach(apcu_cache_info()['cache_list'] as $entry){
	if(!isset($entry['info'])||substr($entry['info'],0,$len)!=$key) continue;
	$total++;
	$delete=!USE_APCU;
	if(!$delete){
		$fn=explode('.',substr($entry['info'],$len));
		array_pop($fn);
		$delete=!file_exists(__DIR__.implode('.',$fn));
	}
	if(!$delete){
		echo nl2br('KEEP '.substr($entry['info'],$len).PHP_EOL);
		continue;
	}
	$deleted++;
	echo nl2br('DELETE '.substr($entry['info'],$len).PHP_EOL);
	apcu_delete($entry['info']);
}
echo nl2br('---Summary--------------------'.PHP_EOL);
echo nl2br('Total number of cached entries: '.$total.PHP_EOL);
echo nl2br('Number of deleted cached entries: '.$deleted.PHP_EOL);
echo 'Number of kept cached entries: '.($total-$deleted);
