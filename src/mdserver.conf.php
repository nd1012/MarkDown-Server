<?php

// Server-side cache
define('CACHE_ENABLED',true);// If the server cache is enabled
define('HTML_CACHE_DIR','/path/to/mdwebroot');// Path to the HTML cache folder (without trailing slash, set to NULL, if cache is disabled and the HTML generator command won't write a file)
define('MKDIR_MODE',0777);// Access mode for created folders in the HTML cache folder
define('DISABLE_HOOK',false);// Disable PHP hook?

// APCu for the server-side cache
define('USE_APCU',true);// Use APCu for in-memory-caching, if available (won't write cached HTML into the HTML cache folder then)
define('APCU_TTL',60*60*24*30);// APCu cache TTL in seconds (should be greater than zero - however, zero would disable TTL)

// Client-side cache
define('MAX_CACHE_TIME',60*60*24*30);// Maximum cache time in seconds (zero to disable client cache support)

// MarkDown rendering
//define('MD_TO_HTML_CMD','/usr/bin/markdown -f +fencedcode,-style {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)
define('MD_TO_HTML_CMD','/usr/bin/redcarpet --parse-fenced-code-blocks --render-with-toc-data {mdfile} > {htmlfile}');// Command to transform MarkDown to HTML ("{mdfile}" is the MarkDown file path, "{htmlfile}" the HTML file path, paths will be shell argument escaped)
define('DISABLE_HEADER_FOOTER',false);// Disable custom header/footer?

// Help for the MarkDown command can be found here: https://manpages.debian.org/bullseye/discount/markdown.1.en.html
// Help for the redcarpet command can be found here: https://github.com/vmg/redcarpet
