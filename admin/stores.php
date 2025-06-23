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
	
	$with_qfield = is_file('class/qfield_util.php');
	if($with_qfield){
		require('class/qfield_util.php');
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
	$qf_rows = null;
	
	switch($tab){
		case 'qgs':	$obj 		= new qgs_Class($dbconn, 		$_SESSION[SESS_USR_KEY]->id);
								$pg_obj = new pglink_Class($dbconn, 	$_SESSION[SESS_USR_KEY]->id);
								$pg_stores = $pg_obj->getArr();
								if($with_qfield){
									$qf_obj = new qfield_util_Class();
									$qf_rows	 = $qf_obj->getRows();
								}
								break;
		
		case 'pg':  $obj = new pglink_Class($dbconn, 	$_SESSION[SESS_USR_KEY]->id); break;
		default:		die('Error: Invalid tab'); break;
	}
	$rows = $obj->getRows();
?>



<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">

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
	<script src="assets/dist/js/html5_uploader.js"></script>
	<script src="assets/dist/js/stores_<?=$tab?>.js"></script>

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

.content-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    padding-right: 1.5rem;
    margin-bottom: 1rem;
    height: 100%;
    padding=left: 1.5rem;
    padding-bottom: 1.5rem;
}		
.page-header {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	margin-bottom: 1.5rem;
	padding-bottom: 1rem;
	border-bottom: 1px solid var(--border-color);
}

.page-header .text-end {
	margin-top: 0.5rem;
	margin-left: 0;
	align-self: flex-start;
}

.page-title {
	color: var(--text-color);
	font-size: 1.75rem;
	font-weight: 600;
	margin: 0;
}

.nav-tabs {
	border-bottom: 2px solid var(--border-color);
	margin-bottom: 1.5rem;
}

.nav-tabs .nav-link {
	color: var(--secondary-color);
	border: none;
	padding: 0.75rem 1.25rem;
	transition: all 0.3s ease;
	font-weight: 500;
}

.nav-tabs .nav-link:hover {
	color: var(--primary-color);
	background: transparent;
}

.nav-tabs .nav-link.active {
	color: var(--primary-color);
	border-bottom: 2px solid var(--primary-color);
	background: transparent;
}

.btn {
	padding: 0.5rem 1rem;
	font-weight: 500;
	border-radius: 6px;
	transition: all 0.3s ease;
}

.btn-primary {
	background-color: var(--primary-color);
	border-color: var(--primary-color);
	color: #fff;
}

.btn-warning {
	background-color: #ffc107;
	border-color: #ffc107;
	color: #000;
}

.btn:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-primary:hover, .btn-primary:focus {
	background-color: #0b5ed7;
	border-color: #0a58ca;
	color: #fff;
}

.btn-warning:hover, .btn-warning:focus {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
}

.btn-secondary:hover, .btn-secondary:focus {
	background-color: #495057;
	border-color: #495057;
	color: #fff;
}

/* Modal styles */
.modal-content {
	border: none;
	border-radius: 12px;
	box-shadow: 0 10px 30px rgba(0,0,0,0.12);
	background: #fff;
	color: #2c3e50;
}

.modal-header {
	background-color: #fff;
	border-bottom: 1px solid #e9ecef;
	padding: 1.25rem 2rem 1.25rem 2rem;
	border-radius: 12px 12px 0 0;
}

.modal-title {
	color: #2c3e50 !important;
	font-size: 1.25rem;
	font-weight: 600;
}

.modal-body {
	padding: 1.25rem 2rem 1.25rem 2rem;
	background-color: #fff;
	max-height: none;
	overflow: visible;
}

.modal-footer {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	background-color: #fff;
	border-top: 1px solid #e9ecef;
	padding: 1.25rem 2rem 1.25rem 2rem;
	border-radius: 0 0 12px 12px;
}

.modal-backdrop.show {
	opacity: 0.5;
}

.modal-dialog {
	margin: 2.5rem auto;
	max-width: 600px;
	width: 95vw;
}

.modal .form-group label,
.modal .form-label {
	color: #2c3e50 !important;
}

.modal .form-control {
	width: 100% !important;
	background: #fff;
	color: #2c3e50;
	border: 1px solid #ced4da;
	border-radius: 6px;
}

.modal .form-control:focus {
	border-color: #0d6efd;
	box-shadow: 0 0 0 0.2rem rgba(13,110,253,.10);
	background: #fff;
	color: #2c3e50;
}

.modal .btn {
	padding: 0.5rem 1.5rem;
	border-radius: 6px;
	font-weight: 500;
}

.modal .btn-primary {
	background-color: #0d6efd;
	border-color: #0d6efd;
	color: #fff;
}

.modal .btn-secondary {
	background-color: #6c757d;
	border-color: #6c757d;
	color: #fff;
}

.modal .btn-secondary:hover {
	background-color: #495057;
	border-color: #495057;
}

.modal .close {
	font-size: 1.5rem;
	font-weight: 400;
	opacity: 0.7;
	transition: opacity 0.3s ease;
	color: #2c3e50;
}

.modal .close:hover {
	opacity: 1;
}

.modal-body form {
	width: 100% !important;
	margin: 0 auto !important;
	padding: 0;
}

.btn-warning.text-white:hover, .btn-warning.text-white:focus {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
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
					<div class="content-wrapper">
						<div class="page-header">
							<h1 class="page-title">Stores</h1>
							<div class="text-end upgrade-btn">
								<?php if($tab == 'pg') { ?>
								<a class="btn btn-warning import-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-box-arrow-in-up"></i> Create</a>
								<?php } ?>
								<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New</a>
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
		</div>
<?php include("incl/footer.php"); ?>
<script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
