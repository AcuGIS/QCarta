<?php require_once __DIR__ . '/qcarta_tile_project_key.php'; ?>
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
				$topic_ids_row = $obj->get_assigned_category_ids('topic', $row->id);
				$gemet_ids_row = $obj->get_assigned_category_ids('gemet', $row->id);
				$tile_cache_key = qcarta_tile_cache_project_key_for_layer_id((int) $row->id);
				?>
				<tr data-id="<?=$row->id?>"
				    data-tile_cache_key="<?=htmlspecialchars($tile_cache_key, ENT_QUOTES, 'UTF-8')?>"
				    data-description="<?=$row->description?>"
				    data-topic_id="<?=implode(',', $topic_ids_row)?>"
				    data-gemet_id="<?=implode(',', $gemet_ids_row)?>"
				    data-public="<?=$row->public=='t' ? 'yes' : 'no'?>"
					data-customized="<?=$row->customized=='t' ? 'yes' : 'no'?>"
				    data-cached="<?=$row->cached=='t' ? 'yes' : 'no'?>"
					data-layers="<?=htmlspecialchars($row->layers, ENT_QUOTES, 'UTF-8')?>"
					data-proxyfied="<?=$row->proxyfied=='t' ? 'yes' : 'no'?>"
					data-exposed="<?=$row->exposed=='t' ? 'yes' : 'no'?>"
					data-show_charts="<?=$row->show_charts=='t' ? 'yes' : 'no'?>"
					data-show_dt="<?=$row->show_dt=='t' ? 'yes' : 'no'?>"
					data-show_query="<?=$row->show_query=='t' ? 'yes' : 'no'?>"
					data-show_fi_edit="<?=$row->show_fi_edit=='t' ? 'yes' : 'no'?>"
					data-print_layout="<?=$row->print_layout?>"
					data-basemap_id="<?=$row->basemap_id ?? ''?>"
					data-group_id="<?=implode(',', array_keys($row_grps))?>"
					align="left">
					<!--<td><?=$row->id?></td>-->
					<td data-order="<?=$row->name?>">
						<a href="../layers/<?=$row->id?>/layer.php" target="_blank" style="background-color: #C8D5E3!important;"><?=$row->name?></a>
					</td>
					<td><?=str_replace(',', '<br>', $row->layers)?></td>
					<td data-value="<?=$row->store_id?>"><?=$stores[$row->store_id]?></td>
					<td>						
						<a class="info me-2" title="Show info" data-toggle="tooltip"><i class="text-info bi bi-info-circle fs-5"></i></a>
							<?php if(($row->owner_id == $_SESSION[SESS_USR_KEY]->id) || ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID)){ ?>
							<a class="edit me-2" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square fs-5"></i></a>
							<!-- <a class="edit_layer_queries" title="Edit Queries" data-toggle="tooltip"><i class="text-success bi bi-filetype-sql"></i></a> -->
							<a class="edit_layer_reports me-2" title="Edit Reports" data-toggle="tooltip"><i class="text-success bi bi-table fs-5"></i></a>
							<a class="edit_property_filters me-2" title="Edit Filters" data-toggle="tooltip"><i class="text-success bi bi-funnel fs-5"></i></a>
							<a class="edit_layer_metadata me-2" title="Edit Metadata" data-toggle="tooltip"><i class="text-primary bi bi-bar-chart-steps fs-5"></i></a>
							<?php if($row->cached == 't'){ ?>
							<a href="#" class="warm_tile_cache me-2" title="Warm tile cache for this map only" data-toggle="tooltip"><i class="text-primary bi bi-speedometer2 fs-5"></i></a>
							<a href="#" class="clear_project_cache me-2" title="Clear Cache (This Project)" data-toggle="tooltip"><i class="text-danger bi bi-trash fs-5"></i></a>
						<?php } ?>
							<a class="edit_preview me-2" title="Edit Preview" data-toggle="tooltip"><i class="text-warning bi bi-easel fs-5"></i></a>
							<a class="delete me-2" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square fs-5"></i></a>
						<?php } ?>
					</td>
				</tr> <?php } ?>
			</tbody>
		</table>
</div>

<div class="row">
    <div class="col-8"><p>&nbsp;</p>

			<div class="alert alert-success">
			   <strong>Note:</strong> Create Layers from QGIS Stores. <a href="https://QCarta.docs.acugis.com/en/latest/sections/layers/index.html" target="_blank"> Documentation</a>

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
					
					<tr><td>QCarta Tiles URL</td><td id="td_mapproxy_url"></td></tr>
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
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h4 class="modal-title mb-0">
					<i class="bi bi-plus-circle me-2"></i>Create New Layer
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

					<!-- Store and Layers Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-database me-2"></i>Store Configuration
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="store_id" class="form-label fw-semibold">
								<i class="bi bi-server me-1"></i>QGIS Store
							</label>
							<select class="form-select" name="store_id" id="store_id">
								<?php foreach($stores as $k => $v) { ?>
									<option value="<?=$k?>"><?=$v?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-6 mb-3">
							<label for="layers" class="form-label fw-semibold">
								<i class="bi bi-layers me-1"></i>Available Layers
							</label>
							<select class="form-select" name="layers[]" id="layers" multiple required>
							</select>
							<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple layers</small>
						</div>
					</div>

					<!-- Thumbnail Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-image me-2"></i>Thumbnail Settings
							</h6>
						</div>
						<div class="col-md-8 mb-3">
							<label for="image" class="form-label fw-semibold">
								<i class="bi bi-upload me-1"></i>Upload Thumbnail
							</label>
							<input type="file" class="form-control" name="image" id="image" 
								   accept=".jpeg,.jpg,.png,.webp"/>
							<small class="form-text text-muted">Supported formats: JPEG, PNG, WebP</small>
						</div>
						<div class="col-md-4 mb-3">
							<div class="form-check mt-4">
								<input type="checkbox" class="form-check-input" name="auto_thumbnail" id="auto_thumbnail" value="t"/>
								<label for="auto_thumbnail" class="form-check-label fw-semibold">
									<i class="bi bi-magic me-1"></i>Auto Generate
								</label>
							</div>
						</div>
					</div>

					<!-- Print Layout Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-printer me-2"></i>Print Configuration
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="print_layout" class="form-label fw-semibold">
								<i class="bi bi-file-earmark-text me-1"></i>Print Layout
							</label>
							<select class="form-select" name="print_layout" id="print_layout">
							</select>
						</div>
					</div>

					<!-- Layer Options Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-gear me-2"></i>Layer Options
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="public" id="public" value="t"/>
								<label for="public" class="form-check-label fw-semibold">
									<i class="bi bi-globe me-1"></i>Public Access
								</label>
							</div>
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="cached" id="cached" value="t"/>
								<label for="cached" class="form-check-label fw-semibold">
									<i class="bi bi-hdd me-1"></i>Enable Tile Cache (qcarta-tiles)
								</label>
							</div>
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="customized" id="customized" value="t"/>
								<label for="customized" class="form-check-label fw-semibold" title="When checked, saving only updates env.php; index.php, layer.php, analysis.php, and table.php are left as-is. When unchecked, each save replaces those files from the admin template (copied or hand-edited map PHP is overwritten).">
									<i class="bi bi-file-earmark-code me-1"></i>Keep custom map PHP (no template overwrite)
								</label>
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check mb-3">
								<input type="checkbox" class="form-check-input" name="exposed" id="exposed" value="t" disabled/>
								<label for="exposed" class="form-check-label fw-semibold text-muted">
									<i class="bi bi-layers-fill me-1"></i>Separate Layers
								</label>
							</div>
							<label for="basemap_id" class="form-label fw-semibold">
								<i class="bi bi-map me-1"></i>Default Basemap
							</label>
							<select class="form-select" name="basemap_id" id="basemap_id">
								<?php foreach($basemaps as $k => $v) { ?>
									<option value="<?=$k?>"><?=$v?></option>
								<?php } ?>
							</select>
						</div>
					</div>

					<!-- Feature Options Section -->
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-ui-checks me-2"></i>Feature Options
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="show_charts" id="show_charts" value="t"/>
								<label for="show_charts" class="form-check-label fw-semibold">
									<i class="bi bi-bar-chart me-1"></i>Show Charts Tab
								</label>
							</div>
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="show_dt" id="show_dt" value="t"/>
								<label for="show_dt" class="form-check-label fw-semibold">
									<i class="bi bi-table me-1"></i>Show Data Tables
								</label>
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="show_query" id="show_query" value="t"/>
								<label for="show_query" class="form-check-label fw-semibold">
									<i class="bi bi-search me-1"></i>Show Query Tab
								</label>
							</div>
							<div class="form-check">
								<input type="checkbox" class="form-check-input" name="show_fi_edit" id="show_fi_edit" value="t"/>
								<label for="show_fi_edit" class="form-check-label fw-semibold">
									<i class="bi bi-pencil-square me-1"></i>Show Edit Button
								</label>
							</div>
						</div>
					</div>
					
					<!-- Topics & GEMET keywords -->
					<?php require __DIR__.'/taxonomy_multiselect.php'; ?>

					<!-- Access Groups Section -->
					<div class="row mb-3">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-shield-lock me-2"></i>Access Control
							</h6>
						</div>
						<div class="col-12 mb-3">
							<label for="group_id" class="form-label fw-semibold">
								<i class="bi bi-people me-1"></i>Access Groups
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

<div id="addproject_modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h4 class="modal-title mb-0">
					<i class="bi bi-plus-circle me-2"></i>Create layer from project
				</h4>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body p-4" id="addproject_modal_body">
				<form id="qgs_form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="save"/>
					<input type="hidden" name="autofill" value="1"/>
					<input type="hidden" name="id" id="id" value="0"/>

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
									<small class="form-text text-muted">Supported formats: ZIP, QGS, QGZ, GeoJSON</small>
								</div>

								<!-- URL Option -->
								<div class="mb-3">
									<div class="form-check mb-2">
										<input type="radio" class="form-check-input" id="src_url_radio" name="store_source" value="2"/>
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

<div id="tile_seed_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="tile_seed_title" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<div>
					<h4 class="modal-title mb-0" id="tile_seed_title">
						<i class="bi bi-speedometer2 me-2"></i>Warm tile cache
					</h4>
					<div class="small opacity-75 mt-1">Pre-seed <code>/api/tiles/</code> for <strong>one map</strong> (this row). Pick zoom levels, then Start.</div>
				</div>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-4">
				<h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-map me-2"></i>This map only</h6>
				<div class="mb-4">
					<input type="hidden" id="tile_seed_layer_id" value="" />
					<p class="fs-5 fw-semibold text-dark mb-1" id="tile_seed_map_display"></p>
					<p class="text-muted small mb-0">Seeding applies only to the QGIS project and layer list for this published map—not other maps.</p>
				</div>

				<h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-grid-3x3-gap me-2"></i>Zoom levels to seed</h6>
				<p class="text-muted small mb-2">Tick each zoom level (0 = world overview, higher = more detail, more tiles). Use presets or pick manually.</p>
				<div class="btn-group btn-group-sm flex-wrap mb-2" role="group" aria-label="Zoom presets">
					<button type="button" class="btn btn-outline-secondary" id="tile_seed_preset_overview" title="Zoom 0–6">Overview 0–6</button>
					<button type="button" class="btn btn-outline-secondary" id="tile_seed_preset_standard" title="Zoom 0–10">Standard 0–10</button>
					<button type="button" class="btn btn-outline-secondary" id="tile_seed_preset_detail" title="Zoom 0–14">Detail 0–14</button>
					<button type="button" class="btn btn-outline-secondary" id="tile_seed_preset_clear">Clear all</button>
					<button type="button" class="btn btn-outline-secondary" id="tile_seed_preset_all">Select all</button>
				</div>
				<div class="border rounded p-3 bg-light mb-4" id="tile_seed_zoom_wrap" style="max-height: 200px; overflow-y: auto;">
					<div class="row row-cols-2 row-cols-sm-4 row-cols-md-6 row-cols-lg-8 g-2 small">
						<?php for ($tz = 0; $tz <= 22; $tz++): ?>
						<div class="col">
							<div class="form-check">
								<input class="form-check-input tile-seed-z" type="checkbox" value="<?= (int) $tz ?>" id="tile_seed_z_<?= (int) $tz ?>"<?= $tz <= 8 ? ' checked' : '' ?>/>
								<label class="form-check-label" for="tile_seed_z_<?= (int) $tz ?>">Zoom <?= (int) $tz ?></label>
							</div>
						</div>
						<?php endfor; ?>
					</div>
				</div>

				<h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-calculator me-2"></i>Estimate</h6>
				<p class="mb-4 small" id="tile_seed_estimate_wrap"><span id="tile_seed_estimate" class="text-muted">Open “Warm cache” from a map row to see the tile count estimate.</span></p>

				<h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-graph-up me-2"></i>Progress</h6>
				<div class="progress mb-2" style="height: 1.75rem;">
					<div id="tile_seed_progress" class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
				</div>
				<p id="tile_seed_status" class="small text-muted mb-0">Idle. Press Start to fetch tiles (runs in this browser; keep the tab open).</p>
			</div>
			<div class="modal-footer bg-light border-top">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="tile_seed_close">Close</button>
				<button type="button" class="btn btn-danger" id="tile_seed_stop" disabled><i class="bi bi-stop-fill me-1"></i>Stop</button>
				<button type="button" class="btn btn-primary" id="tile_seed_start"><i class="bi bi-play-fill me-1"></i>Start seeding</button>
			</div>
		</div>
	</div>
</div>
