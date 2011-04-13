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
		$ignored_files = array("php", "markdown", "html", "md");
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
			$this->menu = $this->prepare_menu("");
			$this->generate_contents("");
			$this->copy_files(MARKSITE_SRC_PATH, MARKSITE_DST_PATH);
		}
		else
		{
			die("Cannot create new directory: ".MARKSITE_DST_PATH);
		}

	}

	function prepare_menu($dir)
	{
		include MARKSITE_SRC_PATH."$dir"."info.php";
		$menu = array();
		$uri_before = MARKSITE_ABSOLUTE_PATH.$dir;

		# prevent menu_hidden not set
		if ( !isset($menu_hidden) )
		{
			$menu_hidden = array();
		}

		foreach ( $contents as $file => $title )
		{
			# skip hidden one
			if ( in_array( $file, $menu_hidden ) )
			{
				continue;
			}

			$src_file = MARKSITE_SRC_PATH.$dir.$file;
			if (is_dir($src_file))
			{
				$item = array(
						'uri' => $uri_before.$file."/",
						'file' => $file,
						'title' => $title,
						'menu' => $this->prepare_menu($dir.$file."/")
						);
			}
			else if (file_exists("$src_file.markdown") || file_exists("$src_file.md") || file_exists("$src_file.php") || file_exists("$src_file.html"))
			{
				$item = array(
						'uri' => $uri_before.$file.".html",
						'file' => $file,
						'title' => $title,
						);
			}

			array_push($menu, $item);
		}

		return $menu;
	}

	function has_menu($level)
	{
		return $level < count($this->current);
	}

	function menu($level, $depth=1)
	{
		# prevent error
		if (!$this->has_menu($level))
		{
			return "";
		}

		$ancestors = array_slice($this->current, 0, $level);

		if($level > 0)
		{
			$uri_before .= implode("/", $ancestors)."/";
		}

		$target_menu = $this->menu;
		for ($i = 0; $i < $level; $i++)
		{
			$target_menu = $this->menu[$this->current[$i]]['menu'];
		}

		return $this->menu_recursion($target_menu, $level, $depth);
	}


	function menu_recursion($target_menu, $level, $depth)
	{
		$output = "";
		foreach ($target_menu as $menuitem)
		{
			$uri = $menuitem['uri'];
			$file = $menuitem['file'];
			$title = $menuitem['title'];

			if ($file == $this->current[$level])
			{
				#ancestor: current level, and not index
				if ($level < count($this->current)-1 && $this->current[$level+1] != "index")
				{
					$output .= "<li class=\"current-ancestor\">";
				}
				else
				{
					$output .= "<li class=\"current\">";
				}
			}
			else
			{
				$output .= "<li>";
			}

			$output .= "<a href=\"$uri\">$title</a>";

			if ( $depth>1 && isset($menuitem['menu']) )
			{
				$output .= "\n<ul>\n";
				$output .= $this->menu_recursion($menuitem['menu'], $level+1, $depth-1);
				$output .= "</ul>\n";
			}

			$output .= "</li>\n";
		}
		return $output;
	}

	function write_themed($dst_file, $title, $contents)
	{
		/* some usable variable for theme */
		$theme_path = MARKSITE_ABSOLUTE_PATH.MARKSITE_THEME_PATH;
		$home_path = MARKSITE_ABSOLUTE_PATH;

		if ($page_output = fopen("$dst_file.html", "c"))
		{
			# starting output buffer
			ob_start();
			
			# run theme, generate content
			include MARKSITE_SRC_PATH.MARKSITE_THEME_PATH."page.php";
			
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
			else if (file_exists("$src_file.md") && $page = fopen("$src_file.md", "r"))
			{
				print("$dir$file  -  $title\n");

				# read file, convert it from Markdown to HTML
				$contents = Markdown(fread($page, filesize("$src_file.md")));
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
			else if (file_exists("$src_file.html") && $page = fopen("$src_file.html", "r"))
			{
				print("(HTML) $dir$file  -  $title\n");

				# read file
				$contents = fread($page, filesize("$src_file.html"));
				$this->write_themed($dst_file, $title, $contents);
			}
			else
			{
				die("Cannot find file: $src_file.{markdown|md|php|html}");
			}

			array_pop($this->current);
		}

	}

}

$marksite = new Marksite_Parser;
$marksite->parse();