<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8"/>

	<title><?php echo $title; ?></title>
</head>
<body>
	<nav>
		<ul><?php echo $this->menu(0); ?></ul>
	</nav>
	<article>
		<?php echo $contents; ?>
	</article>
</body>
</html>