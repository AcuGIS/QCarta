<?php
  session_start();
	require('admin/incl/const.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>Quail Layer Server</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
<link href="admin/dist/css/side_menu.css" rel="stylesheet">

</head>
<body>

		<div id="container">
		
			<?php const NAV_SEL = 'About'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>
				
				<div id="content">
						
						

						<h1>About</h1>						
						<p>Quail is designed to be simple, secure, and easy to make your own</p>
							
						<ul>				
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Simplicity:  Publish your QGIS Project directly</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Security:  Group Level permissions for both Stores and Layers</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Customization:  Quail is easy to customize</li>
	
						</ul>

						<h1>Open Source</h1>						
						<p>Quail is Open Source software built using Open Source components.</p>

<ul>				
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">QGIS Server</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">PostgreSQL</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">PostGIS</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">MapProxy</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">WFS Extension</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">SimpleBrowser</li>
							<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);"><a href="https://quail.docs.acugis.com/en/latest/index.html" target="_blank">Full List</a></li>	
	
						</ul>


												
						<h1>Documentation</h1>								
						<p><a href="https://quail.docs.acugis.com/en/latest/index.html" target="_blank">Quail Documenation</a></p>
				</div>
		</div>
		
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
