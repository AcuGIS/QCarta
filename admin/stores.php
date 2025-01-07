<?php
  session_start(['read_and_close' => true]);
	require('incl/const.php');
	require('incl/app.php');
  require('class/database.php');
	require('class/table.php');
	require('class/table_ext.php');
	
	require('class/access_group.php');
  
	require('class/pglink.php');
	require('class/qgs.php');

	function return_bytes($val)
	{
	    $val = trim($val);
	    $num = (int) rtrim($val, 'KMG');
	    $last = strtolower($val[strlen($val) - 1]);

	    switch ($last) {
	        // The 'G' modifier is available
	        case 'g':
	            $num = $num * 1024 * 1024 * 1024;
	            break;
	        case 'm':
	            $num = $num * 1024 * 1024;
	            break;
	        case 'k':
	            $num *= 1024;
	            break;
	    }

	    return $num;
	}

	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
    header('Location: ../login.php');
    exit;
  }
		
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();
	
	$grp_obj = new access_group_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	$groups = $grp_obj->getArr();
	
	$tab = empty($_GET['tab']) ? 'qgs' : $_GET['tab'];
	$obj = null;
	$pg_stores = null;
	
	switch($tab){
		case 'qgs':	$obj 		= new qgs_Class($dbconn, 		$_SESSION[SESS_USR_KEY]->id);
								$pg_obj = new pglink_Class($dbconn, 	$_SESSION[SESS_USR_KEY]->id);
								$pg_stores = $pg_obj->getArr(); break;
		
		case 'pg':  $obj = new pglink_Class($dbconn, 	$_SESSION[SESS_USR_KEY]->id); break;
		default:		die('Error: Invalid tab'); break;
	}
	$rows = $obj->getRows();
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

	<script type="text/javascript">
	var edit_row = null;
	const post_max_size = <?=return_bytes(ini_get('post_max_size'))?>;
	$(document).ready(function() {
		$('[data-toggle="tooltip"]').tooltip();
		
		//$('#addnew_modal').modal('hide');
		//$('#import_modal').modal('hide');
		
		$(document).on("click", ".add-modal", function() {
			edit_row = null;
			$('#id').val(0);
			$('#addnew_modal').modal('show');
			if($('#compress').length > 0){
				$('#compress').prop("disabled", false);
			}
			if($('#btn_upload')){
				$('#btn_upload').html('Upload');
			}
		});
	});
	</script>
	<script src="dist/js/html5_uploader.js"></script>
	<script src="dist/js/stores_<?=$tab?>.js"></script>

<style>

.mt-2 {
    margin-top: .0rem !important;
}


</style>



</head>
 
<body>

		<div id="container"  style="display:block">
		
			<?php const NAV_SEL = 'Stores'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>
			<br class="clear">
			<?php include("incl/sidebar.php"); ?>
				
				<div id="content">

						<h1>Stores</h1>
					<div style="width: 95%">
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav>


								</div>
								<div class="col-6">
										<div class="text-end upgrade-btn">
											<?php if($tab == 'pg') { ?>
											<a class="btn btn-warning text-white import-modal" role="button" aria-pressed="true"><i class="bi bi-box-arrow-in-up"></i> Create</a>
											<?php } ?>
											<a class="btn btn-primary text-white add-modal" role="button" aria-pressed="true"><i class="bi bi-plus-square"></i> Add New</a>
									</div>
								</div>
						</div>
				</div>
				
				<ul class="nav nav-tabs">
					<li class="nav-item"><a class="nav-link <?php if($tab == 'qgs') { ?> active <?php } ?>" href="stores.php?tab=qgs"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;QGIS</a></li>
					<li class="nav-item"><a class="nav-link <?php if($tab == 'pg') { ?> active <?php } ?>" href="stores.php?tab=pg"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;PostGIS</a> </li>
				</ul>
				
	<?php
		if(is_file('incl/store_'.$tab.'.php')){
			require('incl/store_'.$tab.'.php');
		}else{
			die('Error: Invalid tab!');
		}
	?>
		</div>
	</div>
</div>
<?php include("incl/footer.php"); ?>
<script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
