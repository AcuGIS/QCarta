<?php
    session_start(['read_and_close' => true]);
	require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
    require('class/topic.php');
		
	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit(0);
    }
		
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();

    $obj     = new topic_Class($dbconn, $_SESSION[SESS_USR_KEY]->id, 'gemet');

    $topics  = $obj->getRows();
    
    $resources = [];
    foreach(TOPIC_RESOURCES as $type){
        $resources[$type] = $database->select('SELECT id,name from public.'.$type);
    }
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
	<script>
	  const tbl_action = 'gemet';
	</script>
	<script src="assets/dist/js/topics.js"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --border-color: #e9ecef;
            --text-color: #2c3e50;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--background-color);
        }
        body {
            min-height: 100vh;
            overflow-y: auto;
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
            padding-left: 1.5rem;
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

        .editor-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .preview-map {
            width: 100%;
            min-width: 100%;
            height: 300px;
            min-height: 300px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-top: 20px;
            position: relative;
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
    <script>const TOPIC_RESOURCES = <?=json_encode(TOPIC_RESOURCES)?>;</script>
</head>

<body>
  
    <div id="container" style="display:block">
		
		<?php const NAV_SEL = 'Settings'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
			
		<div id="content">
			<div class="content-wrapper">
				<div class="page-header">
					<h1 class="page-title">GEMET Themes</h1>
					<div class="text-end upgrade-btn">
						<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New</a>
					</div>
				</div>
            
            <div class="table-responsive">
                <table class="table table-bordered" id="sortTable">
					<thead>
						<tr>
							<th data-name="id" data-editable='false'>ID</th>
							<th data-name="name">Name</th>
							<th data-name="description">Description</th>
							<th data-editable='false' data-action='true'>Actions</th>
						</tr>
					</thead>

					<tbody> <?php while($row = pg_fetch_object($topics)){
					        $typeIds = $obj->getTypeIds($row->id);
					    ?>
					    <tr align="left"
							data-id="<?=$row->id?>"
							<?php foreach($typeIds as $k => $v){ ?>
							data-<?=$k?>_id="<?=join(',',$v)?>"
							<?php } ?>>
							<td><?=$row->id?> </td>
							<td><?=$row->name?></td>
							<td><?=$row->description?></td>
							<td>
								<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
								<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
							</td>
						</tr> <?php } ?>
					</tbody>
				</table>
            </div>
        </div>
    </div>
    
    
    <div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header bg-primary text-white">
						<h4 class="modal-title mb-0">
							<i class="bi bi-plus-circle me-2"></i>Create New GEMET Theme
						</h4>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body p-4" id="addnew_modal_body">
						<form id="topic_form" action="" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<!-- Basic Information Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-info-circle me-2"></i>Theme Information
									</h6>
								</div>
								<div class="col-md-6 mb-3">
									<label for="name" class="form-label fw-semibold">
										<i class="bi bi-tag me-1"></i>Theme Name
									</label>
									<input type="text" class="form-control" id="name" name="name" required 
										   placeholder="Enter theme name"/>
								</div>
								<div class="col-md-6 mb-3">
									<label for="description" class="form-label fw-semibold">
										<i class="bi bi-file-earmark-text me-1"></i>Description
									</label>
									<input type="text" class="form-control" id="description" name="description" required 
										   placeholder="Enter theme description"/>
								</div>
							</div>

							<!-- Resource Associations Section -->
							<div class="row mb-3">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-diagram-3 me-2"></i>Resource Associations
									</h6>
								</div>
								<?php foreach(TOPIC_RESOURCES as $type){ ?>
								<div class="col-md-6 mb-3">
									<label for="<?=$type?>_id" class="form-label fw-semibold">
										<i class="bi bi-collection me-1"></i><?=ucfirst($type)?>
									</label>
									<select name="<?=$type?>_id[]" id="<?=$type?>_id" multiple class="form-select" style="min-height: 100px;">
										<?php foreach($resources[$type] as $res_row){ ?>
											<option value="<?=$res_row['id']?>"><?=$res_row['name']?></option>
										<?php }?>
									</select>
									<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple <?=$type?></small>
								</div>
								<?php } ?>
							</div>
						</form>
					</div>
					<div class="modal-footer bg-light border-top">
						<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
							<i class="bi bi-x-circle me-1"></i>Cancel
						</button>
						<button type="button" class="btn btn-primary activate" id="btn_create">
							<i class="bi bi-check-circle me-1"></i>Create Theme
						</button>
					</div>
				</div>
			</div>
		</div>
    
    <?php include("incl/footer.php"); ?>
    <script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
