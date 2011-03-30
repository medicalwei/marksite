Marksite — A text-to-html conversion framework for web builders 
===============================================================

This is a simple, stupid, hackable website converter using simply php
and Markdown.

Steps to generate contents
--------------------------
1. Copy `config.example.php` and `src.example` to `config.php` and `src` (this is for simplify git update)
2. Open config.php, change the absolute path.
3. Put your content into `src/[pagename].markdown` using markdown syntax
4. Write `"[pagename]" => "Page title",` into `src/page.php`
5. run `marksite.php`
6. You've done!
