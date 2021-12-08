<?php

// Help for the MarkDown command can be found here: https://manpages.debian.org/bullseye/discount/markdown.1.en.html
// Help for the redcarpet command can be found here: https://github.com/vmg/redcarpet

define('HTML_CACHE_DIR','/path/to/mdwebroot');// Path to the HTML cache folder (without trailing slash, set to NULL, if cache is disabled and the HTML generator command won't write a file)
define('CACHE_ENABLED',true);// If the server cache is enabled
define('MKDIR_MODE',0777);// Access mode for created folders in the HTML cache folder
define('MAX_CACHE_TIME',60*60*24*30);// Maximum cache time in seconds (zero to disable client cache support)
//define('MD_TO_HTML_CMD','/usr/bin/markdown -f +fencedcode,-style {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)
define('MD_TO_HTML_CMD','/usr/bin/redcarpet --parse-fenced-code-blocks {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)
