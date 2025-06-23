<?php
    session_start(['read_and_close' => true]);
	require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
    require('class/doc.php');
	require('class/access_group.php');
	require('incl/app.php');
		
	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit(0);
    }
		
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();

    $obj     = new doc_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
    $grp_obj = new access_group_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);

    $docs  = $obj->getRows();
	$groups = $grp_obj->getArr();
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
	<script src="assets/dist/js/html5_uploader.js"></script>
	<script src="assets/dist/js/docs.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        body {
            min-height: 100vh;
            overflow-y: auto;
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
    </style>
    
    <script>
      const post_max_size = <?=return_bytes(ini_get('post_max_size'))?>;
    </script>
</head>

<body>
  
    <div id="container" style="display:block">
		
		<?php const NAV_SEL = 'Documents'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
			
		<div id="content">
			<div class="content-wrapper">
				<div class="page-header">
					<h1 class="page-title">Documents</h1>
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
							<th data-name="filename">Filename</th>
							<th data-editable='false' data-action='true'>Actions</th>
						</tr>
					</thead>

					<tbody> <?php while($row = pg_fetch_object($docs)){
					        $row_grps = $grp_obj->getByKV('doc', $row->id);
					    ?>
					    <tr align="left"
							data-id="<?=$row->id?>"
							data-public="<?=$row->public=='t' ? 'yes' : 'no'?>"
							data-group_id="<?=implode(',', array_keys($row_grps))?>">
							<td><?=$row->id?> </td>
							<td><?=$row->name?></td>
							<td><?=$row->description?></td>
							<td><?=$row->filename?></td>
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
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Create Link</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body" id="addnew_modal_body">
						<form id="doc_form" class="border shadow p-3 rounded"
									action=""
									method="post"
									enctype="multipart/form-data"
									style="width: 450px;">
  
							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<div class="form-group">
								<label for="name">Name</label>
								<input type="text" class="form-control" id="name" placeholder="Enter name" name="name" required>
							</div>
							
							<div class="form-group">
								<label for="description">Description</label>
								<input type="description" class="form-control" id="description" placeholder="Enter description" name="description" required>
							</div>
							
							<div class="form-group">
								<label for="filename">File</label>
								<input type="file" class="form-control" name="filename" id="filename" required />
							</div>
							<div class="progress">
								<div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
							</div>

							<div class="form-group">
    							<label for="image" class="form-label">Thumbnail</label>
    							<input type="file" class="form-control" name="image" id="image" accept=".jpeg,.jpg,.png,.webp"/>
							</div>

							<div class="form-group">
    							<input type="checkbox" name="public" id="public" value="t"/>
    							<label for="public" class="form-label">Public</label>
							</div>

							<div class="form-group">
							    <label for="group_id" style="font-weight: 500; color: #2c3e50; margin-bottom: 0.5rem;">Access Groups</label>
							    <select name="group_id[]" id="group_id" multiple required class="form-control" style="min-height: 70px;">
							        <?php $sel = 'selected';
							        foreach($groups as $k => $v){ ?>
							            <option value="<?=$k?>" <?=$sel?>><?=$v?></option>
							        <?php $sel = ''; } ?>
							    </select>
							</div>							
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="activate btn btn-secondary" id="btn_create" data-dismiss="modal">Create</button>
					</div>
				</div>
			</div>
		</div>
    
    <?php include("incl/footer.php"); ?>
    <script>var sortTable = new DataTable('#sortTable', { paging: false });</script>
</body>
</html>
