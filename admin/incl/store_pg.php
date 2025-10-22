	<div class="table-responsive">
		<table class="table table-bordered" id="sortTable">

			<thead>
				<tr>
					<!--<th data-name="id" data-editable='false'>ID</th>-->
					<th data-name="name">Name</th>
					<th data-name="group_id" data-type="select">Access Group</th>
					<th data-name="host">Host</th>
					<th data-name="port">Port</th>
					<th data-name="schema">Schema</th>
					<th data-name="dbname">Database</th>
					<th data-name="username">Username</th>
					<th data-name="password">Password</th>
					<th data-editable='false' data-action='true'>Actions</th>
				</tr>
			</thead>

			<tbody> <?php while($row = pg_fetch_object($rows)) {
				$row_grps = $grp_obj->getByKV('store', $row->id);
				
				$store_dir = DATA_DIR.'/stores/'.$row->id;
				$name_icon = '';
				if(is_pid_running($store_dir.'/clone.pid')){					$name_icon = '<a href="../stores/'.$row->id.'/data_filep.php?f=clone.out" title="Clone in progress ..." data-toggle="tooltip"><i class="text-info bi bi-copy"></i></a>';
				}else if(is_pid_running($store_dir.'/restore.pid')){	$name_icon = '<a href="../stores/'.$row->id.'/data_filep.php?f=restore.out" title="Restore in progress ..." data-toggle="tooltip"><i class="text-info bi bi-box-arrow-in-up"></i></a>';
				}else if(is_pid_running($store_dir.'/backup.pid')){		$name_icon = '<a href="../stores/'.$row->id.'/data_filep.php?f=backup.out" title="Backup in progress ..." data-toggle="tooltip"><i class="text-info bi bi-box-arrow-down-square"></i></a>';
				}
			?>
				<tr data-id="<?=$row->id?>" align="left">
					<!--<td><?=$row->id?></td>-->
					<td><?=$row->name?><?=$name_icon?></td>
					<td data-value="<?=implode(',', array_keys($row_grps))?>">
						<?=implode(',', array_values($row_grps))?>
					</td>
					<td><?=$row->host?></td>
					<td><?=$row->port?></td>
					<td><?=$row->schema?></td>
					<td><?=$row->dbname?></td>
					<td><?=$row->username?></td>
					<td>******</td>
					<td>						
						<a class="conn_info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
						<a class="pwd_vis" title="Show Password" data-toggle="tooltip"><i class="text-secondary bi bi-eye"></i></a>
						<?php if(($row->owner_id == $_SESSION[SESS_USR_KEY]->id) || ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID)){ ?>
						<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
						<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
						<a class="clone" title="Clone" data-toggle="tooltip"><i class="text-info bi bi-copy"></i></a>
						<a class="restore" title="Restore" data-toggle="tooltip"><i class="text-info bi bi-box-arrow-in-up"></i></a>
						<a class="backup" title="Backup" data-toggle="tooltip"><i class="text-info bi bi-arrow-down-square"></i></a>
					<?php } ?>
					</td>
				</tr> <?php } ?>
			</tbody>
		</table>
</div>

<div class="row">
    <div class="col-8"><p>&nbsp;</p>

			<div class="alert alert-success">
			   <strong>Note:</strong> Create or connect to PostGIS databases. Zip archives are supported. <a href="https://QCarta.docs.acugis.com/en/latest/sections/postgisstores/index.html" target="_blank"> Documentation</a>

			</div>
		</div>
</div>

<div class="row">
	<pre id='import_output'></pre>
</div>

<div id="conn_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Connection Information</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body"><p>Connection string.</p></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary copy">Copy</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div id="clone_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Clone Database</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body">
				<form id="clone_form" class="border shadow p-3 rounded"
							action=""
							method="post"
							enctype="multipart/form-data"
							style="width: 450px;">

					<input type="hidden" name="clone" value="1"/>
					<input type="hidden" name="id" id="clone_id" value="0"/>

					<div class="form-group">
						<label for="dst_name" class="form-label">Name</label>
						<input type="text" class="form-control" name="dst_name" id="dst_name" value="" required/>
						
						<input type="checkbox" name="locally" value="1" checked>
						<label for="locally" class="form-label">Clone on localhost</label>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="activate btn btn-secondary" id="clone_pglink" data-bs-dismiss="modal">Clone</button>
			</div>
		</div>
	</div>
</div>

<div id="backup_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Backup Database</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body">
				<form id="backup_form" class="border shadow p-3 rounded"
							action=""
							method="post"
							enctype="multipart/form-data"
							style="width: 450px;">

					<input type="hidden" name="backup" value="1"/>
					<input type="hidden" name="id" id="backup_id" value="0"/>

					<div class="form-group">
						<label for="backup_prefix" class="form-label">Name Prefix</label>
						<input type="text" class="form-control" name="backup_prefix" id="backup_prefix" value="" required/>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="activate btn btn-secondary" id="clone_pglink" data-bs-dismiss="modal">Backup</button>
			</div>
		</div>
	</div>
</div>

<div id="restore_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Restore Database</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body">
				<form id="restore_form" class="border shadow p-3 rounded"
							action=""
							method="post"
							enctype="multipart/form-data"
							style="width: 450px;">

					<input type="hidden" name="restore" value="1"/>
					<input type="hidden" name="id" id="restore_id" value="0"/>

					<div class="form-group">
						<label for="dump_file" class="form-label">Dump File</label>
						<select name="dump_file" id="dump_file" required/>
						</select>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="activate btn btn-danger" id="delete_dump">Delete</button>
				<button type="button" class="activate btn btn-secondary" id="restore_pglink" data-bs-dismiss="modal">Restore</button>
			</div>
		</div>
	</div>
</div>

<div id="addnew_modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h4 class="modal-title mb-0">
					<i class="bi bi-plus-circle me-2"></i>Create New PostGIS Connection
				</h4>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body p-4" id="addnew_modal_body">
				<form id="pglink_form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="save" value="1"/>
					<input type="hidden" name="id" id="id" value="0"/>

					<!-- Basic Information Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-info-circle me-2"></i>Basic Information
							</h6>
						</div>
						<div class="col-12 mb-3">
							<label for="name" class="form-label fw-semibold">
								<i class="bi bi-tag me-1"></i>Connection Name
							</label>
							<input type="text" class="form-control" name="name" id="name" required 
								   placeholder="Enter connection name"/>
						</div>
					</div>
					
					<!-- Server Configuration Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-server me-2"></i>Server Configuration
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="host" class="form-label fw-semibold">
								<i class="bi bi-globe me-1"></i>Host Address
							</label>
							<input type="text" class="form-control" name="host" id="host" required 
								   placeholder="localhost or IP address"/>
						</div>
						<div class="col-md-6 mb-3">
							<label for="port" class="form-label fw-semibold">
								<i class="bi bi-ethernet me-1"></i>Port
							</label>
							<input type="number" class="form-control" name="port" id="port" value="5432" required 
								   placeholder="5432"/>
						</div>
					</div>

					<!-- Authentication Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-shield-lock me-2"></i>Authentication
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="username" class="form-label fw-semibold">
								<i class="bi bi-person me-1"></i>Username
							</label>
							<input type="text" class="form-control" name="username" id="username" required 
								   placeholder="Database username"/>
						</div>
						<div class="col-md-6 mb-3">
							<label for="password" class="form-label fw-semibold">
								<i class="bi bi-key me-1"></i>Password
							</label>
							<div class="input-group">
								<input type="password" class="form-control" name="password" id="password" required 
									   placeholder="Database password"/>
								<button type="button" class="btn btn-outline-secondary visibility" title="Toggle password visibility">
									<i class="bi bi-eye"></i>
								</button>
							</div>
						</div>
					</div>
					
					<!-- Database Configuration Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-database me-2"></i>Database Configuration
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="schema" class="form-label fw-semibold">
								<i class="bi bi-folder me-1"></i>Schema
							</label>
							<input type="text" class="form-control" name="schema" id="schema" value="public" required 
								   placeholder="public"/>
						</div>
						<div class="col-md-6 mb-3">
							<label for="dbname" class="form-label fw-semibold">
								<i class="bi bi-database-gear me-1"></i>Database Name
							</label>
							<div class="input-group">
								<input type="text" class="form-control" name="dbname" id="dbname" required 
									   placeholder="Database name"/>
								<button type="button" class="btn btn-outline-secondary list_databases" title="Load database names">
									<i class="bi bi-arrow-clockwise"></i>
								</button>
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
							<select name="group_id[]" id="group_id" class="form-select" multiple required>
								<?php foreach($groups as $k => $v){ ?>
									<option value="<?=$k?>"><?=$v?></option>
								<?php } ?>
							</select>
							<small class="form-text text-muted">Select groups that can access this connection</small>
						</div>
					</div>

				</form>
			</div>
			<div class="modal-footer bg-light border-top">
				<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
					<i class="bi bi-x-circle me-1"></i>Cancel
				</button>
				<button type="button" class="btn btn-primary activate" id="submit_pglink">
					<i class="bi bi-check-circle me-1"></i>Save Connection
				</button>
			</div>
		</div>
	</div>
</div>

<div id="import_modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h4 class="modal-title mb-0">
					<i class="bi bi-download me-2"></i>Create/Import Database
				</h4>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body p-4" id="import_modal_body">
				<?php if (isset($_GET['error'])) { ?>
					<div class="alert alert-danger" role="alert">
						<i class="bi bi-exclamation-triangle me-2"></i><?=$_GET['error']?>
					</div>
				<?php } else if(isset($_GET['success'])) { ?>
					<div class="alert alert-success" role="alert">
						<i class="bi bi-check-circle me-2"></i><?=$_GET['success']?>
					</div>
				<?php } ?>

				<form id="import_form" action="" method="post" enctype="multipart/form-data">
					<!-- Database Information Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-database me-2"></i>Database Information
							</h6>
						</div>
						<div class="col-12 mb-3">
							<label for="dbname" class="form-label fw-semibold">
								<i class="bi bi-tag me-1"></i>Database Name
							</label>
							<input type="text" class="form-control" name="dbname" id="dbname" 
								   placeholder="Enter database name"/>
						</div>
					</div>

					<!-- Data Source Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-cloud-arrow-up me-2"></i>Data Source
							</h6>
						</div>
						<div class="col-12 mb-3">
							<fieldset>
								<legend><i class="bi bi-gear me-1"></i>Select Source Type</legend>
								
								<!-- File Upload Option -->
								<div class="mb-3">
									<div class="form-check mb-2">
										<input type="radio" class="form-check-input" id="src_file_radio" name="store_source" value="1" checked/>
										<label for="src_file_radio" class="form-check-label fw-semibold">
											<i class="bi bi-file-earmark-arrow-up me-1"></i>Upload Files
										</label>
									</div>
									<input type="file" class="form-control" name="source[]" id="import_file" 
										   accept=".gpkg,.shp,.zip,.sql,.dump" multiple required/>
									<small class="form-text text-muted">Supported formats: GPKG, SHP, ZIP, SQL, DUMP</small>
								</div>

								<!-- URL Option -->
								<div class="mb-3">
									<div class="form-check mb-2">
										<input type="radio" class="form-check-input" id="src_url_radio" name="store_source" value="1"/>
										<label for="src_url_radio" class="form-check-label fw-semibold">
											<i class="bi bi-link-45deg me-1"></i>Load from URL
										</label>
									</div>
									<input type="text" class="form-control" name="src_url" id="src_url" 
										   placeholder="Enter URL to load data from" disabled/>
								</div>
							</fieldset>
						</div>
					</div>
					
					<!-- Import Options Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-gear me-2"></i>Import Options
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="create_only" id="create_only" value="1"/>
								<label for="create_only" class="form-check-label fw-semibold">
									<i class="bi bi-plus-circle me-1"></i>Create Database Only
								</label>
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="create_qgs" id="create_qgs" value="1"/>
								<label for="create_qgs" class="form-check-label fw-semibold">
									<i class="bi bi-file-earmark-code me-1"></i>Create QGIS Store Too
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
							<select name="group_id[]" id="group_id" class="form-select" multiple required>
								<?php $sel = 'selected';
								foreach($groups as $k => $v){ ?>
									<option value="<?=$k?>" <?=$sel?>><?=$v?></option>
								<?php $sel = ''; } ?>
							</select>
							<small class="form-text text-muted">Select groups that can access this database</small>
						</div>
					</div>
				</form>

				<!-- Progress Bar -->
				<div class="progress">
					<div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
				</div>
				
			</div>
			<div class="modal-footer bg-light border-top">
				<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
					<i class="bi bi-x-circle me-1"></i>Cancel
				</button>
				<button type="button" class="btn btn-primary activate" id="btn_import">
					<i class="bi bi-download me-1"></i>Import Database
				</button>
			</div>
		</div>
	</div>
</div>

</div>
