<?php

/**
 * @author Andreas Zimmermann, wan24.de
 * @version 1
 * @license MIT
 * @github https://github.com/nd1012/MarkDown-Server
 */

// Help for the MarkDown command can be found here: https://manpages.debian.org/bullseye/discount/markdown.1.en.html
// Help for the redcarpet command can be found here: https://github.com/vmg/redcarpet

// Constants to configure
define('HTML_CACHE_DIR','/path/to/mdwebroot');// Path to the HTML cache folder (without trailing slash, set to NULL, if cache is disabled and the HTML generator command won't write a file)
define('CACHE_ENABLED',true);// If the server cache is enabled
define('MKDIR_MODE',0777);// Access mode for created folders in the HTML cache folder
define('MAX_CACHE_TIME',60*60*24*30);// Maximum cache time in seconds (zero to disable client cache support)
//define('MD_TO_HTML_CMD','/usr/bin/markdown -f +fencedcode,-style {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)
define('MD_TO_HTML_CMD','/usr/bin/redcarpet --parse-fenced-code-blocks {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)

// Deny direct access, exit with a forbidden 403 http status
if(preg_match('/^\/.+\/'.preg_quote(basename(__FILE__),'/').'$/',$_SERVER['SCRIPT_NAME'])){
	trigger_error('Direct access denied',E_USER_WARNING);
	http_response_code(403);
	exit;
}

// Internal constants/variables
define('CALLED_PATH',substr($_SERVER['PATH_INFO'],0,strlen($_SERVER['PATH_INFO'])-1));// Called path
define('SOURCE_FILE',realpath(__DIR__.CALLED_PATH));// Source MarkDown file full path
define('TARGET_FILE',is_null(HTML_CACHE_DIR)?null:HTML_CACHE_DIR.CALLED_PATH.'.html');// Target HTML file full path

// Get the output HTML
$finalHtml=null;
if(SOURCE_FILE===false){
	// If the source file doesn't exist, exit with a not found 404 http status
	http_response_code(404);
}else if(!preg_match('/^'.preg_quote(__DIR__,'/').'\/.*\.md$/i',SOURCE_FILE)){
	// If the source file path looks strange, exit with a forbidden 403 http status
	trigger_error('Source file path looks strange, will not process',E_USER_WARNING);
	http_response_code(403);
}else if(!CACHE_ENABLED||!file_exists(TARGET_FILE)||filemtime(SOURCE_FILE)>filemtime(TARGET_FILE)){
	// (Re)Create the HTML file
	http_response_code(200);
	if(!is_null(TARGET_FILE)){
		define('TARGET_DIR',dirname(TARGET_FILE));
		if(!is_dir(TARGET_DIR)&&!mkdir(TARGET_DIR,MKDIR_MODE,true)){
			// The target folder couldn't be created, exit with an internal error 500 http status
			trigger_error('Failed to create target folder "'.TARGET_DIR.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		define('TEMP_FILE',TARGET_FILE.'.'.uniqid().'.temp');
	}
	$cmd=MD_TO_HTML_CMD;
	foreach(Array(
		'mdfile'		=>	SOURCE_FILE,
		'htmlfile'		=>	TEMP_FILE
		) as $var=>$val)
		$cmd=str_replace('{'.$var.'}',escapeshellarg($val),$cmd);
	if(!is_null(TARGET_FILE)){
		`$cmd`;
		if(!file_exists(TEMP_FILE)){
			// Generating HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to generate HTML from MarkDown "'.SOURCE_FILE.'" to "'.TEMP_FILE.'" using the command "'.$cmd.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		define('GENERATED_HTML',file_get_contents(TEMP_FILE));
		if(GENERATED_HTML===false){
			// Reading the generated HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to read generated HTML from "'.TEMP_FILE.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
	}else{
		$temp=trim(`$cmd`);
		if($temp==''){
			// Generating HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to generate HTML from MarkDown "'.SOURCE_FILE.'" using command "'.$cmd.'"',E_USER_WARNING);
			http_response_code(500);
			exit;
		}
		define('GENERATED_HTML',$temp);
	}
	define('CACHED_HTML',utf8_encode(GENERATED_HTML));
	$finalHtml=CACHED_HTML;
	if(defined('TEMP_FILE')&&!unlink(TEMP_FILE)) trigger_error('Failed to delete temporary file "'.TEMP_FILE.'"',E_USER_WARNING);
	if(file_exists(__DIR__.'/mdserver.hook.php')) require __DIR__.'/mdserver.hook.php';
	if(CACHE_ENABLED&&$finalHtml!=''&&file_put_contents(TARGET_FILE,$finalHtml)===false){
		// Writing the generated and encoded HTML failed, exit with an internal server error 500 http status
		trigger_error('Failed to write generated and encoded HTML to "'.TARGET_FILE.'"',E_USER_WARNING);
		http_response_code(500);
		exit;
	}
	if(CACHE_ENABLED&&MAX_CACHE_TIME) define('CACHE_TIME',time());
}else{
	// Handle caching and determine if the client cache is up to date
	http_response_code(200);
	if(MAX_CACHE_TIME){
		define('CACHE_TIME',filemtime(TARGET_FILE));
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])&&strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=CACHE_TIME){
			// Client cache is up to date, send a not modified 304 http status
			define('CACHED_HTML','');
			http_response_code(304);
		}
	}
	// Use the previously generated HTML, if not using the client cache
	if(!defined('CACHED_HTML')){
		define('CACHED_HTML',file_get_contents(TARGET_FILE));
		if(CACHED_HTML===false){
			// Reading HTML failed, exit with an internal server error 500 http status
			trigger_error('Failed to read previously generated HTML from "'.TARGET_FILE.'"',E_USER_WARNING);
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
		header('Cache-Control: max-age='.(time()-CACHE_TIME+MAX_CACHE_TIME).', private',true);
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
