<?php
    session_start(['read_and_close' => true]);
	require('incl/const.php');
    require('class/database.php');
	require('class/table.php');
	require('class/basemap.php');
	require('class/access_group.php');

	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit(1);
    }
		
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$grp_obj = new access_group_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	$groups = $grp_obj->getArr();
	
	$obj = new basemap_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	$rows = $obj->getRows();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta - Basemap Management</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">

	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">
	<script src="assets/dist/js/basemap.js"></script>
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
    padding: 1.5rem;
    margin-bottom: 1rem;
    height: 100%;
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

.modal-body form {
	width: 100% !important;
	margin: 0 auto !important;
	padding: 0;
}

.table th {
	background-color: #f8f9fa;
	border-color: #dee2e6;
	font-weight: 600;
	color: #495057;
}

.table td {
	vertical-align: middle;
}

.badge {
	font-size: 0.75em;
	padding: 0.35em 0.65em;
}

.badge-success {
	background-color: #28a745;
}

.badge-secondary {
	background-color: #6c757d;
}
</style>

</head>
 
<body>
	<div id="container" style="display:block">
		<?php const NAV_SEL = 'Basemaps'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
			
		<div id="content">
			<div class="content-wrapper">
				<div class="page-header">
					<h1 class="page-title">Basemap Management</h1>
					<div class="text-end upgrade-btn">
						<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New Basemap</a>
					</div>
				</div>
				
				<div class="table-responsive">
					<table class="table table-striped table-hover" id="sortTable">
						<thead>
							<tr>
								<th>Thumbnail</th>
								<th>Name</th>
								<th>Description</th>
								<th>Type</th>
								<th>URL</th>
								<th>Zoom Range</th>
								<th>Public</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php while ($row = pg_fetch_assoc($rows)) {
							    $row_grps = $grp_obj->getByKV('basemaps', $row['id']);
                                $thumb = $row['thumbnail'] ?? '';
                                // If $thumb is already an absolute URL (http/https), leave it as-is.
                                if (preg_match('#^https?://#i', $thumb)) {
                                    $src = $thumb;
                                } else {
                                    $baseUrlPath = '/assets/images'; // public URL path, not filesystem path
                                    $src = rtrim($baseUrlPath, '/') . '/' . ltrim($thumb, '/');
                                }
?>

							<tr data-id="<?=$row['id']?>" data-name="<?=$row['name']?>" data-description="<?=$row['description']?>" data-url="<?=$row['url']?>" data-type="<?=$row['type']?>" data-attribution="<?=$row['attribution']?>" data-thumbnail="<?=$row['thumbnail'] ?? ''?>" data-min-zoom="<?=$row['min_zoom']?>" data-max-zoom="<?=$row['max_zoom']?>" data-public="<?=$row['public']?>" data-group_id="<?=implode(',', array_keys($row_grps))?>">
								<td>
									<?php if (!empty($row['thumbnail'])) { ?>
										<img src="<?=$src?>" alt="<?=$row['name']?>" class="img-thumbnail" style="width: 80px; height: 60px; object-fit: cover;">
									<?php } else { ?>
										<div class="bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 60px; border: 1px solid #dee2e6; border-radius: 4px;">
											<i class="bi bi-image text-muted"></i>
										</div>
									<?php } ?>
								</td>
								<td data-order="<?=$row['name']?>"><strong><?=$row['name']?></strong></td>
								<td><?=$row['description']?></td>
								<td data-order="<?=$row['type']?>"><span class="badge badge-secondary"><?=$row['type']?></span></td>
								<td data-order="<?=$row['url']?>"><small><?=substr($row['url'], 0, 50) . (strlen($row['url']) > 50 ? '...' : '')?></small></td>
								<td><?=$row['min_zoom']?> - <?=$row['max_zoom']?></td>
								<td data-order="<?=$row['public']?>">
									<?php if ($row['public'] == 't') { ?>
										<span class="badge badge-success">Public</span>
									<?php } else { ?>
										<span class="badge badge-secondary">Private</span>
									<?php } ?>
								</td>
								<td>
    								<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
    								<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
								</td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Add/Edit Modal -->
	<div class="modal fade" id="addnew_modal" tabindex="-1" role="dialog" aria-labelledby="addnew_modal_label" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title mb-0" id="addnew_modal_label">
						<i class="bi bi-plus-circle me-2"></i>Add New Basemap
					</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-4">
					<form id="basemap_form">
						<input type="hidden" id="id" name="id" value="0">
						
						<!-- Basic Information Section -->
						<div class="row mb-4">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-info-circle me-2"></i>Basic Information
								</h6>
							</div>
							<div class="col-md-6 mb-3">
								<label for="name" class="form-label fw-semibold">
									<i class="bi bi-map me-1"></i>Basemap Name *
								</label>
								<input type="text" class="form-control" id="name" name="name" required 
									   placeholder="Enter basemap name"/>
							</div>
							<div class="col-md-6 mb-3">
								<label for="type" class="form-label fw-semibold">
									<i class="bi bi-gear me-1"></i>Type *
								</label>
								<select class="form-select" id="type" name="type" required>
									<option value="xyz">XYZ Tiles</option>
									<option value="wms">WMS</option>
									<option value="wmts">WMTS</option>
									<option value="tms">TMS</option>
								</select>
							</div>
							<div class="col-12 mb-3">
								<label for="description" class="form-label fw-semibold">
									<i class="bi bi-file-earmark-text me-1"></i>Description
								</label>
								<textarea class="form-control" id="description" name="description" rows="3" 
										  placeholder="Enter basemap description"></textarea>
							</div>
						</div>

						<!-- URL Configuration Section -->
						<div class="row mb-4">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-globe me-2"></i>URL Configuration
								</h6>
							</div>
							<div class="col-12 mb-3">
								<label for="url" class="form-label fw-semibold">
									<i class="bi bi-link me-1"></i>Tile URL *
								</label>
								<input type="url" class="form-control" id="url" name="url" required 
									   placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png"/>
								<small class="form-text text-muted">Use {z}, {x}, {y} placeholders for tile coordinates</small>
							</div>
							<div class="col-12 mb-3">
								<label for="attribution" class="form-label fw-semibold">
									<i class="bi bi-copyright me-1"></i>Attribution
								</label>
								<input type="text" class="form-control" id="attribution" name="attribution" 
									   placeholder="© OpenStreetMap contributors"/>
							</div>
						</div>

						<!-- Zoom Configuration Section -->
						<div class="row mb-4">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-zoom-in me-2"></i>Zoom Configuration
								</h6>
							</div>
							<div class="col-md-6 mb-3">
								<label for="min_zoom" class="form-label fw-semibold">
									<i class="bi bi-zoom-out me-1"></i>Min Zoom
								</label>
								<input type="number" class="form-control" id="min_zoom" name="min_zoom" 
									   min="0" max="22" value="0"/>
							</div>
							<div class="col-md-6 mb-3">
								<label for="max_zoom" class="form-label fw-semibold">
									<i class="bi bi-zoom-in me-1"></i>Max Zoom
								</label>
								<input type="number" class="form-control" id="max_zoom" name="max_zoom" 
									   min="0" max="22" value="18"/>
							</div>
						</div>

						<!-- Thumbnail Section -->
						<div class="row mb-4">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-image me-2"></i>Thumbnail
								</h6>
							</div>
							<div class="col-12 mb-3">
								<label for="thumbnail" class="form-label fw-semibold">
									<i class="bi bi-file-image me-1"></i>Thumbnail Filename
								</label>
								<input type="text" class="form-control" id="thumbnail" name="thumbnail" 
									   placeholder="esri.png"/>
								<small class="form-text text-muted">Filename of thumbnail image in /assets/images folder (150x100px recommended)</small>
							</div>
						</div>

						<!-- Access Control Section -->
						<div class="row mb-4">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-shield-lock me-2"></i>Access Control
								</h6>
							</div>
							<div class="col-md-6 mb-3">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" id="public" name="public" value="1"/>
									<label class="form-check-label fw-semibold" for="public">
										<i class="bi bi-globe me-1"></i>Public (visible to all users)
									</label>
								</div>
							</div>
						</div>
						
						<!-- Access Groups Section -->
						<div class="row mb-3">
							<div class="col-12">
								<h6 class="text-primary mb-3 border-bottom pb-2">
									<i class="bi bi-people me-2"></i>Access Groups
								</h6>
							</div>
							<div class="col-12 mb-3">
								<label for="group_id" class="form-label fw-semibold">
									<i class="bi bi-shield-check me-1"></i>Select Access Groups
								</label>
								<select class="form-select" id="group_id" name="group_id[]" multiple style="min-height: 100px;">
									<?php foreach ($groups as $gid => $gname) { ?>
										<option value="<?=$gid?>"><?=$gname?></option>
									<?php } ?>
								</select>
								<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple groups. Leave empty for no restrictions.</small>
							</div>
						</div>
					</form>
				</div>
				<div class="modal-footer bg-light border-top">
					<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
						<i class="bi bi-x-circle me-1"></i>Cancel
					</button>
					<button type="button" class="btn btn-primary" id="btn_create">
						<i class="bi bi-check-circle me-1"></i>Create Basemap
					</button>
				</div>
			</div>
		</div>
	</div>

<?php include("incl/footer.php"); ?>
<script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
