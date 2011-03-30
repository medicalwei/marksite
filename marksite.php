<?php
#
# Marksite  -  A text-to-html conversion framework for web builders
#

# include
include_once "config.php";
include_once "markdown.php";

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
		$dst_path = preg_replace("/^$src/",$dst,$src_path);
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

	# TODO: recursively delete MARKSITE_DST_PATH first
	empty_dir(MARKSITE_DST_PATH);

	if (mkdir(MARKSITE_DST_PATH))
	{
		generate_contents("");
	}
	else
	{
		die("Cannot create new directory: ".MARKSITE_DST_PATH);
	}

}

function generate_contents($dir)
{
	# read directory information
	# "Title" => "filename" (without postfix)
	#
	# $contents = Array(
	# 	"Home" => "index"
	# 	"About me" => "aboutme"
	# );
	#
	include MARKSITE_SRC_PATH."$dir/info.php";
	

	foreach ( $contents as $title => $file )
	{
		$src_file = MARKSITE_SRC_PATH."$dir/$file";
		$dst_file = MARKSITE_DST_PATH."$dir/$file";

		if (is_dir($src_file))
		{
			if (mkdir($dst_file))
			{
				generate_content("$dir/$file");
			}
			else
			{
				die("Cannot create new directory: $dst_file");
			}
		}
		else if ($page = fopen("$src_file.markdown", "r"))
		{
			print("$dir/$file  -  $title\n");

			# read file, convert it from Markdown to HTML
			$contents = Markdown(fread($page, filesize("$src_file.markdown")));

			if ($page_output = fopen("$dst_file.html", "c"))
			{
				# starting output buffer
				ob_start();
				
				# run theme, generate content
				include MARKSITE_SRC_PATH."/".MARKSITE_THEME_PATH."/page.php";
				
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

			fclose($page);
		}
		else
		{
			die("Cannot open markdown file: $src_file.markdown");
		}
	}

	# put contents other than php to destination directory
	copy_files(MARKSITE_SRC_PATH, MARKSITE_DST_PATH);

}

parse();