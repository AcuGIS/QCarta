var tbl_action = 'qgs_layer';

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
		
		var fd = new FormData();
		fd.append('layer_id', id);
		
		$.ajax({
			type: "POST",
			url: 'action/clear_project_cache.php',
			data: fd,
			processData: false,
			contentType: false,
			dataType: "json",
			success: function(response){
				if(response && response.success){
					alert(`Cache cleared for project: ${response.scope || 'unknown'}\nRemoved: ${response.removed || 0} cache entries`);
				} else {
					alert(`Failed: ${response ? (response.message || 'Unknown error') : 'Invalid response'}`);
				}
			},
			error: function(xhr, status, error){
				var errorMsg = 'Request failed';
				if(xhr.responseText) {
					try {
						var jsonResp = JSON.parse(xhr.responseText);
						errorMsg = jsonResp.message || errorMsg;
					} catch(e) {
						errorMsg = xhr.responseText.substring(0, 100);
					}
				}
				alert(`Error: ${errorMsg}\nStatus: ${xhr.status || status}`);
				console.error('Clear project cache error:', xhr, status, error);
			}
		});
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
							
								const tds = [
									{ "display": name_a, "@data-order": data.get('name') },
									data.getAll('layers[]').join(','),
									$('#store_id').find(':selected').text(),
									`<a class="info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
									<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];

								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-id', response.id);
								dtrow.attr('data-description', response.description);
								dtrow.attr('data-public', is_public);
								dtrow.attr('data-customized', is_customized);
								dtrow.attr('data-cached', is_cached);
								dtrow.attr('data-proxyfied', is_proxyfied);
								dtrow.attr('data-exposed', is_exposed);
								dtrow.attr('data-show_charts', is_show_charts);
								dtrow.attr('data-show_dt', is_show_dt);
								dtrow.attr('data-show_query', is_show_query);
								dtrow.attr('data-show_fi_edit', is_editable);
								dtrow.attr('data-group_id', $('#group_id').val().join(','));
								dtrow.attr('data-print_layout', $('#print_layout').val());
								
								dtrow.find('td:eq(0)').attr('data-order', $('#name').val());
								dtrow.find('td:eq(4)').attr('data-value', $('#store_id').val());
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
});
