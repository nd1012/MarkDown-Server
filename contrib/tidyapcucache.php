<?php

/*
 * Used to delete all APCu cache keys that belong to MarkDown source files that don't exist anymore
 *
 * Place this file in the same folder as the mdserver.php, and delete it after usage.
 * 
 * If you want authentication, please set a password in the APCU_SECRET constant in mdserver.conf.php.
 */

// Load the MarkDown Server configuration
require __DIR__.'/mdserver.conf.php';

// Perform the authentication, if enabled
if(defined('APCU_SECRET')&&APCU_SECRET!='')
	if(!isset($_SERVER['PHP_AUTH_PW'])||$_SERVER['PHP_AUTH_PW']!=APCU_SECRET){
		header('WWW-Authenticate: Basic realm="MarkDown Server APCu authentication"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Not authorized';
		exit;
	}

// Check for APCu functionality
if(!function_exists('apcu_enabled')||!apcu_enabled())
	throw new Exception('APCu is not available or enabled');

// Prepare
$key=__DIR__.'/mdserver.php:';// Key prefix
$len=strlen($key);// Key prefix length

// Delete obsolete cache entries
$total=0;// Total number of entries in the cache
$deleted=0;// Total number of deleted cache entries
foreach(apcu_cache_info()['cache_list'] as $entry){
	if(!isset($entry['info'])||substr($entry['info'],0,$len)!=$key) continue;
	$total++;
	$delete=!USE_APCU;
	if(!$delete){
		// Check if the MarkDown file of the cached entry still exists
		$fn=explode('.',substr($entry['info'],$len));
		array_pop($fn);
		$delete=!file_exists(__DIR__.implode('.',$fn));
	}
	if(!$delete){
		echo nl2br('KEEP '.substr($entry['info'],$len).PHP_EOL);
		continue;
	}
	// Delet the cache entry
	$deleted++;
	echo nl2br('DELETE '.substr($entry['info'],$len).PHP_EOL);
	apcu_delete($entry['info']);
}

// Output a summary
echo nl2br('---Summary--------------------'.PHP_EOL);
echo nl2br('Total number of cached entries: '.$total.PHP_EOL);
echo nl2br('Number of deleted cached entries: '.$deleted.PHP_EOL);
echo 'Number of kept cached entries: '.($total-$deleted);
exit;
