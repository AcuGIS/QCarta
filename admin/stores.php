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

/* Checkbox and radio styling */
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

/* Radio button styling */
.modal input[type="radio"] {
	width: 1.1rem;
	height: 1.1rem;
	margin-right: 0.5rem;
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

/* Fieldset styling */
.modal fieldset {
	border: 2px solid #e9ecef;
	border-radius: 8px;
	padding: 1.5rem;
	margin: 1rem 0;
	background: #f8f9fa;
}

.modal legend {
	font-weight: 600;
	color: #0d6efd;
	padding: 0 0.5rem;
	font-size: 1rem;
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

.modal .btn-secondary {
	background-color: #6c757d;
	border-color: #6c757d;
	color: #fff;
}

.modal .btn-secondary:hover {
	background-color: #495057;
	border-color: #495057;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(108,117,125,0.3);
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

.modal .btn-danger {
	background-color: #dc3545;
	border-color: #dc3545;
	color: #fff;
}

.modal .btn-danger:hover {
	background-color: #c82333;
	border-color: #bd2130;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(220,53,69,0.3);
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

/* Input group styling */
.modal .input-group-text {
	background-color: #e9ecef;
	border-color: #ced4da;
	color: #495057;
	font-weight: 500;
}

.modal .input-group .form-control {
	border-right: none;
}

.modal .input-group .form-control:focus {
	border-right: 2px solid #0d6efd;
}

/* Progress bar styling */
.modal .progress {
	height: 8px;
	border-radius: 4px;
	background-color: #e9ecef;
	margin-top: 1rem;
}

.modal .progress-bar {
	background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
	border-radius: 4px;
	transition: width 0.3s ease;
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

.btn-warning.text-white:hover, .btn-warning.text-white:focus {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
}
</style>



</head>
 
<body>

<?php const NAV_SEL = 'Stores'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>

		<div id="container"  style="display:block">
		
			
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
