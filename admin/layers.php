<?php
    session_start(['read_and_close' => true]);
	require('incl/const.php');
	require('incl/app.php');
    require('class/database.php');
	require('class/table.php');
	require('class/table_ext.php');
	
	require('class/pglink.php');
	require('class/qgs.php');
	
	require('class/layer.php');
	require('class/pg_layer.php');
	require('class/qgs_layer.php');
	require('class/access_group.php');
	require('class/basemap.php');

	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit(1);
    }
		
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$grp_obj = new access_group_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	$groups = $grp_obj->getArr();
	
	$basemap_obj = new basemap_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	$basemaps = $basemap_obj->getArr();
	
	$tab = empty($_GET['tab']) ? 'qgs' : $_GET['tab'];
	$obj = null;
	$store_obj = null;
	
	switch($tab){
		case 'qgs':	$obj = new qgs_layer_Class($dbconn, 		$_SESSION[SESS_USR_KEY]->id);
								$store_obj = new qgs_Class($dbconn, 		$_SESSION[SESS_USR_KEY]->id); break;
		case 'pg':  $obj = new pg_layer_Class($dbconn, 	$_SESSION[SESS_USR_KEY]->id);
								$store_obj = new pglink_Class($dbconn,	$_SESSION[SESS_USR_KEY]->id); break;
		default:		die('Error: Invalid tab'); break;
	}

	$rows   = $obj->getRows();
	$stores = $store_obj->getArr();
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
		
		$(document).on("click", ".add-modal", function() {
			edit_row = null;
			$('#addnew_modal').modal('show');
			$('#btn_create').html('Create');
			
			$('#id').val(0);
			
			if($('#store_id').length > 0){	// if PG tab
				$('#store_id').trigger('change');	// trigger change to reload selects
			}
		});
	});
	</script>
	<script src="assets/dist/js/html5_uploader.js"></script>
	<script src="assets/dist/js/layer_<?=$tab?>.js"></script>



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
	color: #212529;
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

button.btn-warning:hover, button.btn-warning:focus,
a.btn-warning:hover, a.btn-warning:focus,
.btn-warning:hover, .btn-warning:focus {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
}

.btn:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Enhanced Modal styles */
.modal-content {
	border: none;
	border-radius: 16px;
	box-shadow: 0 20px 60px rgba(0,0,0,0.15);
	background: #fff;
	color: #2c3e50;
	overflow: hidden;
}

.modal-header {
	background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
	border-bottom: none;
	padding: 1.5rem 2rem;
	border-radius: 16px 16px 0 0;
}

.modal-header.bg-primary {
	background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
}

.modal-title {
	color: #fff !important;
	font-size: 1.4rem;
	font-weight: 600;
	margin: 0;
}

.modal-body {
	padding: 2rem;
	background-color: #fff;
	max-height: 70vh;
	overflow-y: auto;
}

.modal-footer {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	background-color: #f8f9fa;
	border-top: 1px solid #e9ecef;
	padding: 1.5rem 2rem;
	border-radius: 0 0 16px 16px;
}

.modal-footer.bg-light {
	background-color: #f8f9fa !important;
}

.modal-backdrop.show {
	opacity: 0.6;
	backdrop-filter: blur(2px);
}

.modal-dialog {
	margin: 2rem auto;
	max-width: 800px;
	width: 95vw;
}

.modal-dialog.modal-lg {
	max-width: 900px;
}

/* Form styling improvements */
.modal .form-label {
	color: #495057 !important;
	font-weight: 600;
	margin-bottom: 0.5rem;
}

.modal .form-control,
.modal .form-select {
	width: 100% !important;
	background: #fff;
	color: #495057;
	border: 2px solid #e9ecef;
	border-radius: 8px;
	padding: 0.75rem 1rem;
	transition: all 0.3s ease;
	font-size: 0.95rem;
}

.modal .form-control:focus,
.modal .form-select:focus {
	border-color: #0d6efd;
	box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
	background: #fff;
	color: #495057;
	transform: translateY(-1px);
}

.modal .form-control::placeholder {
	color: #6c757d;
	font-style: italic;
}

/* Checkbox styling */
.modal .form-check-input {
	width: 1.2rem;
	height: 1.2rem;
	margin-top: 0.1rem;
	border: 2px solid #dee2e6;
	border-radius: 4px;
	transition: all 0.3s ease;
}

.modal .form-check-input:checked {
	background-color: #0d6efd;
	border-color: #0d6efd;
}

.modal .form-check-input:focus {
	box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
}

.modal .form-check-label {
	color: #495057 !important;
	font-weight: 500;
	margin-left: 0.5rem;
	cursor: pointer;
}

/* Section headers */
.modal h6 {
	font-size: 1rem;
	font-weight: 600;
	color: #0d6efd !important;
	margin-bottom: 1rem;
	padding-bottom: 0.5rem;
	border-bottom: 2px solid #e9ecef;
}

.modal h6 i {
	color: #0d6efd;
}

/* Button styling */
.modal .btn {
	padding: 0.75rem 1.5rem;
	border-radius: 8px;
	font-weight: 600;
	font-size: 0.95rem;
	transition: all 0.3s ease;
	border: 2px solid transparent;
}

.modal .btn-primary {
	background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
	border-color: #0d6efd;
	color: #fff;
}

.modal .btn-primary:hover {
	background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
	border-color: #0b5ed7;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(13,110,253,0.3);
}

.modal .btn-outline-secondary {
	border-color: #6c757d;
	color: #6c757d;
	background: transparent;
}

.modal .btn-outline-secondary:hover {
	background-color: #6c757d;
	border-color: #6c757d;
	color: #fff;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(108,117,125,0.3);
}

/* Close button */
.modal .btn-close {
	font-size: 1.2rem;
	font-weight: 400;
	opacity: 0.8;
	transition: opacity 0.3s ease;
	color: #fff;
}

.modal .btn-close:hover {
	opacity: 1;
	transform: scale(1.1);
}

.modal .btn-close-white {
	filter: invert(1);
}

/* Form text styling */
.modal .form-text {
	font-size: 0.85rem;
	color: #6c757d;
	margin-top: 0.25rem;
}

/* Icon styling */
.modal .bi {
	font-size: 0.9rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
	.modal-dialog {
		margin: 1rem;
		width: calc(100vw - 2rem);
	}
	
	.modal-body {
		padding: 1.5rem;
	}
	
	.modal-header,
	.modal-footer {
		padding: 1rem 1.5rem;
	}
}

/* Animation for modal appearance */
.modal.fade .modal-dialog {
	transform: scale(0.8) translateY(-50px);
	transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
	transform: scale(1) translateY(0);
}

/* Scrollbar styling for modal body */
.modal-body::-webkit-scrollbar {
	width: 6px;
}

.modal-body::-webkit-scrollbar-track {
	background: #f1f1f1;
	border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
	background: #c1c1c1;
	border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
	background: #a8a8a8;
}
</style>


</head>
 
<body>

<?php const NAV_SEL = 'Layers'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>

		<div id="container" style="display:block">
		
			
			<br class="clear">
			<?php include("incl/sidebar.php"); ?>
				
				<div id="content">
					<div class="content-wrapper">
						<div class="page-header">
							<h1 class="page-title">Layers</h1>
							<div class="text-end upgrade-btn">
								<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New</a>
								<?php if($tab == 'qgs') { ?>
								    <a class="btn btn-secondary addproject-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-folder-plus"></i> Add from Project</a>
								<?php } ?>
							</div>
						</div>
						<ul class="nav nav-tabs">
							<li class="nav-item"><a class="nav-link <?php if($tab == 'qgs') { ?> active <?php } ?>" href="layers.php?tab=qgs" style="font-size:14px"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;QGS</a></li>
							<li class="nav-item"><a class="nav-link <?php if($tab == 'pg') { ?> active <?php } ?>" href="layers.php?tab=pg" style="font-size:14px"><i class="bi bi-arrow-right-square"></i>&nbsp;&nbsp;PostGIS</a> </li>
						</ul>
						<?php
							if(is_file('incl/layer_'.$tab.'.php')){
								require('incl/layer_'.$tab.'.php');
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
