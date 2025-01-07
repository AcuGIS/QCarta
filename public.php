<?php
  session_start();
	require('admin/incl/const.php');
	require('admin/class/database.php');
	require('admin/class/table.php');
	require('admin/class/table_ext.php');
	require('admin/class/layer.php');
	require('admin/class/qgs_layer.php');
	require('admin/class/qgs.php');
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$qgs_obj  	= new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
	$qgslay_obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
	
	$stores = $qgs_obj->getPublic();
	$layers = $qgslay_obj->getPublic();
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
		
			<?php const NAV_SEL = 'Layers'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>
				
				<div id="content">

						
						<div style="width: 75%">
						
							<h1>Get Capabilities and OpenLayers</h1>
						
						<!-- Stores -->
			<div class="row row-cols-1 row-cols-md-4 g-4">
				<?php while ($row = pg_fetch_object($stores)) { ?>
					<div class="col">
						<div class="card">
							<div class="card-body">
								<h5 class="card-title" style="font-size: 15px; font-weight: 800;"><?=$row->name?></h5>
							</div>
							<div class="px-3">
								 <a href="stores/<?=$row->id?>/wms.php?REQUEST=GetCapabilities" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;" target="_blank">
									 <img src="assets/images/wms.svg" alt="WMS" width="40" height="40">
								 </a>
	 							<a href="stores/<?=$row->id?>/wfs.php?REQUEST=GetCapabilities" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;" target="_blank">
									<img src="assets/images/wfs.svg" alt="WFS" width="40" height="40">
								</a>
	 							<a href="stores/<?=$row->id?>/wmts.php?REQUEST=GetCapabilities" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;" target="_blank">
									<img src="assets/images/wmts.svg" alt="WMTS" width="40" height="40">
								</a>
	 							<a href="stores/<?=$row->id?>/wms.php?REQUEST=GetProjectSettings" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;" target="_blank">
									<img src="assets/images/openlayers.svg" alt="OpenLayers" width="40" height="40">
								</a>
							</div>
						</div>
					</div>
				<?php }
					pg_free_result($stores);
				?>
			</div>
						
			
			<!-- Layers -->
						<p>&nbsp;</p>
						<h1>Leaflet Preview</h1>
			<div class="row row-cols-1 row-cols-md-4 g-4">
				
				<?php while ($row = pg_fetch_object($layers)) {
					$image = file_exists("assets/layers/".$row->id.".png") ? "assets/layers/".$row->id.".png" : "assets/layers/default.png"; ?>
					<div class="col">
							<a href="layers/<?=$row->id?>/index.php" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;" target="_blank">
								<div class="card">
									<div class="card-body">
										<h5 class="card-title" style="font-size: 15px; font-weight: 800;"><?=$row->name?></h5>
									</div>
									<div class="px-3">
										 <div style="height: 150px; width: 100%; background: url('<?=$image?>') no-repeat; background-size: cover; background-position: center center;"></div>
									</div>
								</div>
						</a>
					</div>
				<?php }
					pg_free_result($layers);
				?>
			</div>
		</div>
  </div>

</div>
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
