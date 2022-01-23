# Use php-markdown as MarkDown converter

[php-markdown](https://github.com/michelf/php-markdown) may be used as embedded MarkDown converter. These steps are required:

1. Follow the steps from the [php-markdown](https://github.com/michelf/php-markdown) project site to install the library
1. Create a PHP file like the `php-markdown.php` from this folder
1. Modify the `mdserver.conf.php` and set the value of `MD_TO_HTML_CMD` to `@php-markdown.php:convertMarkDown`

That's it!
