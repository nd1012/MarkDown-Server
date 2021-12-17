<?php

/*
 * Used to delete all APCu cache keys
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

// Delete all cache entries
$total=0;// Total number of deleted cache entries
foreach(apcu_cache_info()['cache_list'] as $entry){
	if(!isset($entry['info'])||substr($entry['info'],0,$len)!=$key) continue;
	$total++;
	echo nl2br('DELETE '.substr($entry['info'],$len).PHP_EOL);
	apcu_delete($entry['info']);
}

// Output a summary
echo nl2br('---Summary--------------------'.PHP_EOL);
echo 'Total number of deleted cached entries: '.$total;
exit;
