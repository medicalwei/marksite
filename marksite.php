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

	# blocks
	var $blocks = array();

	# recursive delete from http://php.net/manual/en/class.recursivedirectoryiterator.php
	function empty_dir($dir)
	{
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $path)
		{
			if ($path->isDir())
			{
				rmdir($path->__toString());
			}
			else
			{
				unlink($path->__toString());
			}
		}
		rmdir($dir);
	}

	function copy_files($src, $dst)
	{
		$ignored_files = array("php", "markdown", "html", "md");
		$ignored_files_re = implode("|", $ignored_files);

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $path) 
		{
			$src_path = $path->__toString();
			$quoted_src = preg_quote($src, '/'); # escape quote
			$dst_path = preg_replace("/^$quoted_src/",$dst,$src_path);

			# don't copy hidden files and prohibited files
			if (!preg_match("/(\/\.[^\/\.]|\.($ignored_files_re)\$)/", $src_path))
			{
				if ($path->isDir())
				{
					if( !file_exists($dst_path) )
					{
						mkdir($dst_path);
					}
				}
				else
				{
					copy($src_path, $dst_path);
				}
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
			print("== Generating Menu ==\n");
			$this->menu = $this->prepare_menu("");
			print("\n");

			if (is_dir(MARKSITE_BLOCKS_PATH))
			{
				print("== Generating Blocks ==\n");
				$this->prepare_blocks(MARKSITE_BLOCKS_PATH);
				print("\n");
			}

			print("== Generating Contents ==\n");
			$this->generate_contents("");
			print("\n");

			print("== Copying Files ==\n");
			$this->copy_files(MARKSITE_SRC_PATH, MARKSITE_DST_PATH);
			print("\n");

			print("== All Done! ==\n");
			print("\n");
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

			if (preg_match("/^http:\/\//", $file))
			{
				$menu[] = array(
						'uri' => $file,
						'title' => $title
						);
			}
			else if (is_dir($src_file))
			{
				$menu[] = array(
						'uri' => $uri_before.$file."/",
						'title' => $title,
						'menu' => $this->prepare_menu($dir.$file."/"),
						'file' => $file
						);
			}
			else if (file_exists("$src_file.markdown") || file_exists("$src_file.md") || file_exists("$src_file.php") || file_exists("$src_file.html"))
			{
				$menu[] = array(
						'uri' => $uri_before.$file.".html",
						'title' => $title,
						'file' => $file
						);
			}
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

		$target_menu = $this->menu;
		for ($i = 0; $i < $level; $i++)
		{
			$target_file = $this->current[$i];
			foreach ($target_menu as $menuitem)
			{ 
				if ($menuitem['file'] == $target_file)
				{
					$target_menu = $menuitem['menu'];
					break;
				}
			}
		}

		return $this->menu_recursion($target_menu, $level, $depth, true);
	}


	function menu_recursion($target_menu, $level, $depth, $is_current = false)
	{
		$output = "";
		foreach ($target_menu as $menuitem)
		{
			$uri = $menuitem['uri'];
			$file = $menuitem['file'];
			$title = $menuitem['title'];

			$is_current_next = false;

			if ($is_current && $file == $this->current[$level])
			{
				$is_current_next = true;

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
				$output .= $this->menu_recursion($menuitem['menu'], $level+1, $depth-1, $is_current_next);
				$output .= "</ul>\n";
			}

			$output .= "</li>\n";
		}
		return $output;
	}

	function write_themed($dst_file, $title, $contents)
	{
		# some usable variable for theme
		$home_path = MARKSITE_ABSOLUTE_PATH;

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
			if (preg_match("/^http:\/\//", $file))
			{
				continue;
			}

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
			else if (file_exists($src_file))
			{
				print("$src_file (copy)\n");
				copy($src_file, $dst_file);
			}
			else
			{
				$contents = $this->generate_context($src_file);
				$this->write_themed($dst_file, $title, $contents);
			}

			array_pop($this->current);
		}

	}

	function prepare_blocks($dir)
	{
		$dir_handler = opendir($dir);
		while (($filename = readdir($dir_handler)) !== false)
		{
			if (preg_match("/^\./", $filename))
			{
				continue;
			}
			$block_name = preg_replace("/\.([^\.]*)$/","",$filename);
			$src_file = $dir.$block_name;
			$this->block[$block_name] = $this->generate_context($src_file);
		}
	}
	
	function generate_context($src_file)
	{
		$contents = "";

		if (file_exists("$src_file.md") && $page = fopen("$src_file.md", "r"))
		{
			print("$src_file.md\n");

			# read file, convert it from Markdown to HTML
			$size = filesize("$src_file.md");
			if ($size > 0)
			{
				$contents = Markdown(fread($page, $size));
			}
			fclose($page);
		}
		else if (file_exists("$src_file.markdown") && $page = fopen("$src_file.markdown", "r"))
		{
			print("$src_file.markdown\n");

			# read file, convert it from Markdown to HTML
			$size = filesize("$src_file.markdown");
			if ($size > 0)
			{
				$contents = Markdown(fread($page, $size));
			}
			fclose($page);
		}
		else if (file_exists("$src_file.php"))
		{
			print("$src_file.php\n");

			ob_start();
			include "$src_file.php";
			$contents = ob_get_contents();
			ob_end_clean();
		}
		else if (file_exists("$src_file.html") && $page = fopen("$src_file.html", "r"))
		{
			print("$src_file.html\n");

			# read file
			$size = filesize("$src_file.html");
			if ($size > 0)
			{
				$contents = fread($page, $size);
			}
		}
		else
		{
			print("Warning: $src_file.{markdown|md|php|html} does not exist.\n");
		}

		return $contents;
	}

}

$marksite = new Marksite_Parser;
$marksite->parse();
