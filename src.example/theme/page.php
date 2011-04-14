<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" href="<?php echo $theme_path; ?>style.css" type="text/css" media="screen" charset="utf-8"/>
	<title><?php echo $title; ?> | Marksite Template</title>
</head>

<body>
	<header>

		<hgroup>
			<h1><a href="<?php echo $home_path; ?>">Marksite Template</a></h2>
			<h2>The website title is changable in src/theme/page.php</h2>
		</hgroup>

		<nav id="mainMenu">
			<ul><?php echo $this->menu(0); ?></ul>
			<?php if ($this->has_menu(1)){ ?>
			<ul class="submenu"><?php echo $this->menu(1); ?></ul>
			<?php } ?>
		</nav>

	</header>




	<article>
		<?php echo $contents; ?>
	</article>


	<aside>
		<?php echo $this->block['sidebar']; ?>
	</aside>



	<footer>
		<p>
			Powered by <a href="http://marksite.m-wei.net">Marksite</a>.
			Syntax powered by <a href="http://daringfireball.net">Markdown</a>.
		</p>
	</footer>
</body>

</html>