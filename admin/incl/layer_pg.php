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
			   <strong>Note:</strong> Create Layers from PostGIS Stores. <a href="https://quail.docs.acugis.com/en/latest/sections/layers/index.html" target="_blank"> Documentation</a>
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
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Create Layer</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body" id="addnew_modal_body">
				<form id="layer_form" class="border shadow p-3 rounded"
							action=""
							method="post"
							enctype="multipart/form-data"
							style="width: 450px;">

					<input type="hidden" name="action" value="save"/>
					<input type="hidden" name="id" id="id" value="0"/>
					
					<div class="form-group">
						<label for="name" class="form-label">Name</label>
						<input type="text" class="form-control" name="name" id="name" value="" required/>
					</div>
					
					<div class="form-group">
						<input type="checkbox" name="public" id="public" value="t"/>
						<label for="public" class="form-label">Public</label>
					</div>
					
					<div class="form-group">
						<label for="store_id" class="form-label">Store</label>
						<select class="form-control" name="store_id" id="store_id">
							<?php foreach($stores as $k => $v) { ?>
								<option value="<?=$k?>"><?=$v?></option>
							<?php } ?>
						</select>
						
						<label for="tbl" class="form-label">Table</label>
						<select class="form-control" name="tbl" id="tbl">
						</select>
						
						<label for="geom" class="form-label">GEOM</label>
						<select class="form-control" name="geom" id="geom">
						</select>
					</div>
					
					<div class="form-group">
						<div class="input-group">
							<select name="group_id[]" id="group_id" multiple required>
								<?php $sel = 'selected';
								foreach($groups as $k => $v){ ?>
									<option value="<?=$k?>" <?=$sel?>><?=$v?></option>
								<?php $sel = ''; } ?>
							</select>
							<span class="input-group-text"><i class="bi bi-shield-lock">Access Groups</i></span>
						</div>
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
