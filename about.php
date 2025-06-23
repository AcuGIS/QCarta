<?php
session_start();
require('admin/incl/const.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
<link href="admin/dist/css/side_menu.css" rel="stylesheet">

<style>
:root {
	--primary-color: #0d6efd;
	--secondary-color: #6c757d;
	--background-color: #f8f9fa;
	--border-color: #e9ecef;
	--text-color: #2c3e50;
}

html, body {
	height: 100%;
	margin: 0;
	padding: 0;
	overflow-x: hidden;
}

body {
	background-color: var(--background-color);
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

#container {
	position: relative;
	min-height: 100vh;
	overflow-x: hidden;
}

#content {
	position: relative;
	padding: 0.5rem 1rem 1rem 1rem;
	margin-left: 210px;
	width: calc(100% - 210px);
	height: auto;
	overflow: visible;
}

.page-title, h1 {
	color: var(--text-color);
	font-size: 1.75rem;
	font-weight: 600;
	margin: 0 0 1.5rem 0;
}

.card {
	border-radius: 12px;
	box-shadow: 0 4px 6px rgba(0,0,0,0.05);
	border: 1px solid var(--border-color);
	margin-bottom: 1.5rem;
	background: #fff;
}

.card-title {
	font-size: 1.2rem;
	font-weight: 600;
	color: var(--primary-color);
}

.card-link {
	color: var(--primary-color);
	font-weight: 500;
	margin-right: 1rem;
	transition: color 0.2s;
}

.card-link:hover {
	color: #0b5ed7;
	text-decoration: underline;
}

.card-text {
	color: var(--secondary-color);
}
</style>

</head>
<body>

		<div id="container">
		
			<?php const NAV_SEL = 'About'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>
				
				<div id="content">
						
						

						<h1>About</h1>						
						<p>QCarta is designed to be simple, secure, and easy to make your own</p>
							
						<ul>				
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Simplicity:  Publish your QGIS Project directly</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Security:  Group Level permissions for both Stores and Layers</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Customization:  QCarta is easy to customize</li>
	
						</ul>

						<h1>Open Source</h1>						
						<p>QCarta is Open Source software built using Open Source components.</p>

<ul>				
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">QGIS Server</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">PostgreSQL</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">PostGIS</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">MapProxy</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">WFS Extension</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">SimpleBrowser</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);"><a href="https://QCarta.docs.acugis.com/en/latest/index.html" target="_blank">Full List</a></li>	
	
						</ul>


												
						<h1>Documentation</h1>								
						<p><a href="https://QCarta.docs.acugis.com/en/latest/index.html" target="_blank">QCarta Documenation</a></p>
				</div>
		</div>
		
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
