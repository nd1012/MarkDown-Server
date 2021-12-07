# PHP MarkDown-Server demonstration

Files in this folder:

- `index.md`: A demonstration MarkDown file that will be parsed to HTML, when called from the MarkDown webroot
- `mdheader.html`: A custom HTML header that will be prepended to each generated HTML (includes some CSS for styling the result)
- `mdfooter.html`: A custom HTML footer that will be appended to each generated HTML (includes [highlight.js](https://github.com/highlightjs/highlights.js) for syntax highlighting fenced code parts)

## Directory index file

If your `.htaccess` is configured properly, you shouldn't need to call the `index.md` directly - it should be enough to call the folder in the browsers address bar, because `index.md` will be the directory index file for your webserver.

## Customizing tips

The `mdheader.html` and `mdfooter.html` will only be used for the output, but they won't be cached. If you want to customize the generated HTML that will be written to the cache, you can modify the `$finalHtml` in the `mdserver.hook.php`, and remove `mdheader.html` and `mdfooter.html`.

## Public Domain license

The CSS stylesheet in `mdheader.html` includes only a very limited layout for the generated HTML. You're free to use it as it comes, or make any modifications as you like (the **HTML files** in this demonstration **are Public Domain licensed** for this purpose).
