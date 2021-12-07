# PHP MarkDown Server

[MarkDown](https://www.markdownguide.org/) is used in many places for writing documentations and other stuff and widely supported in many environments. However, creating websites using MarkDown isn't a really new idea (-> GitHub pages), but how could you serve a MarkDown website DIY?

I found some solutions for hosting MarkDown websites, but none of them really satisfied me. Especially when it comes to the performance, because none of them included a cache, so the MarkDown had to be processed for each call. Or they don't allow customization. Or they include their own script language to be mixed within MarkDown, which is just a bit too much... Or they simply didn't support the MarkDown features that I require. Or they're just too complicated to setup.

My approach is to use Apache with `mod_rewrite` as webserver, and simple PHP as gateway to output (cached) HTML. You can use any CLI tool to convert MarkDown to HTML - I tried the `markdown` and `redcarpet` ([https://github.com/vmg/redcarpet](https://github.com/vmg/redcarpet)) commands. These features are included currently:

- Custom header/footer
- Client/Server cache
- PHP hook

## Pre-requirements

1. Apache webserver with `mod_rewrite` enabled and `.htaccess` configured
2. PHP
3. Any CLI MarkDown to HTML converter

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

## Webspace setup

In the MarkDown webroot you'll need to place a `.htaccess` and the `mdserver.php` file. The PHP script will use a writable folder to cache the generated HTML (which may be the MarkDown webroot folder).

The `.htaccess` file contents:

```
DirectoryIndex index.md
RewriteEngine on
RewriteBase /mdwebroot/
RewriteRule \/md(server|header|footer)(\.hook)?\.(html|php)$ - [NC,F]
RewriteCond %{REQUEST_FILENAME} \.md$ [NC]
RewriteCond %{LA-U:REQUEST_FILENAME} -f
RewriteCond %{LA-U:REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /path/to/mdwebroot/mdserver.php/$1/ [NC,L]
```

Please replace `/mdwebroot/` with the MarkDown webroot path of your webspace, and `/path/to/mdwebroot/mdserver.php` with the full local path to the `mdserver.php` file.

In the `mdserver.php` file please edit the configuration first:

- `HTML_CACHE_DIR`: This should point to a writable folder that will be used as cache folder for the generated HTML files (skip a trailing slash)
- `CACHE_ENABLED`: If the cache is enabled in general
- `MKDIR_MODE`: The filesystem access mode for new folders within the cache folder
- `MAX_CACHE_TIME`: Maximum client cache time in seconds (this value will be sent to a client browser)
- `MD_TO_HTML_CMD`: CLI command to use to convert a MarkDown file to HTML (variables `{mdfile}` points to the full local MarkDown file path, `{htmlfile}` to the full local HTML file path, both values are shell argument escaped)

Now you're done already:

```
https://uri.to/mdwebroot/index.md
```

If you've used the demo files, you should be able to see the generated HTML website in your browser, when you point to this URI.

## How it works

The `.htaccess` configuration will make an internal redirect to the `mdserver.php` file, which then outputs the generated HTML. For this, the PHP script will have a look at the cache folder structure, if the HTML file exists. The HTML filename is the MarkDown filename with the `.html` postfix. Every time you update the MarkDown source file, the PHP script will re-create the HTML file to the cache folder structure next time a browser tries to access it. This is how the server-side cache works.

The client-side cache is managed by the browser, the PHP script will only tell the browser some basic information:

1. The time when the HTML file was modified last
2. The time when the cache should expire

Links to other MarkDown files can be written as usual, but they should point to a MarkDown file (not a cached HTML file, even if it's browser accessable).

## Disable caching

### Server-side cache

To disable the server-side cache, simply set the value of the constant `CACHE_ENABLED` to `false`. But will still use the filesystem, if the MarkDown converter wants to write a file.

To avoid using the filesystem here, the MarkDown converter needs to be able to output the HTML to the shell (modify the `MD_TO_HTML_CMD` value). As soon as you disabled the server-side cache and modified the MarkDown converter command to output HTML to the shell, you can set the value of `HTML_CACHE_DIR` to `null`.

### Browser cache

To disable the browser cache support, simply set `MAX_CACHE_TIME` to `0`. This will avoid to interpret the received cache headers or send and cache headers to the client.

## Performance

Of course converting MarkDown to HTML will take some time and use resources. But as soon as the converter is done, the generated HTML will be cached and re-used until you modify the MarkDown source file. All in all this results in a very good performance already.

For an even better performance, the `.htaccess` configuration could try to use the previously cached HTML file and only use the PHP script, if the HTML file doesn't exist. For this the `HTML_CACHE_DIR` should point to the MarkDown webroot. However, this option would skip the whole (server- AND client-side) cache control of the PHP script, and you would have to delete the HTML versions of updated MarkDown source files manually!

## Security

If the called MarkDown file URI can't be resolved to an existing file, or the resolved full file path isn't under the folder that contains the `mdserver.php`, the PHP script will deny processing the request.

The `.htaccess` will block any access to a `\/md(server|header|footer)(\.hook)?\.(html|php)$` file. So `mdserver.php` can't be accessed directly, which is how it should be, because it should only be possible to run the PHP script from an internal relocation trough a `mod_rewrite` rule. Anyway, the `mdserver.php` contains an accessibility check, too.

## Customizing the output HTML

### Adding a header/footer

You can define the HTML to prepend/append to the generated HTML using the files `mdheader.html` and `mdfooter.html`. The header/footer won't be cached, so it may be modified at any time without having to clear the server side cache.

### Using a PHP hook

Before the generated HTML is being written do the cache, a PHP script with the name `mdserver.hook.php` has the chance to modify the generated HTML and write the final HTML to use from and in the variable `$finalHtml`. If the value of `$finalHtml` is `null`, nothing will be sent to the client. If the value is an empty string, only the cache headers will be sent to the client.
