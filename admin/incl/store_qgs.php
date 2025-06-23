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
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Create New Store</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body" id="addnew_modal_body">
				<form id="qgs_form" class="border shadow p-3 rounded"
							action=""
							method="post"
							enctype="multipart/form-data"
							style="width: 450px;">

					<input type="hidden" name="action" value="save"/>
					<input type="hidden" name="id" id="id" value="save"/>
					
					<div class="form-group">
						<label for="name" class="form-label">Name</label>
						<input type="text" class="form-control" name="name" id="name" value="" required/>
					</div>

					<fieldset class="border p-2">
    				<legend class="w-auto">Source</legend>
						
						<div class="input-group">
							<input type="radio" id="src_file_radio" name="store_source" value="1" checked/>&nbsp;<span class="input-group-text" title="File Upload"><i class="bi bi-file-up"></i>Upload File</span>
							<input type="file" class="form-control" name="qgs_file" id="qgs_file" value="" accept=".zip, .qgs, .geojson" required multiple/>
							
						</div><br>
						<div class="input-group">
							<input type="radio" id="src_url_radio" name="store_source" value="1"/>&nbsp;<span class="input-group-text" title="URL Upload"><i class="bi bi-link"></i>&nbsp;Load via URL</span>
							<input type="text" class="form-control" name="src_url" id="src_url" value="" disabled required/>
							
						</div><br>

						<div class="input-group">
							<input type="radio" id="src_pg_radio" name="store_source" value="1"/>&nbsp;<span class="input-group-text"  title="PostGIS"><i class="bi bi-database-up"></i>&nbsp;Use PostGIS Connection</span>							<select class="form-control" name="pg_store_id[]" id="pg_store_id" disabled multiple>
								<?php $sel = 'selected';
								foreach($pg_stores as $k => $v){ ?>
									<option value="<?=$k?>" <?=$sel?>><?=$v?></option>
								<?php $sel = ''; } ?>
							</select>
							
						</div>
						<?php if($with_qfield){ ?>
						<div class="input-group">
							<input type="radio" class="form-check-input" id="src_qf_radio" name="store_source" value="1"/>
							<select class="form-control" name="qf_name" id="qf_name" disabled>
								<?php $sel = 'selected';
								foreach($qf_rows as $row){ ?>
									<option value="<?=$row['name']?>" <?=$sel?>><?=$row['name']?></option>
								<?php $sel = ''; } ?>
							</select>
							<span class="input-group-text"  title="QField"><i class="bi bi-cloud"></i></span>
						</div>
					<?php } ?>
					</fieldset>
					
					<div class="form-group">
						<input type="checkbox" name="public" id="public" value="t"/>
						<label for="public" class="form-label">Public</label>
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
				
				<div class="progress">
					<div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
				</div>
				
			</div>
			<div class="modal-footer">
				<button type="button" class="activate btn btn-secondary" id="btn_upload" data-dismiss="modal">Upload</button>
			</div>
		</div>
	</div>
</div>

</div>
