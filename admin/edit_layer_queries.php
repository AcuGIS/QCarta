<?php
    session_start(['read_and_close' => true]);
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');    
    require('class/layer_query.php');
    
    if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit;
    }

    const QUERY_TYPES = ['gpkg' => 'GeoPackage', 'postgres' => 'PostgreSQL', 'shp' => 'ShapeFile', 'gdb' => 'ESRI Geodatabase'];
    
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$id = (empty($_GET['id'])) ? -1 : intval($_GET['id']);
	
	$obj = new layer_query_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	if(($id <= 0) && !$obj->isOwnedByUs($id)){
		http_response_code(405);	//not allowed
		die(405);
	}	
	$rows = $obj->getRows();
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" >

<head>
	<title>QCarta</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/sql-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/sql/sql.min.js"></script>
		
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">

	<script type="text/javascript">
	$(document).ready(function() {
		$('[data-toggle="tooltip"]').tooltip();
		
		$(document).on("click", ".add-modal", function() {
			edit_row = null;
			$('#id').val(0);
			$('#addnew_modal').modal('show');
		});
	});
	</script>
	<style>
	.CodeMirror {
	  border: 1px solid #eee;
	  height: auto;
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

	/* CodeMirror styling */
	.modal .CodeMirror {
		border: 2px solid #e9ecef;
		border-radius: 8px;
		transition: all 0.3s ease;
	}

	.modal .CodeMirror:focus-within {
		border-color: #0d6efd;
		box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
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

	/* Table styling improvements */
	.table-responsive {
		border-radius: 8px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}

	.table {
		margin-bottom: 0;
		background-color: #fff;
	}

	.table th {
		background-color: #f8f9fa;
		border-color: #dee2e6;
		font-weight: 600;
		color: #495057;
		padding: 1rem 0.75rem;
		white-space: nowrap;
		position: sticky;
		top: 0;
		z-index: 10;
	}

	.table td {
		vertical-align: middle;
		padding: 0.75rem;
		border-color: #dee2e6;
	}

	/* SQL column styling */
	.table td:nth-child(5) {
		max-width: 300px;
		width: 300px;
		word-wrap: break-word;
		word-break: break-all;
		overflow: hidden;
		text-overflow: ellipsis;
		font-family: 'Courier New', monospace;
		font-size: 0.85rem;
		background-color: #f8f9fa;
		border-left: 3px solid #0d6efd;
		white-space: nowrap;
	}

	/* Badge column styling */
	.table td:nth-child(3) {
		max-width: 120px;
		text-align: center;
	}

	.table td:nth-child(3) span {
		display: inline-block;
		padding: 0.25rem 0.5rem;
		font-size: 0.75rem;
		font-weight: 600;
		border-radius: 4px;
		background-color: #e9ecef;
		color: #495057;
	}

	/* Type column styling */
	.table td:nth-child(4) {
		max-width: 150px;
		text-align: center;
	}

	.table td:nth-child(4) span {
		display: inline-block;
		padding: 0.25rem 0.5rem;
		font-size: 0.75rem;
		font-weight: 600;
		border-radius: 4px;
		background-color: #d1ecf1;
		color: #0c5460;
		border: 1px solid #bee5eb;
	}

	/* Actions column styling */
	.table td:last-child {
		width: 80px;
		min-width: 80px;
		max-width: 80px;
		text-align: center;
		white-space: nowrap;
		flex-shrink: 0;
	}

	.table td:last-child a {
		margin: 0 0.25rem;
		padding: 0.25rem;
		border-radius: 4px;
		transition: all 0.2s ease;
	}

	.table td:last-child a:hover {
		background-color: rgba(0,0,0,0.1);
		transform: scale(1.1);
	}

	/* Responsive table adjustments */
	@media (max-width: 1200px) {
		.table td:nth-child(5) {
			max-width: 250px;
			width: 250px;
		}
	}

	@media (max-width: 992px) {
		.table td:nth-child(5) {
			max-width: 200px;
			width: 200px;
		}
		
		.table td:nth-child(2) {
			max-width: 150px;
			word-wrap: break-word;
		}
	}

	@media (max-width: 768px) {
		.table td:nth-child(5) {
			max-width: 150px;
			width: 150px;
			font-size: 0.75rem;
		}
		
		.table td:nth-child(2) {
			max-width: 120px;
		}
		
		.table th,
		.table td {
			padding: 0.5rem 0.25rem;
		}
	}

	/* Table hover effects */
	.table tbody tr:hover {
		background-color: rgba(13, 110, 253, 0.05);
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		transition: all 0.2s ease;
	}

	/* SQL query tooltip styling */
	.table td:nth-child(5) {
		cursor: help;
		position: relative;
	}

	.table td:nth-child(5):hover::after {
		content: attr(data-full-sql);
		position: absolute;
		top: 100%;
		left: 0;
		right: 0;
		background: #2c3e50;
		color: #fff;
		padding: 0.5rem;
		border-radius: 4px;
		font-size: 0.75rem;
		z-index: 1000;
		word-wrap: break-word;
		white-space: pre-wrap;
		max-width: 400px;
		box-shadow: 0 4px 8px rgba(0,0,0,0.2);
	}
	</style>
	<script src="assets/dist/js/edit_layer_queries.js"></script>
</head>

<body>
  
	<div id="container">
		
		<?php const NAV_SEL = 'SQL Queries'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
		<div id="content">
		
				<h1>Edit SQL Queries for layer <?=$_GET['id']?></h1>
			<div style="width: 99%">
			
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>
								</div>
						</div>
						
						<div class="text-end upgrade-btn">
							<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New</a>
						</div>
				</div>
			
				<div class="table-responsive">
						<table class="table table-bordered" id="sortTable">
							<thead>
								<tr>
									<th data-name="name">Name</th>
									<th data-name="description">Description</th>
									<th data-name="badge">Badge</th>
								    <th data-name="database_type">Type</th>
									<th data-name="sql_query">SQL</th>
									<th data-editable='false' data-action='true'>Actions</th>
								</tr>
							</thead>

							<tbody> <?php while($row = pg_fetch_assoc($rows)) { ?>
								<tr data-id="<?=$row['id']?>" align="left">
									<td><?=$row['name']?></td>
									<td><?=$row['description']?></td>
									<td><span><?=$row['badge']?></span></td>
									<td data-value="<?=$row['database_type']?>"><span><?=QUERY_TYPES[$row['database_type']]?></span></td>
									<td data-full-sql="<?=htmlspecialchars($row['sql_query'])?>"><?=substr($row['sql_query'], 0, 100) . (strlen($row['sql_query']) > 100 ? '...' : '')?></td>
									<td>
										<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
										<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
									</td>
								</tr> <?php } ?>
							</tbody>
						</table>           
					</div>

					<div class="row">
					    <div class="col-8"><p>&nbsp;</p>

								<div class="alert alert-success">
								   <strong>Note:</strong> Manage your layer queries from here. <a href="https://QCarta.docs.acugis.com/en/latest/sections/queries/index.html" target="_blank">Documentation</a>
								</div>
							</div>
					</div>
					
					<div id="addnew_modal" class="modal fade" role="dialog">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header bg-primary text-white">
									<h4 class="modal-title mb-0">
										<i class="bi bi-plus-circle me-2"></i>Create New SQL Query
									</h4>
									<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								
								<div class="modal-body p-4" id="addnew_modal_body">
									<form id="query_form" action="" method="post">
										<input type="hidden" name="action" value="save"/>
										<input type="hidden" name="id" id="id" value="0"/>
										<input type="hidden" name="layer_id" id="layer_id" value="<?=$_GET['id']?>"/>

										<!-- Basic Information Section -->
										<div class="row mb-4">
											<div class="col-12">
												<h6 class="text-primary mb-3 border-bottom pb-2">
													<i class="bi bi-info-circle me-2"></i>Query Information
												</h6>
											</div>
											<div class="col-md-6 mb-3">
												<label for="name" class="form-label fw-semibold">
													<i class="bi bi-tag me-1"></i>Query Name
												</label>
												<input type="text" class="form-control" id="name" name="name" required 
													   placeholder="Enter query name"/>
											</div>
											<div class="col-md-6 mb-3">
												<label for="badge" class="form-label fw-semibold">
													<i class="bi bi-badge me-1"></i>Badge
												</label>
												<input type="text" class="form-control" id="badge" name="badge" required 
													   placeholder="Enter badge text"/>
											</div>
											<div class="col-12 mb-3">
												<label for="description" class="form-label fw-semibold">
													<i class="bi bi-file-earmark-text me-1"></i>Description
												</label>
												<input type="text" class="form-control" id="description" name="description" required 
													   placeholder="Enter query description"/>
											</div>
										</div>

										<!-- Database Configuration Section -->
										<div class="row mb-4">
											<div class="col-12">
												<h6 class="text-primary mb-3 border-bottom pb-2">
													<i class="bi bi-database me-2"></i>Database Configuration
												</h6>
											</div>
											<div class="col-12 mb-3">
												<label for="database_type" class="form-label fw-semibold">
													<i class="bi bi-gear me-1"></i>Database Type
												</label>
												<select class="form-select" id="database_type" name="database_type">
													<?php foreach(QUERY_TYPES as $k => $v){ ?>
														<option value="<?=$k?>"><?=$v?></option>
													<?php } ?>
												</select>
												<small class="form-text text-muted">Select the database type for this query</small>
											</div>
										</div>

										<!-- SQL Query Section -->
										<div class="row mb-3">
											<div class="col-12">
												<h6 class="text-primary mb-3 border-bottom pb-2">
													<i class="bi bi-code-square me-2"></i>SQL Query
												</h6>
											</div>
											<div class="col-12 mb-3">
												<label for="sql_query" class="form-label fw-semibold">
													<i class="bi bi-terminal me-1"></i>SQL Code
												</label>
												<textarea rows="15" cols="150" id="sql_query" placeholder="Enter your SQL query here..." required></textarea>
												<small class="form-text text-muted">Use Ctrl+Space for SQL autocomplete</small>
											</div>
										</div>
									</form>
								</div>
								<div class="modal-footer bg-light border-top">
									<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
										<i class="bi bi-x-circle me-1"></i>Cancel
									</button>
									<button type="button" class="btn btn-primary activate" id="btn_create">
										<i class="bi bi-check-circle me-1"></i>Create Query
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>

			
			</div>
		</div>
</div>
<?php include("incl/footer.php"); ?>
<script>
    var sortTable = new DataTable('#sortTable', { paging: false });
	editor1 = CodeMirror.fromTextArea(document.getElementById("sql_query"), {
		extraKeys: {"Ctrl-Space": "autocomplete"}
	});
	editor1.setSize(420, 100);
</script>

</body>
</html>
