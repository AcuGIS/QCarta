<?php

?>
	<div class="table-responsive">
		<table class="table table-bordered" id="sortTable">

			<thead>
				<tr>
					<!--<th data-name="id" data-editable='false'>ID</th>-->
					<th data-name="name">Name</th>
					<th data-editable='false' data-name="size">Size</th>
					<th data-name="public">Public</th>
					<th data-name="group_id" data-type="select">Access Group</th>
					<th data-editable='false' data-action='true'>Actions</th>
				</tr>
			</thead>

			<tbody> <?php while($row = pg_fetch_object($rows)) {
				$data_size = dir_size(DATA_DIR.'/stores/'.$row->id);
				$row_grps = $grp_obj->getByKV('store', $row->id);
			?>
				<tr data-id="<?=$row->id?>" align="left">
					<!--<td><?=$row->id?></td>-->
					<td><?=$row->name?></td>
					<td data-order="<?=$data_size?>"><?=human_size($data_size)?></td>
					<td><?=$row->public=='t' ? 'yes' : 'no'?></td>
					<td data-value="<?=implode(',', array_keys($row_grps))?>">
						<?=implode(',', array_values($row_grps))?>
					</td>
					<td>						
						<a class="info" title="Show Info" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
						<a class="edit_plotly_defaults" title="Edit Plotly Defaults" data-toggle="tooltip"><i class="text-primary bi bi-bar-chart-steps"></i></a>
						<?php if(($row->owner_id == $_SESSION[SESS_USR_KEY]->id) || ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID)){ ?>
						<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
						<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
					<?php } ?>
					</td>
				</tr> <?php } ?>
			</tbody>
		</table>
</div>

<div class="row">
    <div class="col-8"><p>&nbsp;</p>

			<div class="alert alert-success">
			   <strong>Note:</strong> Upload your .qgs files here. Or create from data files or PostGIS. <a href="https://QCarta.docs.acugis.com/en/latest/sections/qgisstore/index.html" target="_blank"> Documentation</a>

			</div>
		</div>
</div>

<div id="info_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content" style="width:750px">
			<div class="modal-header">
				<h4 class="modal-title">QGS Information</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body"><p>QGS information.</p></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div id="addnew_modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h4 class="modal-title mb-0">
					<i class="bi bi-plus-circle me-2"></i>Create New QGIS Store
				</h4>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body p-4" id="addnew_modal_body">
				<form id="qgs_form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="save"/>
					<input type="hidden" name="id" id="id" value="save"/>
					
					<!-- Basic Information Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-info-circle me-2"></i>Basic Information
							</h6>
						</div>
						<div class="col-12 mb-3">
							<label for="name" class="form-label fw-semibold">
								<i class="bi bi-tag me-1"></i>Store Name
							</label>
							<input type="text" class="form-control" name="name" id="name" required 
								   placeholder="Enter store name"/>
						</div>
					</div>

					<!-- Data Source Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-database me-2"></i>Data Source
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
									<input type="file" class="form-control" name="qgs_file" id="qgs_file" 
										   accept=".zip, .qgs, .geojson, .qgz" multiple/>
									<small class="form-text text-muted">Supported formats: ZIP, QGS, GeoJSON</small>
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

								<!-- PostGIS Option -->
								<div class="mb-3">
									<div class="form-check mb-2">
										<input type="radio" class="form-check-input" id="src_pg_radio" name="store_source" value="1"/>
										<label for="src_pg_radio" class="form-check-label fw-semibold">
											<i class="bi bi-database me-1"></i>Use PostGIS Connection
										</label>
									</div>
									<select class="form-select" name="pg_store_id[]" id="pg_store_id" multiple disabled>
										<?php $sel = 'selected';
										foreach($pg_stores as $k => $v){ ?>
											<option value="<?=$k?>" <?=$sel?>><?=$v?></option>
										<?php $sel = ''; } ?>
									</select>
									<small class="form-text text-muted">Select PostGIS stores to connect</small>
								</div>

								<?php if($with_qfield){ ?>
								<!-- QField Option -->
								<div class="mb-3">
									<div class="form-check mb-2">
										<input type="radio" class="form-check-input" id="src_qf_radio" name="store_source" value="1"/>
										<label for="src_qf_radio" class="form-check-label fw-semibold">
											<i class="bi bi-cloud me-1"></i>Use QField Project
										</label>
									</div>
									<select class="form-select" name="qf_name" id="qf_name" disabled>
										<?php $sel = 'selected';
										foreach($qf_rows as $row){ ?>
											<option value="<?=$row['name']?>" <?=$sel?>><?=$row['name']?></option>
										<?php $sel = ''; } ?>
									</select>
								</div>
								<?php } ?>
							</fieldset>
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
								<input type="checkbox" class="form-check-input" name="public" id="public" value="t"/>
								<label for="public" class="form-check-label fw-semibold">
									<i class="bi bi-globe me-1"></i>Public Access
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
							<small class="form-text text-muted">Select groups that can access this store</small>
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
				<button type="button" class="btn btn-primary activate" id="btn_upload">
					<i class="bi bi-upload me-1"></i>Upload Store
				</button>
			</div>
		</div>
	</div>
</div>

</div>
