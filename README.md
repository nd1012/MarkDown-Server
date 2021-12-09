# PHP MarkDown Server

## Table of contents

- [What is...](#what-is)
- [Pre-requirements](#pre-requirements)
	- [MarkDown converter Debian Linux setup](#markdown-converter-debian-linux-setup)
		- [Using `markdown` as converter](#using-markdown-as-converter)
		- [Using `redcarpet` as converter](#using-redcarpet-as-converter)
	- [Using a PHP script as converter](#using-a-php-script-as-converter)
- [Webspace setup](#webspace-setup)
- [How it works](#how-it-works)
- [Caching](#caching)
	- [APCu in-memory-caching](#apcu-in-memory-caching)
		- [Clear the APCu cache](#clear-the-apcu-cache)
	- [Disable caching](#disable-caching)
		- [Server-side cache](#server-side-cache)
		- [Browser cache](#browser-cache)
- [Performance](#performance)
- [Security](#security)
- [Customizing the output HTML](#customizing-the-output-html)
	- [Adding a header/footer](#adding-a-header-footer)
	- [Using a PHP hook](#using-a-php-hook)
	- [Handle the HTML output](#handle-the-html-output)
- [Known issues/limitations](#known-issues-limitations)

## What is...

[MarkDown](https://www.markdownguide.org/) is used in many places for writing documentations and other stuff and widely supported in many environments. However, creating websites using MarkDown isn't a really new idea (-> GitHub pages), but how could you serve a MarkDown website DIY?

I found some solutions for hosting MarkDown websites, but none of them really satisfied me. Especially when it comes to the performance, because none of them included a cache, so the MarkDown had to be processed for each call. Or they don't allow customization. Or they include their own script language to be mixed within MarkDown, which is just a bit too much... Or they simply didn't support the MarkDown features that I require. Or they're just too complicated to setup.

My approach is to use Apache with `mod_rewrite` as webserver, and simple PHP as gateway to output (cached) HTML. You can use any CLI tool to convert MarkDown to HTML - I tried the `markdown` and `redcarpet` ([https://github.com/vmg/redcarpet](https://github.com/vmg/redcarpet)) commands. These features are included currently:

- Custom header/footer
- Client/Server cache
- Support for CLI or PHP MarkDown converter
- PHP hook

## Pre-requirements

1. Apache webserver with `mod_rewrite` enabled and `.htaccess` configured
2. PHP
3. Any CLI or PHP MarkDown to HTML converter

### MarkDown converter Debian Linux setup

#### Using `markdown` as converter

```
apt install markdown
```

(Example command line is in `mdserver.php`)

#### Using `redcarpet` as converter

```
apt install ruby-redcarpet
```

(Example command line is in `mdserver.php`)

### Using a PHP script as converter

In the `mdserver.conf.php` configuration file the constant `MD_TO_HTML_CMD` actually defines the shell command to be executed for converting MarkDown to HTML. If you want to call a static PHP method or a global PHP function instead, you can use this special syntax for the constant value:

```
@/path/to/include.php:\Your\NameSpace\ClassName::MethodName
```

This would call the static method `MethodName` of the class `\Your\NameSpace\ClassName` that will be defined by loading the PHP include `/path/to/include.php`, giving the full source MarkDown and temporary HTML file paths as parameters.

```
@/path/to/include.php:\Your\NameSpace\FunctionName
```

This would call the global function `\Your\NameSpace\FunctionName` that will be defined by loading the PHP include `/path/to/include.php`, giving the full source MarkDown and temporary HTML file paths as parameters.

The temporary HTML file path (second parameter) may be `NULL`. If so, the method/function is required to return the generated HTML. Otherwise the generated HTML has to be written to the temporary HTML file.

Use this way, if you want to use a pure PHP MarkDown converter, for example.

## Webspace setup

In the MarkDown webroot you'll need to place a `.htaccess` and the `mdserver.php` file along with the `mdserver.conf.php` file. The PHP script will use a writable folder to cache the generated HTML (which may be the MarkDown webroot folder).

The `.htaccess` file contents:

```
# Use index.md as directory index
DirectoryIndex index.md

# Prepare rewrite
RewriteEngine on
RewriteBase /mdwebroot/

# Deny direct access to internal files that shouldn't be called from a browser
RewriteRule \/md(server|header|footer)(\..+)?\.(html|php)$ - [NC,F]

# Process MarkDown files using the mdserver.php
RewriteCond %{REQUEST_FILENAME} \.md$ [NC]
RewriteCond %{LA-U:REQUEST_FILENAME} -f
RewriteCond %{LA-U:REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /path/to/mdwebroot/mdserver.php/$1/ [NC,L]
```

Please replace `/mdwebroot/` with the MarkDown webroot path of your webspace, and `/path/to/mdwebroot/mdserver.php` with the full local path to the `mdserver.php` file.

In the `mdserver.conf.php` file please edit the configuration first:

- `HTML_CACHE_DIR`: This should point to a writable folder that will be used as cache folder for the generated HTML files (skip a trailing slash)
- `CACHE_ENABLED`: If the cache is enabled in general
- `USE_APCU`: Use APCu (in-memory-cache) for caching generated HTML?
- `APCU_TTL`: APCu TTL in seconds (should be greater than zero - however, zero would disable TTL)
- `MKDIR_MODE`: The filesystem access mode for new folders within the cache folder
- `MAX_CACHE_TIME`: Maximum client cache time in seconds (this value will be sent to a client browser)
- `MD_TO_HTML_CMD`: CLI command to use to convert a MarkDown file to HTML (variables `{mdfile}` points to the full local MarkDown file path, `{htmlfile}` to the full local HTML file path, both values are shell argument escaped)
- `DISABLE_HEADER_FOOTER`: Disable header/footer customization?
- `DISABLE_HOOK`: Disable the PHP hook?

Now you're done already:

```
https://uri.to/mdwebroot/index.md
```

If you've used the demo files, you should be able to see the generated HTML website in your browser, when you point to this URI (of course replace the dummys with your configured webspace URI).

To see how the served `index.md` from the `demo` folder should look: [https://nd1012.github.io/MarkDown-Server/demo.html](https://nd1012.github.io/MarkDown-Server/demo.html).

## How it works

The `.htaccess` configuration will make an internal redirect to the `mdserver.php` file, which then outputs the generated HTML. For this, the PHP script will have a look at the cache folder structure, if the HTML file exists. The HTML filename is the MarkDown filename with the `.html` postfix. Every time you update the MarkDown source file, the PHP script will re-create the HTML file to the cache folder structure next time a browser tries to access it. This is how the server-side cache works, when not using APCu.
If APCu is available and enabled, nothing will be cached in the HTML cache folder, but instead an in-memory-cache entry will be managed.

The client-side cache is managed by the browser, the PHP script will only tell the browser some basic information:

1. The time when the HTML file was modified last
2. The time when the cache should expire

Links to other MarkDown files can be written as usual, but they should point to a MarkDown file (not a cached HTML file, even if it's browser accessable).

## Caching

### APCu in-memory-caching

If APCu is available and enabled (`USE_APCU` is `true`), the MarkDown server would cache generated HTML in memory, instead of writing to the HTML cache folder. The advantage is, that in-memory-cache access is much faster than reading/writing from/to a filesystem. The disadvantage is that the cache will be lost, if the caching engine or the server was restarted.

The cache key is a combination of the full path to the `mdserver.php`, combined with a `:`, and the relative HTML cache filename - for example:

- Full path is `/path/to/mdwebroot/mdserver.php`
- URI was `https://uri.to/mdwebroot/folder/file.md`

The cache key would be:

```
/path/to/mdwebroot/mdserver.php:/folder/file.md
```

To display all cached values, you can use the PHP script `apcucache.php` in the `contrib` folder. Place this PHP script in the same folder as the `mdserver.php` file and call it from your browser.

**NOTE**: Be sure to delete the file from your webspace after usage!

#### Clear the APCu cache

When you want to clear the APCu cache, you have two options, which are available from PHP scripts in the `contrib` folder:

1. Delete all cache keys that belong to MarkDown source files that don't exist anymore (use `tidyapcucache.php`)
2. Delete all cache keys (use `clearapcucache.php`)

Place the desired PHP script in the same folder as the `mdserver.php` file and call it from your browser.

**NOTE**: Be sure to delete the file from your webspace after usage!

Clearing the APCu cache from the CLI is a bit difficult, because the APCu cache used in Apache  is different from the one that a CLI PHP uses. If you're looking for a CLI solution for clearing the APCu cache, maybe the [CacheTool](https://github.com/gordalina/cachetool) could help.

### Disable caching

#### Server-side cache

To disable the server-side cache, simply set the value of the constant `CACHE_ENABLED` to `false`. But will still use the filesystem, if the MarkDown converter wants to write a file.

To avoid using the filesystem here, the MarkDown converter needs to be able to output the HTML to the shell (modify the `MD_TO_HTML_CMD` value). As soon as you disabled the server-side cache and modified the MarkDown converter command to output HTML to the shell, you can set the value of `HTML_CACHE_DIR` to `null`.

#### Browser cache

To disable the browser cache support, simply set `MAX_CACHE_TIME` to `0`. This will avoid to interpret the received cache headers or send and cache headers to the client.

## Performance

Of course converting MarkDown to HTML will take some time and use resources. But as soon as the converter is done, the generated HTML will be cached and re-used unless you modify the MarkDown source file. All in all this results in a very good performance already.

For an even better performance, the `.htaccess` configuration could try to use the previously cached HTML file and only use the PHP script, if the HTML file doesn't exist. For this the `HTML_CACHE_DIR` should point to the MarkDown webroot. However, this option would skip the whole (server- AND client-side) cache control of the PHP script, and you would have to delete the HTML versions of updated MarkDown source files manually!

The absolutely best performance will (of course) be reached using the APCu in-memory-cache. Use these tips for the best performance:

- Ensure APCu is useable and set the `APCU_TTL` value to zero (this won't expire cached entries unless their source MarkDown file was changed)
- Don't use a cache folder (set `HTML_CACHE_DIR` to `NULL`)
- Use a PHP MarkDown converter that returns the generated HTML, or use a CLI converter that writes the generated HTML to STDOUT
- Prefer a fast PHP MarkDown converter, if possible
- Optional disable the PHP hook
- Optional disable header/footer (if you don't want to use the `mdheader.php` to define a `markdown_server_output` function)
- Optional produce (GZip) compressed HTML and use a `markdown_server_output` function (see [Handle the HTML output](#handle-the-html-output)) that sends the correct http headers (this would save memory and CPU resources) and defines the value `TRUE` for the constant `DISABLE_FOOTER` to avoid looking for non-existing footer files

Instead of using the PHP hook or a header/footer, you could change the `MD_TO_HTML_CMD` constant value to point to a PHP method/function that calls the MarkDown converter and does all modifications (adding header/footer f.e.) in one place. The resulting HTML would then be cached including header/footer, what will save time, but - of course - uses more cache memory.

I've tested these tips with an optimized demo environment using a small 4 US$ VServer with only 1 CPU and 2 GB RAM. The median ping response of the server is 208 ms (slow, yes, because I ping from Asia to Europe), and the browser network monitor shows a median time of 214 ms for loading the http response from that server - which means the https (v1.1) overhead including server processing time was only about 6 ms with that setup:

`DISABLE_HOOK` was set to `TRUE`, `HTML_CACHE_DIR` was set to `NULL`, `MD_TO_HTML_CMD` contains `@/path/to/mdwebroot/mdserver.parser.php:parse_md`.

`mdserver.parser.php` contents:

```php
<?php

function parse_md($md,$html){
	$cmd='/usr/bin/redcarpet --parse-fenced-code-blocks --render-with-toc-data '.escapeshellarg($md);
	return gzencode(file_get_contents(__DIR__.'/mdheader.html').`$cmd`.file_get_contents(__DIR__.'/mdfooter.html'),9);
}
```

`mdheader.php` contents:

```php
<?php

function markdown_server_output($finalHtml){
	define('DISABLE_FOOTER',true);
	ini_set('zlib.output_compression','Off');
	header('Content-Encoding: gzip',true);
	header('Content-Length: '.strlen($finalHtml));
	echo $finalHtml;
}
```

## Security

If the called MarkDown file URI can't be resolved to an existing file, or the resolved full file path isn't under the folder that contains the `mdserver.php`, the PHP script will deny processing the request. The maximum URI path length is limited to 4095 characters.

The `.htaccess` will block any access to a `\/md(server|header|footer)(\..+)?\.(html|php)$` file. So `mdserver.php` can't be accessed directly, which is how it should be, because it should only be possible to run the PHP script from an internal relocation trough a `mod_rewrite` rule. Anyway, the `mdserver.php` contains an accessibility check, too.

## Customizing the output HTML

### Adding a header/footer

You can define the HTML to prepend/append to the generated HTML using the files `mdheader.html` (and/or `mdheader.php`) and `mdfooter.html` (and/or `mdfooter.php`). The header/footer won't be cached, so it may be modified at any time without having to clear the server side cache. The loading order is:

- `mdheader.php`
- `mdheader.html`
- `mdfooter.html`
- `mdfooter.php`

### Using a PHP hook

Before the generated HTML is being written do the cache, a PHP script with the name `mdserver.hook.php` has the chance to modify the generated HTML and write the final HTML to use from and in the variable `$finalHtml`. If the value of `$finalHtml` is `null`, nothing will be sent to the client. If the value is an empty string, only the cache headers will be sent to the client.

### Handle the HTML output

To handle the HTML output by yourself, you can write the global function `markdown_server_output`, that will be called to output the generated HTML - for example:

```php
markdown_server_output($finalHtml){
	// You may modify the sent headers or $finalHtml here
	echo $finalHtml;
}
```

You could place this function in the `mdheader.php` file that will be included. If you didn't use a HTML header, and the `mdheader.php` didn't output anything, you could modify the sent http headers within the `markdown_server_output` function, before you send the HTML to the browser.

**TIP**: If you use the `mdheader.php` to define this function, and you want to avoid looking for non-existing footer files, define the value `TRUE` for the constant `DISABLE_FOOTER`.

## Known issues/limitations

- Maximum request URI path length (relative to the MarkDown webroot (the folder that includes `mdserver.php`)) is 4095 characters
- Symbolic links that point outside of the MarkDown webroot (the folder that includes `mdserver.php`) can't be served
- MarkDown source files must use the `.md` extension (case is ignored)
- Deleted MarkDown source files won't be deleted from the HTML/APCu cache
- For a better string encoding compatibility, the PHP extension `mbstring` should to be installed and activated
- Generated and cached HTML will be stored in memory for processing, what may be a problem when working with huge files
