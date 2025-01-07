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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<style>

</style>

</head>
 
<body>

		<div id="container">
			<?php const NAV_SEL = 'Home'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>
				
				<div id="content">
						<h1 style="font-size:22px" background-color: ##6BA81E!important>Introduction</h1>
						<p>Quail (QGIS Administration and Layers) is a layer server and administration tool for QGIS Server.  </p>
						<p>The workflow is similar to GeoServer. You create Stores from data sources. From Stores, you create Layers. </p>
						<p>Creation of Stores is simplified by providing only two Store types: QGIS and PostGIS</p>

						
						<h1 style="font-size:22px; background-color: ##6BA81E!important;">QGIS Stores</h1>						
						<p>QGIS Stores consists of your QGIS Project and any flat files required. Flat files can be Raster files or Vector files</p>
							<ul>
								<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Raster Files:  TIFF, GeoTIFF, JPEG, PNG, etc...</li>
								<li style="padding: 0 0 10px 15px; background-image: url(../../assets/images/backgrounds/li.gif);">Vector Files:  GeoPackage, ESRI GeoDatabase, ESRI Shapfile, GeoJson, etc..</li>
						</ul>

						<p>If your QGIS Project uses a PostGIS source, you can create a PostGIS Store for it. </p>

						<p><a href="https://quail.docs.acugis.com/en/latest/sections/qgisstore/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>

						<h1 style="font-size:22px">PostGIS Stores</h1>
						<p>PostGIS Stores are any Local and remote PostGIS connections.</p>
						<p> You can also create PostGIS databases from a variety of formats, such as geopackages and backups. </p>
						<p><a href="https://quail.docs.acugis.com/en/latest/sections/postgisstores/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>


						<h1 style="font-size:22px">OGC Web Services</h1>
						<p>Publish WMS, WFS, and WMTS publically or via authentication.</p> <p><a href="https://quail.docs.acugis.com/en/latest/sections/capabilities/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>

						<h1 style="font-size:22px">MapProxy</h1>
						<p>Quail also installs MapProxy, for caching. Quail Authentication is integrated with MapProxy. Layers can also be seeded in advance.</p> 
						<p><a href="https://quail.docs.acugis.com/en/latest/sections/mapproxy/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>

						<h1 style="font-size:22px">Users and Groups</h1>
						<p>Create Users and Groups. All Stores and Layers provide Group level permissions. </p>
						<p><a href="https://quail.docs.acugis.com/en/latest/sections/users/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>

						<h1 style="font-size:22px">Layer Portal</h1>
						<p>Quail has a built in Layer Portal with User and Group level access. Users can log directly into your Layer Portal as see only those layers they have permissions for.</p>
						<p><a href="https://quail.docs.acugis.com/en/latest/sections/viewer/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>

						<h1 style="font-size:22px">Documentation</h1>
						
						<p><a href="https://quail.docs.acugis.com/en/latest/index.html" target="_blank">Read the Docs&nbsp;<i class="fa fa-external-link"></i> </a></p>	

						
						
				</div>
		</div>

<?php include("admin/incl/footer.php"); ?>
</body>
</html>
