<?php

/**
 * @author Andreas Zimmermann, wan24.de
 * @version 3
 * @license MIT
 * @github https://github.com/nd1012/MarkDown-Server
 */

/**
 * Get a substring
 * 
 * @param string $str String
 * @param int $start Start
 * @param int $len Length
 * @return string Substring
 */
function getsubstr($str,$start,$len){
	$res=function_exists('mb_substr')?mb_substr($str,$start,$len):substr($str,$start,$len);
	return $res===false?'':$res;
}

/**
 * Get the start of a string
 * 
 * @param string $str String
 * @param int $len Length
 * @return string Start
 */
function strstart($str,$len){
	return getsubstr($str,0,$len);
}

/**
 * Get the end of a string
 * 
 * @param string $str String
 * @param int $len Length
 * @return string End
 */
function strend($str,$len){
	return getsubstr($str,getstrlen($str)-$len,$len);
}

/**
 * Get the length of a string
 * 
 * @param string $str String
 * @return int Length
 */
function getstrlen($str){
	$res=function_exists('mb_strlen')?mb_strlen($str):strlen($str);
	return $res===false?strlen($str):$res;
}

/**
 * Find the position of a string within a string
 * 
 * @param string $str String
 * @param string $find String to find
 * @return int Position or -1, if not found
 */
function getstrpos($str,$find){
	$res=function_exists('mb_strpos')?mb_strpos($str,$find):strpos($str,$find);
	return $res===false?-1:$res;
}

/**
 * Get a string with all characters converted to lower letters
 * 
 * @param string $str String
 * @return string String
 */
function getlowerstr($str){
	return function_exists('mb_strtolower')?mb_strtolower($str):strtolower($str);
}

/**
 * Ensure having an UTF-8 encoded string
 * 
 * __NOTE__: This works on already UTF-8 encoded or ISO-8859-1 encoded strings only!
 * 
 * @param string $str String
 * @return string UTF-8 encoded string
 */
function utf8encoded($str){
	return !function_exists('mb_detect_encoding')||mb_detect_encoding($str,'UTF-8',true)!==false
		?$str
		:utf8_encode($str);
}

/**
 * Determine if to use the APCu cache
 * 
 * @return boolean Use APCu?
 */
function useapcu(){
	return CACHE_ENABLED&&USE_APCU&&function_exists('apcu_enabled')&&apcu_enabled();
}

/**
 * Write to the cache
 * 
 * @param string $key Key
 * @param string $content Content
 */
function tocache($key,$content){
	if(defined('TARGET_DIR')) throw new Exception('Invalid call');
	if(!CACHE_ENABLED) return;
	if(useapcu())
		if(apcu_store(__FILE__.':'.$key,$content,APCU_TTL)){
			// Stored in APCu cache
			if(MAX_CACHE_TIME) define('CACHE_TIME',time());
			define('TARGET_DIR',null);
			return;
		}else{
			trigger_error('Failed to store "'.__FILE__.':'.$key.'" ('.getstrlen($content).' bytes) in APCu cache',E_USER_WARNING);
		}
	if(is_null(HTML_CACHE_DIR)){
		define('TARGET_DIR',null);
		return;
	}
	// Store in HTML cache folder
	define('TARGET_DIR',dirname(TARGET_FILE));
	if(!is_dir(TARGET_DIR)&&!mkdir(TARGET_DIR,MKDIR_MODE,true)){
		// The target folder couldn't be created, exit with an internal error 500 http status
		trigger_error('Failed to create target HTML cache folder "'.TARGET_DIR.'"',E_USER_WARNING);
		http_response_code(500);
		exit;
	}
	$fn=HTML_CACHE_DIR.$key;
	if(file_put_contents($fn,$content)!==false){
		if(MAX_CACHE_TIME) define('CACHE_TIME',time());
		return;
	}
	// Failed to write to the cache, exit with an internal server error 500 http status
	trigger_error('Failed to write to the HTML cache "'.$fn.'"',E_USER_WARNING);
	http_response_code(500);
	exit;
}

/**
 * Get content from the cache
 * 
 * @param string $key Key
 * @return string Content or NULL, if not cached
 */
function fromcache($key){
	if(!CACHE_ENABLED) return null;
	if(useapcu()&&apcu_exists(__FILE__.':'.$key)){
		// Found APCu cache key
		$succeed=null;
		$res=apcu_fetch(__FILE__.':'.$key,$succeed);
		if($succeed){
			// Restored from APCu cache
			if(MAX_CACHE_TIME&&!defined('CACHE_TIME')) define('CACHE_TIME',cachetime($key));
			return $res;
		}else{
			trigger_error('Failed to fetch "'.__FILE__.':'.$key.'" from APCu cache',E_USER_WARNING);
		}
	}
	if(is_null(HTML_CACHE_DIR)) return null;
	// Restore from the HTML cache folder
	$fn=HTML_CACHE_DIR.$key;
	if(!file_exists($fn)) return null;
	$res=file_get_contents($fn);
	if($res!==false){
		if(MAX_CACHE_TIME&&!defined('CACHE_TIME')) define('CACHE_TIME',cachetime($key));
		return $res;
	}
	// Failed to read the cache, exit with an internal server error 500 http status
	trigger_error('Failed to read from the HTML cache "'.$fn.'"',E_USER_WARNING);
	http_response_code(500);
	exit;
}

/**
 * Get the time of a cache key
 * 
 * @param string $key Key
 * @return int Timestamp or zero, if not cached
 */
function cachetime($key){
	if(!CACHE_ENABLED) return 0;
	if(useapcu()){
		$info=apcu_key_info(__FILE__.':'.$key);
		if(is_array($info)&&isset($info['mtime']))
			// Use the APCu cache time
			return intval($info['mtime']);
	}
	if(is_null(HTML_CACHE_DIR)) return 0;
	// Use the HTML cache file time, if exists
	$fn=HTML_CACHE_DIR.$key;
	$res=file_exists($fn)?filemtime($fn):0;
	return $res===false?0:$res;
}

/**
 * Determine if a cache entry exists
 * 
 * @param string $key Key
 * @return boolean Is in the cache?
 */
function incache($key){
	return CACHE_ENABLED&&
		(
			(
				useapcu()&&
				apcu_exists(__FILE__.':'.$key)
				)||
			(
				!is_null(HTML_CACHE_DIR)&&
				file_exists(HTML_CACHE_DIR.$key)
				)
			);
}

/**
 * Parse MarkDown
 * 
 * The call instructions may look like this:
 * 
 * 	@/path/to/include.php:\Your\NameSpace\ClassName::MethodName
 * 
 * This instruction would include the PHP file `/path/to/include.php` and execute the __static__ method `MethodName` of the class 
 * `\Your\NameSpace\ClassName`, the source MarkDown and temporary HTML full file paths are provided as parameters. The return value needs to be 
 * the generated HTML.
 * 
 * Another example for a simple global function:
 * 
 * 	@/path/to/include.php:\Your\NameSpace\FunctionName
 * 
 * This instruction would include the PHP file `/path/to/include.php` and execute the global function `\Your\NameSpace\FunctionName`, the source 
 * MarkDown and temporary HTML full file paths are provided as parameters. The return value needs to be the generated HTML.
 * 
 * @param string $cmd Shell command or PHP call instructions
 * @return mixed Command output or callback return value
 */
function parsemd($cmd){
	if(strstart($cmd,1)!='@') return `$cmd`;
	list($inc,$callback)=explode(':',strend($cmd,getstrlen($cmd)-1),2);
	require $inc;
	return call_user_func_array(getstrpos($callback,'::')>-1?explode('::',$callback):$callback,Array(SOURCE_FILE,TEMP_FILE));
}

// Deny direct access, exit with a forbidden 403 http status
if(!isset($_SERVER['REDIRECT_URL'])||getlowerstr(strend($_SERVER['REDIRECT_URL'],3))!='.md'){
	trigger_error('Direct script access is denied',E_USER_WARNING);
	http_response_code(403);
	exit;
}

// Load and validate the configuration
require __DIR__.'/mdserver.conf.php';
if(
	!defined('HTML_CACHE_DIR')||
	(
		!is_null(HTML_CACHE_DIR)&&
		(
			!is_string(HTML_CACHE_DIR)||
			strend(HTML_CACHE_DIR,1)=='/'||
			realpath(HTML_CACHE_DIR)===false // Because realpath uses a cache
			)
		)
	)
	trigger_error('Missing or invalid HTML_CACHE_DIR configuration constant',E_USER_ERROR);
if(!defined('CACHE_ENABLED')||!is_bool(CACHE_ENABLED))
	trigger_error('Missing or invalid CACHE_ENABLED configuration constant',E_USER_ERROR);
if(!defined('USE_APCU')||!is_bool(USE_APCU))
	trigger_error('Missing or invalid USE_APCU configuration constant',E_USER_ERROR);
if(!defined('APCU_TTL')||!is_int(APCU_TTL)||APCU_TTL<0)
	trigger_error('Missing or invalid APCU_TTL configuration constant',E_USER_ERROR);
if(!defined('MKDIR_MODE')||!is_int(MKDIR_MODE)||MKDIR_MODE<0||MKDIR_MODE>7777)
	trigger_error('Missing or invalid MKDIR_MODE configuration constant',E_USER_ERROR);
if(!defined('MAX_CACHE_TIME')||!is_int(MAX_CACHE_TIME))
	trigger_error('Missing or invalid MAX_CACHE_TIME configuration constant',E_USER_ERROR);
if(!defined('MD_TO_HTML_CMD')||!is_string(MD_TO_HTML_CMD)||getstrpos(MD_TO_HTML_CMD,'{mdfile}')<0)
	trigger_error('Missing or invalid MD_TO_HTML_CMD configuration constant',E_USER_ERROR);
if(!defined('DISABLE_HOOK')||!is_bool(DISABLE_HOOK))
	trigger_error('Missing or invalid DISABLE_HOOK configuration constant',E_USER_ERROR);
if(!defined('DISABLE_HEADER_FOOTER')||!is_bool(DISABLE_HEADER_FOOTER))
	trigger_error('Missing or invalid DISABLE_HEADER_FOOTER configuration constant',E_USER_ERROR);
			
// Internal constants/variables
define('PATH_LEN',isset($_SERVER['PATH_INFO'])?getstrlen($_SERVER['PATH_INFO']):null);
if(
	is_null(PATH_LEN)||
	PATH_LEN<4||// Supports at last "/.md"
	PATH_LEN>4096||
	strstart($_SERVER['PATH_INFO'],1)!='/'||
	getlowerstr(strend($_SERVER['PATH_INFO'],4))!='.md/'
	){
	// If the called path is suspect, exit with a bad request 400 http status
	trigger_error('Missing or malformed path info in webserver environment',E_USER_WARNING);
	http_response_code(400);
	exit;
}
define('CALLED_PATH',strstart($_SERVER['PATH_INFO'],getstrlen($_SERVER['PATH_INFO'])-1));// Called path without the trailing slash
define('SOURCE_FILE',realpath(__DIR__.CALLED_PATH));// Existing source MarkDown file full path
if(!is_string(SOURCE_FILE)||!file_exists(SOURCE_FILE)){
	// If the source MarkDown files full path can't be resolved (or the file doesn't exist), exit with a not found 404 http status
	http_response_code(404);
	exit;
}
define('TARGET_FILENAME',CALLED_PATH.'.html');// Target HTML cache filename relative to the cache folder
define('TARGET_FILE',is_null(HTML_CACHE_DIR)?null:HTML_CACHE_DIR.TARGET_FILENAME);// Target HTML cache file full path
define('IN_CACHE',incache(TARGET_FILENAME));// Is the target in the cache?

// Get the output HTML
if(!CACHE_ENABLED||(is_null(TARGET_FILE)&&!IN_CACHE)||filemtime(SOURCE_FILE)>cachetime(TARGET_FILENAME)){
	// (Re)Create the HTML
	http_response_code(200);
	// Find a temporary filename
	define('TEMP_FILE',is_null(TARGET_FILE)?null:TARGET_FILE.uniqid().'.temp');
	// Parse the converter command
	$cmd=MD_TO_HTML_CMD;
	if(strstart($cmd,1)!='@')
		foreach(Array(
			'{mdfile}'			=>	SOURCE_FILE,
			'{htmlfile}'		=>	is_null(TEMP_FILE)?'':TEMP_FILE
			) as $var=>$val)
			if(getstrpos($cmd,$var)>-1)
				$cmd=str_replace($var,escapeshellarg($val),$cmd);
	// Generate the HTML
	if(!is_null(TEMP_FILE)){
		// The command will write the generated HTML to a temporary file
		parsemd($cmd);
		if(!file_exists(TEMP_FILE)){
			// Generating HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to generate HTML from MarkDown "'.SOURCE_FILE.'" to temporary file "'.TEMP_FILE.'" using the command "'.$cmd.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		define('GENERATED_HTML',file_get_contents(TEMP_FILE));
		if(!unlink(TEMP_FILE)) trigger_error('Failed to delete temporary HTML cache file "'.TEMP_FILE.'"',E_USER_WARNING);
		if(GENERATED_HTML===false){
			// Reading the generated HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to read generated HTML from temporary file "'.TEMP_FILE.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
	}else{
		// The command will output the generated HTML to STDOUT (or the PHP method will return it)
		$temp=trim((string)parsemd($cmd));
		if($temp==''){
			// Generating HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to generate HTML from MarkDown "'.SOURCE_FILE.'" using command "'.$cmd.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		define('GENERATED_HTML',$temp);
	}
	// UTF-8 encode the generated HTML
	define('CACHED_HTML',utf8encoded(GENERATED_HTML));
	$finalHtml=CACHED_HTML;
	// Execute the PHP hook
	if(!DISABLE_HOOK){
		$file=__DIR__.'/mdserver.hook.php';
		if(file_exists($file)) require $file;
	}
	// Write the HTML cache file
	if($finalHtml!='') tocache(TARGET_FILENAME,$finalHtml);
}else{
	// Handle caching and determine if the client cache is up to date
	http_response_code(200);
	if(MAX_CACHE_TIME){
		define('CACHE_TIME',cachetime(TARGET_FILENAME));
		if(!CACHE_TIME){
			// Failed to get the HTML cache file time, exit with an internal error 500 http status
			trigger_error('Failed to get HTML cache file time from "'.TARGET_FILE.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])&&strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=CACHE_TIME){
			// Client cache is up to date, send a not modified 304 http status only
			define('CACHED_HTML','');
			http_response_code(304);
		}
	}
	// Use the previously generated HTML, if not using the client cache
	if(!defined('CACHED_HTML')){
		define('CACHED_HTML',fromcache(TARGET_FILENAME));
		if(is_null(CACHED_HTML)){
			// Reading HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to read previously generated HTML from HTML cache "'.TARGET_FILE.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
	}
	$finalHtml=CACHED_HTML;
}

// Send the response and quit
if(is_string($finalHtml)){
	if(CACHE_ENABLED&&MAX_CACHE_TIME){
		// Send client cache headers
		header('Pragma: public',true);
		header('Last-Modified: '.gmdate('D, d M Y H:i:s T',CACHE_TIME),true);
		header('Expires: '.gmdate('D, d M Y H:i:s T',CACHE_TIME+MAX_CACHE_TIME),true);
		header('Cache-Control: max-age='.(time()-CACHE_TIME+MAX_CACHE_TIME).',private',true);
	}
	if($finalHtml!=''){
		// Send the HTML
		header('Content-Type: text/html;charset=utf8',true);
		if(!DISABLE_HEADER_FOOTER){
			$file=__DIR__.'/mdheader.php';
			if(file_exists($file)) require $file;
			$file=__DIR__.'/mdheader.html';
			if(file_exists($file)) include $file;
		}
		echo $finalHtml;
		if(!DISABLE_HEADER_FOOTER){
			$file=__DIR__.'/mdfooter.html';
			if(file_exists($file)) include $file;
			$file=__DIR__.'/mdfooter.php';
			if(file_exists($file)) require $file;
		}
	}
}
exit;
