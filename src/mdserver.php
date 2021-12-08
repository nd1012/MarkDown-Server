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

// Deny direct access, exit with a forbidden 403 http status
if(!isset($_SERVER['REDIRECT_URL'])||getlowerstr(strend($_SERVER['REDIRECT_URL'],3))!='.md'){
	trigger_error('Direct script access from a browser is denied',E_USER_WARNING);
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
if(!defined('MKDIR_MODE')||!is_int(MKDIR_MODE)||MKDIR_MODE<0||MKDIR_MODE>7777)
	trigger_error('Missing or invalid MKDIR_MODE confiruration constant',E_USER_ERROR);
if(!defined('MAX_CACHE_TIME')||!is_int(MAX_CACHE_TIME))
	trigger_error('Missing or invalid MAX_CACHE_TIME configuration constant',E_USER_ERROR);
if(!defined('MD_TO_HTML_CMD')||!is_string(MD_TO_HTML_CMD)||getstrpos(MD_TO_HTML_CMD,'{mdfile}')<0)
	trigger_error('Missing or invalid MD_TO_HTML_CMD configuration constant',E_USER_ERROR);

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
define('TARGET_FILE',is_null(HTML_CACHE_DIR)?null:HTML_CACHE_DIR.CALLED_PATH.'.html');// Target HTML cache file full path

// Get the output HTML
if(!CACHE_ENABLED||is_null(TARGET_FILE)||!file_exists(TARGET_FILE)||filemtime(SOURCE_FILE)>filemtime(TARGET_FILE)){
	// (Re)Create the HTML
	http_response_code(200);
	// Ensure the HTML cache target directory exists
	if(!is_null(TARGET_FILE)){
		define('TARGET_DIR',dirname(TARGET_FILE));
		if(!is_dir(TARGET_DIR)&&!mkdir(TARGET_DIR,MKDIR_MODE,true)){
			// The target folder couldn't be created, exit with an internal error 500 http status
			trigger_error('Failed to create target HTML cache folder "'.TARGET_DIR.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
	}
	// Find a temporary filename
	define('TEMP_FILE',is_null(TARGET_FILE)?null:TARGET_FILE.uniqid().'.temp');
	// Parse the converter command
	$cmd=MD_TO_HTML_CMD;
	foreach(Array(
		'{mdfile}'			=>	SOURCE_FILE,
		'{htmlfile}'		=>	is_null(TEMP_FILE)?'':TEMP_FILE
		) as $var=>$val)
		if(getstrpos($cmd,$var)>-1)
			$cmd=str_replace($var,escapeshellarg($val),$cmd);
	// Generate the HTML
	if(!is_null(TEMP_FILE)){
		// The command will write the generated HTML to a temporary file
		`$cmd`;
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
		// The command will output the generated HTML to STDOUT
		$temp=trim(`$cmd`);
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
	$file=__DIR__.'/mdserver.hook.php';
	if(file_exists($file)) require $file;
	// Write the HTML cache file
	if(CACHE_ENABLED){
		if($finalHtml!=''&&!is_null(TARGET_FILE)&&file_put_contents(TARGET_FILE,$finalHtml)===false){
			// Writing the generated and encoded HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to write generated and encoded HTML to HTML cache file "'.TARGET_FILE.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		if(MAX_CACHE_TIME) define('CACHE_TIME',time());
	}
}else{
	// Handle caching and determine if the client cache is up to date
	http_response_code(200);
	if(MAX_CACHE_TIME){
		define('CACHE_TIME',filemtime(TARGET_FILE));
		if(CACHE_TIME===false){
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
		define('CACHED_HTML',file_get_contents(TARGET_FILE));
		if(CACHED_HTML===false){
			// Reading HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to read previously generated HTML from HTML cache file "'.TARGET_FILE.'"',E_USER_WARNING);
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
		$file=__DIR__.'/mdheader.html';
		if(file_exists($file)) echo file_get_contents($file);
		echo $finalHtml;
		$file=__DIR__.'/mdfooter.html';
		if(file_exists($file)) echo file_get_contents($file);
	}
}
exit;
