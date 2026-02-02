	<div class="table-responsive">
		<table class="table table-bordered" id="sortTable">

			<thead>
				<tr>
					<!--<th data-name="id" data-editable='false'>ID</th>-->
					<th data-name="name">Name</th>
					<th data-name="public">Public</th>
					<th data-name="store_id">Store</th>
					<th data-name="tbl">Table</th>
					<th data-name="geom">Geom</th>
					<th data-name="group_id" data-type="select">Access Group</th>
					<th data-editable='false' data-action='true'>Actions</th>
				</tr>
			</thead>

			<tbody> <?php while($row = pg_fetch_object($rows)) {
				$row_grps = $grp_obj->getByKV('layer', $row->id);
				?>
				<tr data-id="<?=$row->id?>" align="left">
					<!--<td><?=$row->id?></td>-->
					<td><?=$row->name?></td>
					<td><?=$row->public=='t' ? 'yes' : 'no'?></td>
					<td data-value="<?=$row->store_id?>"><?=$stores[$row->store_id]?></td>
					<td><?=$row->tbl?></td>
					<td><?=$row->geom?></td>
					<td data-value="<?=implode(',', array_keys($row_grps))?>">
						<?=implode(',', array_values($row_grps))?>
					</td>
					<td>						
						<a class="info" title="Show info" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
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
			   <strong>Note:</strong> Create Layers from PostGIS Stores. <a href="https://QCarta.docs.acugis.com/en/latest/sections/layers/index.html" target="_blank"> Documentation</a>
			</div>
		</div>
</div>

<div id="info_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content" style="width:120%">
			<div class="modal-header">
				<h4 class="modal-title">Layer Information</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body"><p>Layer information.</p></div>
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
					<i class="bi bi-plus-circle me-2"></i>Create New PostGIS Layer
				</h4>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body p-4" id="addnew_modal_body">
				<form id="layer_form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="save"/>
					<input type="hidden" name="id" id="id" value="0"/>
					
					<!-- Basic Information Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-info-circle me-2"></i>Basic Information
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="name" class="form-label fw-semibold">
								<i class="bi bi-tag me-1"></i>Layer Name
							</label>
							<input type="text" class="form-control" name="name" id="name" required 
								   placeholder="Enter layer name"/>
						</div>
						<div class="col-md-6 mb-3">
							<label for="description" class="form-label fw-semibold">
								<i class="bi bi-file-text me-1"></i>Description
							</label>
							<input type="text" class="form-control" name="description" id="description" required 
								   placeholder="Enter layer description"/>
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
					
					<!-- Database Configuration Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-database me-2"></i>Database Configuration
							</h6>
						</div>
						<div class="col-md-4 mb-3">
							<label for="store_id" class="form-label fw-semibold">
								<i class="bi bi-server me-1"></i>PostGIS Store
							</label>
							<select class="form-select" name="store_id" id="store_id">
								<?php foreach($stores as $k => $v) { ?>
									<option value="<?=$k?>"><?=$v?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-4 mb-3">
							<label for="tbl" class="form-label fw-semibold">
								<i class="bi bi-table me-1"></i>Database Table
							</label>
							<select class="form-select" name="tbl" id="tbl">
							</select>
						</div>
						<div class="col-md-4 mb-3">
							<label for="geom" class="form-label fw-semibold">
								<i class="bi bi-geo-alt me-1"></i>Geometry Column
							</label>
							<select class="form-select" name="geom" id="geom">
							</select>
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
							<small class="form-text text-muted">Select groups that can access this layer</small>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer bg-light border-top">
				<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
					<i class="bi bi-x-circle me-1"></i>Cancel
				</button>
				<button type="button" class="btn btn-primary activate" id="btn_create">
					<i class="bi bi-check-circle me-1"></i>Create Layer
				</button>
			</div>
		</div>
	</div>
</div>

</div>
