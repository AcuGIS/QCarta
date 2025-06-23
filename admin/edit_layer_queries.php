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
									<td><?=$row['badge']?></td>
									<td data-value="<?=$row['database_type']?>"><?=QUERY_TYPES[$row['database_type']]?></td>
									<td><?=$row['sql_query']?></td>
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
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header">
									<h4 style="color:black!important" class="modal-title">Create Query</h4>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								
								<div class="modal-body" id="addnew_modal_body">
									<form id="query_form" class="border shadow p-3 rounded"
												action="" method="post" style="width: 450px;">

										<input type="hidden" name="action" value="save"/>
										<input type="hidden" name="id" id="id" value="0"/>
										<input type="hidden" name="layer_id" id="layer_id" value="<?=$_GET['id']?>"/>

										<div class="form-group">
											<label for="name">Name</label>
											<input type="text" class="form-control" id="name" placeholder="Enter name" name="name" required>
										</div>
										
										<div class="form-group">
											<label for="description">Description</label>
											<input type="description" class="form-control" id="description" placeholder="Enter description" name="description" required>
										</div>
										
										<div class="form-group">
											<label for="badge">Badge</label>
											<input type="badge" class="form-control" id="badge" placeholder="Enter badge" name="badge" required>
										</div>

										<div class="form-group">
    										<label for="database_type" class="form-label">Database Type</label>
                                            <select class="form-select" id="database_type" name="database_type">
                                                <?php foreach(QUERY_TYPES as $k => $v){ ?>
                                                    <option value="<?=$k?>"><?=$v?></option>
                                                <?php } ?>
                                            </select>
								        </div>

										<div class="form-group">
											<label for="sql_query">SQL</label>
											<textarea rows="30" cols="150" id="sql_query" placeholder="Enter SQL" required></textarea>
										</div>
									</form>
								</div>
								<div class="modal-footer">
									<button type="button" class="activate btn btn-secondary" id="btn_create" data-dismiss="modal">Create</button>
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
