Marksite — A text-to-html website compiler for web builders 
===========================================================

This is a simple, stupid, hackable website *compiler* using simply PHP and Markdown.

Steps to generate contents
--------------------------
1. Copy `config.example.php` and `src.example` to `config.php` and `src`, for simplifying git update.
2. Open config.php, change the absolute path.
3. Put your content into `src/[pagename].markdown` using markdown syntax.
4. Write `"[pagename]" => "Page title",` into `src/info.php`.
5. run `marksite.php`.
6. All the magic things happens at `html` directory. Hooray!
