<?php
#
# Marksite  -  A text-to-html conversion framework for web builders
#

# include
include_once "config.php";
include_once "markdown.php";

class Marksite_Parser
{
	# menu buffer
	var $menu;

	# array indicating current position
	var $current = array();

	# recursive delete from http://php.net/manual/en/class.recursivedirectoryiterator.php
	function empty_dir($dir) {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $path) {
			if ($path->isDir()) {
				rmdir($path->__toString());
			}
			else
			{
				unlink($path->__toString());
			}
		}
		rmdir($dir);
	}

	function copy_files($src, $dst) {
		$ignored_files = array("php", "markdown", "html");
		$ignored_files_re = implode("|", $ignored_files);

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $path) 
		{
			$src_path = $path->__toString();
			$quoted_src = preg_quote($src, '/'); # escape quote
			$dst_path = preg_replace("/^$quoted_src/",$dst,$src_path);
			if ($path->isDir())
			{
				if( !file_exists($dst_path) )
				{
					mkdir($dst_path);
				}
			}
			# don't copy hidden files and prohibited files
			else if (!preg_match("/(^(\.)|\.($ignored_files_re)\$)/", $src_path))
			{
				copy($src_path, $dst_path);
			}
		}
	}

	function parse()
	{

		if (is_dir(MARKSITE_DST_PATH))
		{
			$this->empty_dir(MARKSITE_DST_PATH);
		}

		if (mkdir(MARKSITE_DST_PATH))
		{
			$this->menu = $this->generate_menu("");
			$this->generate_contents("");
			$this->copy_files(MARKSITE_SRC_PATH, MARKSITE_DST_PATH);
		}
		else
		{
			die("Cannot create new directory: ".MARKSITE_DST_PATH);
		}

	}

	function generate_menu($dir)
	{
		include MARKSITE_SRC_PATH."$dir"."info.php";
		$menu = array();

		foreach ( $contents as $file => $title )
		{
			$src_file = MARKSITE_SRC_PATH.$dir.$file;
			if (is_dir($src_file))
			{
				$menu[$file] = $this->generate_menu($dir.$file."/");
			}
			else if (file_exists("$src_file.markdown") || file_exists("$src_file.php") || file_exists("$src_file.html"))
			{
				$menu[$file] = $title;
			}
		}

		return $menu;
	}

	function has_menu($layer)
	{
		return $layer <= count($this->current);
	}

	function menu($layer)
	{
		$output = "";
		if ($this->has_menu($layer))
		{
			$ancestors = array_slice($this->current, 0, $layer);
			$uri_before = MARKSITE_ABSOLUTE_PATH;

			if($layer > 0)
			{
				$uri_before .= implode("/", $ancestors)."/";
			}

			$target_menu = $this->menu;
			for ($i = 0; $i < $layer; $i++)
			{
				$target_menu = $this->menu[$this->current[$i]];
			}

			foreach ($target_menu as $file => $title)
			{
				if (is_array($title))
				{
					if (is_string($title["index"]))
					{
						$title = $title["index"];
					}
					else
					{
						$title = $file;
					}

					$uri = $uri_before.$file;
				}
				else
				{
					$uri = $uri_before.$file.".html";
				}
					

				if ($layer < count($this->current) && $file == $this->current[$layer])
				{
					if ($layer == count($this->current)-1)
					{
						$output .= "<li class=\"current\"><a href=\"$uri\">$title</a></li>\n";
					}
					else
					{
						$output .= "<li class=\"current-ancestor\"><a href=\"$uri\">$title</a></li>\n";
					}
				}
				else
				{
					$output .= "<li><a href=\"$uri\">$title</a></li>\n";
				}
			}
		}
		return $output;
	}

	function write_themed($dst_file, $title, $contents)
	{
		$theme_path = MARKSITE_ABSOLUTE_PATH.MARKSITE_THEME_PATH;
		if ($page_output = fopen("$dst_file.html", "c"))
		{
			# starting output buffer
			ob_start();
			
			# run theme, generate content
			include MARKSITE_THEME_PATH."page.php";
			
			# get output
			$themed_contents = ob_get_contents();

			# clean buffer
			ob_end_clean();

			# write contents
			fwrite($page_output, $themed_contents);

			fclose($page_output);
		}
		else
		{
			die("Cannot output page to: $dst_file.html");
		}
	}

	function generate_contents($dir)
	{
		# read directory information
		# "Title" => "filename" (without postfix)
		#
		# $contents = Array(
		# 	"index" => "Home",
		# 	"aboutme" => "About Me"
		# );
		#
		include MARKSITE_SRC_PATH.$dir."info.php";
		
		foreach ( $contents as $file => $title )
		{
			$src_file = MARKSITE_SRC_PATH.$dir.$file;
			$dst_file = MARKSITE_DST_PATH.$dir.$file;

			# indicating where i am
			array_push($this->current, $file);

			if (is_dir($src_file))
			{
				if (mkdir($dst_file))
				{
					$this->generate_contents($dir.$file."/");
				}
				else
				{
					die("Cannot create new directory: $dst_file");
				}
			}
			else if (file_exists("$src_file.markdown") && $page = fopen("$src_file.markdown", "r"))
			{
				print("$dir$file  -  $title\n");

				# read file, convert it from Markdown to HTML
				$contents = Markdown(fread($page, filesize("$src_file.markdown")));
				$this->write_themed($dst_file, $title, $contents);
				fclose($page);
			}
			else if (file_exists("$src_file.php"))
			{
				print("(PHP) $dir$file  -  $title\n");

				ob_start();
				include "$src_file.php";
				$contents = ob_get_contents();
				ob_end_clean();
				$this->write_themed($dst_file, $title, $contents);
			}
			else if (file_exists("$src_file.html"))
			{
				print("(HTML) $dir$file  -  $title\n");

				ob_start();
				include "$src_file.html";
				$contents = ob_get_contents();
				ob_end_clean();
				$this->write_themed($dst_file, $title, $contents);
			}
			else
			{
				die("Cannot find file: $src_file.{markdown|php|html}");
			}

			array_pop($this->current);
		}

	}

}

$marksite = new Marksite_Parser;
$marksite->parse();