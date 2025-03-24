	<div class="table-responsive">
		<table class="table table-bordered" id="sortTable">

			<thead>
				<tr>
					<!--<th data-name="id" data-editable='false'>ID</th>-->
					<th data-name="name">Name</th>
					<th data-name="layers">Layers</th>
					<th data-name="store_id">Store</th>
					<th data-editable='false' data-action='true'>Actions</th>
				</tr>
			</thead>

			<tbody> <?php while($row = pg_fetch_object($rows)) {
				$row_grps = $grp_obj->getByKV('layer', $row->id);
				?>
				<tr data-id="<?=$row->id?>"
				    data-public="<?=$row->public=='t' ? 'yes' : 'no'?>"
					data-customized="<?=$row->customized=='t' ? 'yes' : 'no'?>"
				    data-cached="<?=$row->cached=='t' ? 'yes' : 'no'?>"
					data-proxyfied="<?=$row->proxyfied=='t' ? 'yes' : 'no'?>"
					data-exposed="<?=$row->exposed=='t' ? 'yes' : 'no'?>"
					data-show_dt="<?=$row->show_dt=='t' ? 'yes' : 'no'?>"
					data-group_id="<?=implode(',', array_keys($row_grps))?>"
					align="left">
					<!--<td><?=$row->id?></td>-->
					<td data-order="<?=$row->name?>">
						<a href="../layers/<?=$row->id?>/index.php" target="_blank" style="background-color: #C8D5E3!important;"><?=$row->name?></a>
					</td>
					<td><?=str_replace(',', '<br>', $row->layers)?></td>
					<td data-value="<?=$row->store_id?>"><?=$stores[$row->store_id]?></td>
					<td>						
						<a class="info" title="Show info" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
						<?php if(($row->owner_id == $_SESSION[SESS_USR_KEY]->id) || ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID)){ ?>
						<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
						<?php if(is_dir(CACHE_DIR.'/layers/'.$row->id)){ ?>
						<a class="clear" title="Clear cache" data-toggle="tooltip"><i class="text-secondary bi bi-file-earmark-x"></i></a>
						<?php } ?>
						<a class="edit_preview" title="Edit Preview" data-toggle="tooltip"><i class="text-warning bi bi-easel"></i></a>
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
			   <strong>Note:</strong> Create Layers from QGIS Stores. <a href="https://quail.docs.acugis.com/en/latest/sections/layers/index.html" target="_blank"> Documentation</a>

			</div>
		</div>
</div>

<div id="info_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content" style="width:120%!important">
			<div class="modal-header">
				<h4 class="modal-title">Layer Information</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modal-body">
				<table id="layer_info_table" class="table table-striped table-bordered">
					<tr><td>L.tileLayer.wms URL</td><td id="td_qgis_url"></td></tr>
					<tr><td>BBox[min(x,y); max(x,y)]</td><td id="td_qgis_bbox"></td></tr>
					<tr id="tr_wms_url" data-wms-query="">
						<td>WMS URL</td>
						<td>
							<select id="sel_wms_url" class="wms_url">
								<option value="">Select One</option>
								<option value="image%2Fjpeg">JPEG</option>
								<option value="image%2Fpng">PNG</option>
								<option value="image%2Fpng%3B%20mode%3D1bit">PNG 1bit</option>
								<option value="image%2Fpng%3B%20mode%3D8bit">PNG 8bit</option>
								<option value="image%2Fpng%3B%20mode%3D16bit">PNG 16bit</option>
								<option value="image%2Fwebp">WEBP</option>
								<option value="application%2Fpdf">PDF</option>
							</select>
						</td>
					</tr>
					<tr id="tr_wfs_url" data-wfs-query="">
						<td>WFS URL</td>
						<td>
							<select id="sel_wfs_url" class="wfs_url">
								<option value="">Select One</option>
								<option value="gml2">GML2</option>
								<option value="text%2Fxml%3B%20subtype%3Dgml%2F2.1.2">GML2.1.2</option>
								<option value="gml3">GML3.1</option>
								<option value="text%2Fxml%3B%20subtype%3Dgml%2F3.1.1">GML3.1.1</option>
								<option value="application%2Fjson">GeoJSON</option>
								<option value="application%2Fvnd.geo%3Bjson">VND Geo+JSON</option>
								<option value="application%2Fgeo%3Bjson">Geo+JSON</option>
								<option value="application%2Fgeo%20json">Geo JSON</option>
								
							</select>
						</td>
					</tr>
					
					<?php if(WITH_MAPPROXY) { ?>
					<tr><td>MapProxy URL</td><td id="td_mapproxy_url"></td></tr>
					<?php } ?>
					<tr><td>GetCapabilities URL</td><td><a target="_blank" href="#" id="td_capabilities_url"></a></td></tr>
				</table>
			</div>
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
						<input type="text" class="form-control" name="name" id="name" required/>
						
						<label for="store_id" class="form-label">Store</label>
						<select class="form-control" name="store_id" id="store_id">
							<?php foreach($stores as $k => $v) { ?>
								<option value="<?=$k?>"><?=$v?></option>
							<?php } ?>
						</select>
						
						<label for="layers" class="form-label">Layers</label>
						<select class="form-select" name="layers[]" id="layers" multiple required>
						</select>
					</div>

					<div class="form-group">
						<input type="checkbox" name="public" id="public" value="t"/>
						<label for="public" class="form-label">Public</label>
						<input type="checkbox" name="cached" id="cached" value="t"/>
						<label for="cached" class="form-label">Cached</label>
						<?php if(WITH_MAPPROXY) { ?>
						<input type="checkbox" name="proxyfied" id="proxyfied" value="t"/>
						<label for="cached" class="form-label">MapProxy</label>
						<?php } ?>
						<input type="checkbox" name="customized" id="customized" value="t"/>
						<label for="customized" class="form-label">Customized</label>
					</div>
					
					<div class="form-group">
						<input type="checkbox" name="exposed" id="exposed" value="t" disabled/>
						<label for="exposed" class="form-label">Separate Layers</label>
					</div>

					<div class="form-group">
						<input type="checkbox" name="show_dt" id="show_dt" value="t"/>
						<label for="show_dt" class="form-label">Show data tables</label>
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
