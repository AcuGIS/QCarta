<?php
  session_start(['read_and_close' => true]);
	require('incl/const.php');
  require('class/database.php');
	require('class/backend.php');
	require('class/mapproxy.php');

	require('class/table.php');
	require('class/table_ext.php');
	
	require('class/layer.php');
	require('class/qgs_layer.php');
		
	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
    header('Location: ../login.php');
    exit;
  }

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();
	
	$bknd = new backend_Class();
	$rows = [];

	$tab = empty($_GET['tab']) ? 'proxy' : $_GET['tab'];

	if($tab == 'proxy'){
		$rows = ['mapproxy'];
	
	}else if($tab == 'seed'){
		$obj = new qgs_layer_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
		$rows = $obj->getRows();

	}else{
		die('Invalid tab');
	}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>Quail Layer Server</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	

	
	<?php include("incl/meta.php"); ?>
	<link href="dist/css/side_menu.css" rel="stylesheet">
	<link href="dist/css/table.css" rel="stylesheet">
	<script src="dist/js/services_<?=$tab?>.js"></script>
	<style>
	.CodeMirror {
	  border: 1px solid #eee;
	  height: auto;
	}
	</style>
</head>
 
<body>

		<div id="container">
		
			<?php const NAV_SEL = 'Services'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>
			<br class="clear">
			<?php include("incl/sidebar.php"); ?>
				
				<div id="content">

						<h1>MapProxy</h1>
		<div style="width: 99%">
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>

									<!--<h1 class="mb-0 fw-bold">Stores</h1><p>&nbsp;</p>-->

								</div>
						</div>
				</div>
				
				<ul class="nav nav-tabs">
					<li class="nav-item"><a class="nav-link <?php if($tab == 'proxy') { ?> active <?php } ?>" href="services.php?tab=proxy"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;Proxy</a></li>
					<li class="nav-item"><a class="nav-link <?php if($tab == 'seed') { ?> active <?php } ?>" href="services.php?tab=seed"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;Seed</a> </li>
				</ul>

				
	<?php require('incl/service_'.$tab.'.php'); ?>


		</div>
	</div>
</div>

<?php include("incl/footer.php"); ?>
<script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
