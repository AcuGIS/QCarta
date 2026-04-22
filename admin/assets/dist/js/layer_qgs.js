var tbl_action = 'qgs_layer';

function parseDataIdList(tr, attrName) {
	var raw = tr.attr(attrName) || '';
	if (!raw) {
		return [];
	}
	return raw.split(',').map(function (x) { return String(x).trim(); }).filter(Boolean);
}

function load_select(id, name, arr){
	var obj = $('#' + id);
	if(arr.length === 0){
		obj.replaceWith(`<input type="text" class="form-control" name="` + name +`" id="` + id + `" value=""/>`);
		return;
	}
	
	var opts = '';
	var first = 'selected';
	$.each(arr, function(x){
		opts += '<option value="' + arr[x] + '" ' + first + '>' + arr[x] + '</option>' + "\n";
		first = '';
	});
	
	// check if
	idx = 0;
	if(obj.prop('tagName').toLowerCase() == 'input'){
		var idx = $.inArray(obj.val(), arr);
		if(idx == -1){
			idx = 0;
		}
	}
	
	//change input to select
	obj.replaceWith(`<select class="form-select" id="`+ id + `" name="`+ name +`" multiple>` + opts + `</select>`);
	// selecting first element
	if(edit_row != null){
		$('#' + id).val(edit_row[id]);
	}else{
		$('#' + id).val(arr[idx]);
	}
	$('#' + id).trigger('change');
}

async function mark_data_layers(store_id, arr){
  const xmlData = await fetch(`../stores/${store_id}/wfs.ph?REQUEST=GetCapabilities`, { credentials: 'same-origin' }).then(r => r.text());
  const xmlDoc = new window.DOMParser().parseFromString(xmlData, "text/xml");
  
  // get all feature types from store GetCapabilities
  const featTypes = xmlDoc.getElementsByTagName('FeatureType');
  $.each(featTypes, function(x){
    const name = featTypes[x].getElementsByTagName('Name')[0].textContent;
    const bb = featTypes[x].getElementsByTagName('ows:WGS84BoundingBox')[0];
    const lc = bb.getElementsByTagName('ows:LowerCorner')[0];
    const uc = bb.getElementsByTagName('ows:UpperCorner')[0];
    // if feature bbox is [[0,0][0,0]] it has no geometry ?
    if((lc.textContent == uc.textContent) && (lc.textContent == '0 0')){
      $('select option:contains("' + name + '")').html(name + ' (data only)');
    }
  });
}

function btn_upload_post(data){
	$.ajax({
		type: "POST",
		url: 'action/qgs.php',
		data: data,
		processData: false,
		contentType: false,
		dataType: "json",
		success: function(response){
			if(response.success){
				$('#btn_upload').toggle();
				$('#addproject_modal').modal('hide');
				
				if(data.get('id') > 0){	// if edit
					location.reload();
				}else if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
					location.reload();
				}else{
				  $('#store_id').append(new Option(response.name, response.id));
					$('#store_id').val(response.id);  // select newly created store
					$('.add-modal').click();
				}
			}else{
				alert("Upload failed." + response.message);
			}
		}
	});	
}

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();	
$('#layer_form').submit(false);
$('#qgs_form').submit(false);
$("div .progress").hide();

  $(document).on("click", ".addproject-modal", function() {
			edit_row = null;
			$('#addproject_modal').modal('show');
	});

	// Edit row on edit button click
	$(document).on("click", ".edit", function() {
		let tr = $(this).parents("tr");
		let tds = tr.find('td');
		
		$('#btn_create').html('Update');
		$('#addnew_modal').modal('show');

		$('#id').val(tr.attr('data-id'));
		$('#name').val(tds[0].getAttribute('data-order'));
		$('#description').val(tr.attr('data-description'));
		$('#public').prop('checked', (tr.attr('data-public') == 'yes'));
		$('#cached').prop('checked', (tr.attr('data-cached') == 'yes'));
		$('#proxyfied').prop('checked', (tr.attr('data-proxyfied') == 'yes'));

		$('#exposed').prop('disabled', !$('#proxyfied').prop('checked'));
		
		$('#exposed').prop('checked', (tr.attr('data-exposed') == 'yes'));
		$('#show_charts').prop('checked', (tr.attr('data-show_charts') == 'yes'));
		$('#show_dt').prop('checked', (tr.attr('data-show_dt') == 'yes'));
		$('#show_query').prop('checked', (tr.attr('data-show_query') == 'yes'));
		$('#show_fi_edit').prop('checked', (tr.attr('data-show_fi_edit') == 'yes'));
		$('#customized').prop('checked', (tr.attr('data-customized') == 'yes'));
		$('#store_id').val(tds[2].getAttribute('data-value')).trigger('change');
		$('#group_id').val(tr.attr('data-group_id').split(','));
		$('#basemap_id').val(tr.attr('data-basemap_id'));
		if ($('#topic_id').length) {
			$('#topic_id').val(parseDataIdList(tr, 'data-topic_id'));
			$('#gemet_id').val(parseDataIdList(tr, 'data-gemet_id'));
		}
		edit_row = {'layers': tds[1].innerHTML.split('<br>'), 'print_layout': tr.attr('data-print_layout')};
	});
	
	$(document).on("click", ".edit_preview", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_preview.php?id=' + id;
	});

	$(document).on("click", ".edit_layer_queries", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_layer_queries.php?id=' + id;
	});

	$(document).on("click", ".edit_layer_reports", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_layer_reports.php?id=' + id;
	});

	$(document).on("click", ".edit_property_filters", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_property_filters.php?id=' + id;
	});

	$(document).on("click", ".edit_layer_metadata", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_layer_metadata.php?id=' + id;
	});
	
	// Delete row on delete button click
	$(document).on("click", ".delete", function() {
			var obj = $(this);
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': 'delete', 'id': id}
			
			if(confirm('Layer file will be deleted ?')){
				$.ajax({
					type: "POST",
					url: 'action/' + tbl_action + '.php',
					data: data,
					dataType:"json",
					success: function(response){
						if(response.success) { // means, new record is added
							sortTable.row(obj.parents("tr")).remove().draw();
						}

						alert(response.message);
					}
				});
			}
	});

	// Show layer info
	$(document).on("click", ".info", function() {
			var obj = $(this);	// <a> with the icon
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': 'info', 'id': id}
											
			$.ajax({
				 type: "POST",
				 url: 'action/' + tbl_action + '.php',
				 data: data,
				 dataType:"json",
				 success: function(response){
					 if(response.success) {
 						$('#td_qgis_url').text(response.qgis_url);
						if(typeof response.mapproxy_url != 'undefined'){
							$('#td_mapproxy_url').text(response.mapproxy_url);
						}
						$('#td_qgis_bbox').html(response.bbox);
 						$('#tr_wms_url').attr('data-wms-query', response.wms_query);
            $('#tr_wfs_url').attr('data-wfs-query', response.wfs_query);
            
            $('#td_capabilities_url').attr('href', response.capabilities_query);
            $('#td_capabilities_url').text(response.capabilities_query);
            
 						$('#sel_wms_url').val('');
 						$('#info_modal').modal('show');
 					}else{
 						alert(response.message);
 					}
				 }
			 });
	});

	// Show layer info
	$(document).on("click", ".clear", function() {
			var obj = $(this);	// <a> with the icon
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': 'cache_clear', 'id': id}
											
			$.ajax({
				 type: "POST",
				 url: 'action/' + tbl_action + '.php',
				 data: data,
				 dataType:"json",
				 success: function(response){
					alert(response.message);
				 }
			 });
	});

	// Clear project cache (qcarta-tiles)
	$(document).on("click", ".clear_project_cache", function(e) {
		e.preventDefault();
		e.stopPropagation();
		var obj = $(this);
		var id = obj.parents("tr").attr('data-id');
		
		console.log('Clear project cache clicked for layer ID:', id);
		
		if(!id || id === '') {
			alert('Error: Could not determine layer ID');
			return;
		}
		
		if(!confirm("Clear cached tiles for this project?\n\nThis will remove all cached tiles for the QGIS project used by this layer.")) {
			return;
		}
		
		// Server resolves wms/<basename> from layers/{id}/env.php — admin "Name" (e.g. Bee_Map) is not the cache folder.
		console.log("Purging tile cache for layer id:", id);
		var tok = typeof QCARTA_CACHE_TOKEN !== "undefined" ? QCARTA_CACHE_TOKEN : "";
		fetch("/qcarta-cache/purge.php?layer_id=" + encodeURIComponent(id) + "&token=" + encodeURIComponent(tok))
		  .then(res => res.text())
		  .then(msg => alert(msg))
		  .catch(err => alert("Error: " + err));
	});

	$(document).on("change", ".wms_url", function() {
		let obj = $(this);
		let qgis_url = $('#td_qgis_url').text();
		
		let query  = $('#tr_wms_url').attr('data-wms-query');
		let format = obj.find('option:selected').val();
		
		let url = qgis_url + query + '&FORMAT=' + format;
		window.open(url, 'WMS');
		//console.log(url);
	});

	$(document).on("change", ".wfs_url", function() {
		let obj = $(this);
		let qgis_url = $('#td_qgis_url').text();
		
		let query  = $('#tr_wfs_url').attr('data-wfs-query');
		let format = obj.find('option:selected').val();
		
		let url = qgis_url + query + '&OUTPUTFORMAT=' + format;
		window.open(url, 'WFS');
		//console.log(url);
	});

	$(document).on("change", 'select[name="store_id"]', function() {
		let obj = $(this);
		var data = { 'id' : obj.find('option:selected').val(), 'action': 'layers' };
		
		$('#store_id').prop('disabled', true);
		
		$.ajax({
			type: "POST",
			url: 'action/qgs.php',
			data: data,
			dataType:"json",
			success: function(response){
				 if(response.success) {
					  load_select('layers', 'layers[]', response.layers);
						mark_data_layers(data.id, response.layers);
						load_select('print_layout', 'print_layout', response.print_layouts);
					 	$('#store_id').prop('disabled', false);
				 }else{
					 alert('Error: Failed to list layers. ' + response.message);
				 }
			},
			fail: function(){	alert('Error: POST failure');	}
		});
	});
	
	$(document).on("change", '#proxyfied', function() {
		let obj = $(this);
		if(obj.prop('checked')){
			$('#exposed').prop('disabled', false);
		}else{
			$('#exposed').prop('disabled', true);
			$('#exposed').prop('checked', false);
		}
	});
	
	$(document).on("change", '#auto_thumbnail', function() {
		let obj = $(this);
		$('#image').prop('disabled', obj.prop('checked'));
	});
	
	$(document).on("click", "#btn_create", function() {
			var obj = $(this);
			var input = $('#layer_form').find('input[type="text"], input[type="checkbox"], select');
			var empty = false;
			
			obj.toggle();
			
			input.each(function() {
				if (!$(this).prop('disabled') && $(this).prop('required') && !$(this).val()) {
					$(this).addClass("error");
					empty = true;
				} else {
					$(this).removeClass("error");
				}
			});
 
			if(/^[a-zA-Z0-9_]*$/.test($('#name').val()) == false) {
				$('#name').addClass("error");
				empty = true;
			}

			if(empty){
				$('#layer_form').find(".error").first().focus();
					obj.toggle();
			}else{
				let data = new FormData($('#layer_form')[0]);
				
				$.ajax({
					type: "POST",
					url: 'action/' + tbl_action + '.php',
					data: data,
					processData: false,
					contentType: false,
					dataType: "json",
					success: function(response){
						if(response.success){
							$('#btn_create').toggle();
							$('#addnew_modal').modal('hide');
							
							if(data.get('id') > 0){	// if edit
								location.reload();
							}else if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
								location.reload();
							}else{
								const name_a = '<a href="../layers/' + response.id + '/index.php">' + data.get('name') + '</a>';
								const is_public = data.get('public') == 't' ? 'yes' : 'no';
								const is_cached = data.get('cached') == 't' ? 'yes' : 'no';
								const is_proxyfied = data.get('proxyfied') == 't' ? 'yes' : 'no';
								const is_exposed = data.get('exposed') == 't' ? 'yes' : 'no';
								const is_customized = data.get('customized') == 't' ? 'yes' : 'no';
								const is_show_charts = data.get('show_charts') == 't' ? 'yes' : 'no';
								const is_show_dt = data.get('show_dt') == 't' ? 'yes' : 'no';
								const is_show_query = data.get('show_query') == 't' ? 'yes' : 'no';
								const is_editable = data.get('show_fi_edit') == 't' ? 'yes' : 'no';
							
								const layersJoined = data.getAll('layers[]').join(',');
								const cacheActions =
									is_cached === 'yes'
										? `<a href="#" class="warm_tile_cache me-2" title="Warm tile cache for this map only" data-toggle="tooltip"><i class="text-primary bi bi-speedometer2 fs-5"></i></a>
<a href="#" class="clear_project_cache me-2" title="Clear Cache (This Project)" data-toggle="tooltip"><i class="text-danger bi bi-trash fs-5"></i></a>`
										: '';
								const tds = [
									{ "display": name_a, "@data-order": data.get('name') },
									layersJoined,
									$('#store_id').find(':selected').text(),
									`<a class="info me-2" title="Show info" data-toggle="tooltip"><i class="text-info bi bi-info-circle fs-5"></i></a>
									<a class="edit me-2" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square fs-5"></i></a>
									<a class="edit_layer_reports me-2" title="Edit Reports" data-toggle="tooltip"><i class="text-success bi bi-table fs-5"></i></a>
									<a class="edit_property_filters me-2" title="Edit Filters" data-toggle="tooltip"><i class="text-success bi bi-funnel fs-5"></i></a>
									<a class="edit_layer_metadata me-2" title="Edit Metadata" data-toggle="tooltip"><i class="text-primary bi bi-bar-chart-steps fs-5"></i></a>
									${cacheActions}
									<a class="edit_preview me-2" title="Edit Preview" data-toggle="tooltip"><i class="text-warning bi bi-easel fs-5"></i></a>
									<a class="delete me-2" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square fs-5"></i></a>`
								];

								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-id', response.id);
								dtrow.attr('data-tile_cache_key', '');
								dtrow.attr('data-description', response.description);
								dtrow.attr('data-public', is_public);
								dtrow.attr('data-customized', is_customized);
								dtrow.attr('data-cached', is_cached);
								dtrow.attr('data-layers', layersJoined);
								dtrow.attr('data-proxyfied', is_proxyfied);
								dtrow.attr('data-exposed', is_exposed);
								dtrow.attr('data-show_charts', is_show_charts);
								dtrow.attr('data-show_dt', is_show_dt);
								dtrow.attr('data-show_query', is_show_query);
								dtrow.attr('data-show_fi_edit', is_editable);
								dtrow.attr('data-group_id', $('#group_id').val().join(','));
								dtrow.attr('data-print_layout', $('#print_layout').val());
								if ($('#topic_id').length) {
									dtrow.attr('data-topic_id', ($('#topic_id').val() || []).join(','));
									dtrow.attr('data-gemet_id', ($('#gemet_id').val() || []).join(','));
								}
								
								dtrow.find('td:eq(0)').attr('data-order', $('#name').val());
								dtrow.find('td:eq(2)').attr('data-value', $('#store_id').val());
							}
						}else{
							alert("Create failed:" + response.message);
						}
					}
				});
			}
	});
	
	$(document).on("click", "#btn_upload", function() {
			var obj = $(this);
			var input = $('#qgs_form').find('input[type="text"]');
			var empty = false;
			
			obj.toggle();
			
			input.each(function() {
				if (!$(this).prop('disabled') && $(this).prop('required') && !$(this).val()) {
					$(this).addClass("error");
					empty = true;
				} else {
					$(this).removeClass("error");
				}
			});

			if(empty){
				$('#qgs_form').find(".error").first().focus();
				obj.toggle();
			}else{
				const fileInput = document.getElementById('qgs_file');

				let data = new FormData($('#qgs_form')[0]);
				data.delete('qgs_file');
				
				if(data.get('src_url')){
					uploadURL('src_url', 'action/upload.php', btn_upload_post, data);
				}else{
					for (var i = 0; i < fileInput.files.length; i++) {
						data.append('source[]', fileInput.files[i].name);
					}
					uploadFile('qgs_file', 'action/upload.php', btn_upload_post, data);
				}
			}
	});
	
	$(document).on("click", "#src_file_radio", function() {
		$('#qgs_file').prop('disabled', false);
		$('#qgs_file').prop('required', true);
		
		//$('#pg_store_id').prop('disabled', true);
		//$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#qf_name').prop('disabled', true);
		$('#qf_name').prop('required', false);
	});
	
	$(document).on("click", "#src_url_radio", function() {
		$('#qgs_file').prop('disabled', true);
		$('#qgs_file').prop('required', false);
		
		//$('#pg_store_id').prop('disabled', true);
		//$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', false);
		$('#src_url').prop('required', true);
		
		$('#qf_name').prop('disabled', true);
		$('#qf_name').prop('required', false);
	});
	
	$(document).on("click", "#src_qf_radio", function() {
		$('#qgs_file').prop('disabled', true);
		$('#qgs_file').prop('required', false);
		
		//$('#pg_store_id').prop('disabled', true);
		//$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#qf_name').prop('disabled', false);
		$('#qf_name').prop('required', true);
	});

	// --- Warm qcarta-tiles disk cache: one map per row (GET /api/tiles/...) ---
	var tileSeedAbort = null;
	var tileSeedRunning = false;

	function showTileSeedModal() {
		var el = document.getElementById('tile_seed_modal');
		if (typeof bootstrap !== 'undefined' && bootstrap.Modal && el) {
			bootstrap.Modal.getOrCreateInstance(el).show();
		} else if (el) {
			$(el).modal('show');
		}
	}
	function tileSeedSetFormDisabled(disabled) {
		$('#tile_seed_start, #tile_seed_preset_overview, #tile_seed_preset_standard, #tile_seed_preset_detail, #tile_seed_preset_clear, #tile_seed_preset_all').prop('disabled', disabled);
		$('.tile-seed-z').prop('disabled', disabled);
	}

	function stripLayerPrefix(name) {
		if (!name) return name;
		var parts = String(name).split('.');
		return parts.length > 1 ? parts[parts.length - 1] : name;
	}
	function layersParamForTiles(commaList) {
		return commaList.split(',').map(function (s) { return stripLayerPrefix(s.trim()); }).filter(Boolean).join(',');
	}
	/** Web Mercator half-extent (meters); must match qcarta-tiles tileToBBox. */
	var TILE_SEED_MERC_HALF = 20037508.342789244;
	var TILE_SEED_TILE_SIZE = 256;
	function mercatorMetersToTileXY(mx, my, z) {
		var res = (2 * TILE_SEED_MERC_HALF) / (TILE_SEED_TILE_SIZE * Math.pow(2, z));
		var x = Math.floor((mx + TILE_SEED_MERC_HALF) / (TILE_SEED_TILE_SIZE * res));
		var y = Math.floor((TILE_SEED_MERC_HALF - my) / (TILE_SEED_TILE_SIZE * res));
		return { x: x, y: y };
	}
	function tileRangeForBBox3857(bbox, z) {
		var minx = bbox.minx, miny = bbox.miny, maxx = bbox.maxx, maxy = bbox.maxy;
		var corners = [
			mercatorMetersToTileXY(minx, miny, z),
			mercatorMetersToTileXY(maxx, miny, z),
			mercatorMetersToTileXY(minx, maxy, z),
			mercatorMetersToTileXY(maxx, maxy, z)
		];
		var xs = corners.map(function (c) { return c.x; });
		var ys = corners.map(function (c) { return c.y; });
		var x0 = Math.min.apply(null, xs), x1 = Math.max.apply(null, xs);
		var y0 = Math.min.apply(null, ys), y1 = Math.max.apply(null, ys);
		var n = Math.pow(2, z);
		x0 = Math.max(0, Math.min(n - 1, x0));
		x1 = Math.max(0, Math.min(n - 1, x1));
		y0 = Math.max(0, Math.min(n - 1, y0));
		y1 = Math.max(0, Math.min(n - 1, y1));
		return { x0: x0, x1: x1, y0: y0, y1: y1 };
	}
	function getSelectedZoomLevels() {
		var zs = [];
		$('.tile-seed-z:checked').each(function () {
			var v = parseInt($(this).val(), 10);
			if (!isNaN(v) && v >= 0 && v <= 22) zs.push(v);
		});
		zs.sort(function (a, b) { return a - b; });
		return zs;
	}
	function countTilesForJob(ext3857, zs) {
		var total = 0;
		for (var i = 0; i < zs.length; i++) {
			var z = zs[i];
			var r = tileRangeForBBox3857(ext3857, z);
			total += (r.x1 - r.x0 + 1) * (r.y1 - r.y0 + 1);
		}
		return total;
	}
	function logTileSeedExtent(info) {
		var ex = info.extent3857;
		if (!ex) return;
		console.info(
			'[SEED DEBUG] extent used:',
			ex.minx,
			ex.miny,
			ex.maxx,
			ex.maxy,
			'EPSG:3857',
			'(source=' + (info.extent_source || '') + ')'
		);
		if (info.seed_debug_extent) {
			console.info('[SEED DEBUG] extent detail:', info.seed_debug_extent);
		}
		if (info.seed_debug_gpkg && info.seed_debug_gpkg.length) {
			for (var g = 0; g < info.seed_debug_gpkg.length; g++) {
				console.info('[SEED DEBUG]', info.seed_debug_gpkg[g]);
			}
		}
		if (info.extent_warnings && info.extent_warnings.length) {
			for (var w = 0; w < info.extent_warnings.length; w++) {
				console.warn('[SEED DEBUG]', info.extent_warnings[w]);
			}
		}
	}
	function buildTileUrls(info, zs) {
		var mapEnc = encodeURIComponent(info.map);
		var layersEnc = encodeURIComponent(layersParamForTiles(info.layers));
		var baseQs = '?map=' + mapEnc + '&layers=' + layersEnc + '&meta=4&buffer=64';
		var urls = [];
		for (var i = 0; i < zs.length; i++) {
			var z = zs[i];
			var r = tileRangeForBBox3857(info.extent3857, z);
			for (var x = r.x0; x <= r.x1; x++) {
				for (var y = r.y0; y <= r.y1; y++) {
					urls.push('/api/tiles/' + z + '/' + x + '/' + y + '.png' + baseQs);
				}
			}
		}
		return urls;
	}
	function setTileSeedProgress(done, total, ok, fail) {
		var pct = total ? Math.round(100 * done / total) : 0;
		var bar = $('#tile_seed_progress');
		bar.css('width', pct + '%').attr('aria-valuenow', pct).text(pct + '%');
		$('#tile_seed_status').text(
			'Tiles: ' + done + ' / ' + total + ' (' + pct + '%) — ' + ok + ' OK, ' + fail + ' errors'
		);
	}
	function tileSeedSetZoomChecked(zMin, zMax, checked) {
		$('.tile-seed-z').each(function () {
			var z = parseInt($(this).val(), 10);
			if (isNaN(z)) return;
			if (z >= zMin && z <= zMax) {
				$(this).prop('checked', checked);
			}
		});
	}
	function openTileSeedModalForRow(tr) {
		var id = tr.attr('data-id');
		if (!id || tr.attr('data-cached') !== 'yes') {
			alert('Tile cache is not enabled for this map. Edit the layer and turn on “Enable Tile Cache”.');
			return;
		}
		var name = tr.find('td:first').text().trim() || ('Map ' + id);
		$('#tile_seed_layer_id').val(id);
		$('#tile_seed_map_display').text(name);
		$('#tile_seed_progress').removeClass('progress-bar-animated').css('width', '0%').text('0%');
		$('#tile_seed_status').text('Idle. Press Start to fetch tiles for this map only.');
		$('#tile_seed_stop').prop('disabled', true);
		tileSeedSetFormDisabled(false);
		showTileSeedModal();
		refreshTileSeedEstimate();
	}

	function refreshTileSeedEstimate() {
		var id = $('#tile_seed_layer_id').val();
		var elEst = $('#tile_seed_estimate');
		elEst.text('').removeClass('text-danger').addClass('text-muted');
		if (!id) {
			elEst.text('Open “Warm cache” from a map row.');
			return;
		}
		var zs = getSelectedZoomLevels();
		if (!zs.length) {
			elEst.text('Select at least one zoom level.');
			return;
		}
		$.ajax({
			type: 'POST',
			url: 'action/' + tbl_action + '.php',
			data: { action: 'tile_seed_info', id: id },
			dataType: 'json',
			success: function (r) {
				if (!r.success) {
					elEst.text(r.message || 'Could not load map info').removeClass('text-muted').addClass('text-danger');
					return;
				}
				logTileSeedExtent(r);
				var n = countTilesForJob(r.extent3857, zs);
				var zStr = zs.length > 12 ? zs.length + ' levels' : zs.join(', ');
				var srcHint =
					r.extent_source === 'wms_epsg4326_projected'
						? ' (extent from WMS EPSG:4326 projected to EPSG:3857)'
						: ' (EPSG:3857 extent; source: ' + (r.extent_source || 'unknown') + ')';
				elEst.text(
					'This map only: about ' +
						n +
						' tile requests for zoom level' +
						(zs.length === 1 ? ' ' : 's ') +
						zStr +
						srcHint +
						'.'
				);
			}
		});
	}

	$(document).on('click', '.warm_tile_cache', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var tr = $(this).closest('tr');
		openTileSeedModalForRow(tr);
	});

	$(document).on('change', '.tile-seed-z', refreshTileSeedEstimate);
	$(document).on('click', '#tile_seed_preset_overview', function () {
		$('.tile-seed-z').prop('checked', false);
		tileSeedSetZoomChecked(0, 6, true);
		refreshTileSeedEstimate();
	});
	$(document).on('click', '#tile_seed_preset_standard', function () {
		$('.tile-seed-z').prop('checked', false);
		tileSeedSetZoomChecked(0, 10, true);
		refreshTileSeedEstimate();
	});
	$(document).on('click', '#tile_seed_preset_detail', function () {
		$('.tile-seed-z').prop('checked', false);
		tileSeedSetZoomChecked(0, 14, true);
		refreshTileSeedEstimate();
	});
	$(document).on('click', '#tile_seed_preset_clear', function () {
		$('.tile-seed-z').prop('checked', false);
		refreshTileSeedEstimate();
	});
	$(document).on('click', '#tile_seed_preset_all', function () {
		tileSeedSetZoomChecked(0, 22, true);
		refreshTileSeedEstimate();
	});

	$('#tile_seed_modal').on('hidden.bs.modal', function () {
		if (tileSeedAbort) {
			tileSeedAbort.abort();
			tileSeedAbort = null;
		}
		tileSeedRunning = false;
		$('#tile_seed_stop').prop('disabled', true);
		$('#tile_seed_progress').removeClass('progress-bar-animated');
		tileSeedSetFormDisabled(false);
	});

	$(document).on('click', '#tile_seed_stop', function () {
		if (tileSeedAbort) {
			tileSeedAbort.abort();
		}
	});

	$(document).on('click', '#tile_seed_start', function () {
		if (tileSeedRunning) return;
		var id = $('#tile_seed_layer_id').val();
		if (!id) {
			alert('Open this dialog from the speedometer icon on a map row.');
			return;
		}
		var zs = getSelectedZoomLevels();
		if (!zs.length) {
			alert('Select at least one zoom level to seed.');
			return;
		}
		$.ajax({
			type: 'POST',
			url: 'action/' + tbl_action + '.php',
			data: { action: 'tile_seed_info', id: id },
			dataType: 'json',
			success: function (info) {
				if (!info.success) {
					alert(info.message || 'Failed to load map');
					return;
				}
				logTileSeedExtent(info);
				var urls = buildTileUrls(info, zs);
				var total = urls.length;
				if (total === 0) {
					alert('No tiles in range for this map (check bounding box).');
					return;
				}
				var MAX_SOFT = 2000;
				var MAX_HARD = 40000;
				if (total > MAX_HARD) {
					alert(
						'Too many tiles (' +
							total +
							') for this map. Uncheck some zoom levels (hard limit ' +
							MAX_HARD +
							').'
					);
					return;
				}
				if (total > MAX_SOFT && !confirm('Seed ' + total + ' tiles for this map only?')) {
					return;
				}
				tileSeedRunning = true;
				tileSeedAbort = new AbortController();
				$('#tile_seed_stop').prop('disabled', false);
				tileSeedSetFormDisabled(true);
				$('#tile_seed_progress').addClass('progress-bar-animated');
				var done = 0,
					ok = 0,
					fail = 0;
				var signal = tileSeedAbort.signal;
				var queue = urls.slice();
				setTileSeedProgress(0, total, 0, 0);

				function worker() {
					return new Promise(function (resolve) {
						function next() {
							if (signal.aborted) {
								resolve();
								return;
							}
							var u = queue.shift();
							if (!u) {
								resolve();
								return;
							}
							fetch(u, { credentials: 'same-origin', signal: signal })
								.then(function (res) {
									if (res.ok) ok++;
									else fail++;
								})
								.catch(function (err) {
									if (err.name !== 'AbortError') fail++;
								})
								.finally(function () {
									done++;
									setTileSeedProgress(done, total, ok, fail);
									next();
								});
						}
						next();
					});
				}
				Promise.all([worker(), worker(), worker()]).finally(function () {
					tileSeedRunning = false;
					tileSeedAbort = null;
					$('#tile_seed_stop').prop('disabled', true);
					$('#tile_seed_progress').removeClass('progress-bar-animated');
					tileSeedSetFormDisabled(false);
					$('#tile_seed_status').text('Finished for this map: ' + ok + ' OK, ' + fail + ' errors out of ' + total + '.');
				});
			}
		});
	});
});
