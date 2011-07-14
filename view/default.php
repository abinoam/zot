<!DOCTYPE html >
<html>
<head>
  <title><?php if(x($page,'title')) echo $page['title'] ?></title>
  <script>var baseurl="<?php echo z_path() ?>";</script>
  <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
</head>
<body>
	<header><?php if(x($page,'header')) echo $page['header']; ?></header>
	<nav><div id="top-margin"></div><?php if(x($page,'nav')) echo $page['nav']; ?></nav>
	<aside><?php if(x($page,'aside')) echo $page['aside']; ?></aside>
	<section><?php if(x($page,'content')) echo $page['content']; ?>
		<div id="page-footer"></div>
	</section>
	<footer><?php if(x($page,'footer')) echo $page['footer']; ?></footer>
</body>
</html>

