var tbl_action = 'qgs_layer';

function load_select(id, name, arr){
	var obj = $('#' + id);
	if(arr.length === 0){
		obj.replaceWith(`<input type="text" class="form-control" name="` + name +`" id="` + id + `" value="" required/>`);
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
	obj.replaceWith(`<select class="form-select" id="`+ id + `" name="`+ name +`" multiple required>` + opts + `</select>`);
	// selecting first element
	if(edit_row != null){
		$('#' + id).val(edit_row[id]);
	}else{
		$('#' + id).val(arr[idx]);
	}
	$('#' + id).trigger('change');
}

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();	
$('#layer_form').submit(false);

	// Edit row on edit button click
	$(document).on("click", ".edit", function() {
		let tr = $(this).parents("tr");
		let tds = tr.find('td');
		
		$('#btn_create').html('Update');
		$('#addnew_modal').modal('show');

		$('#id').val(tr.attr('data-id'));
		$('#name').val(tds[0].getAttribute('data-order'));
		$('#public').prop('checked', (tr.attr('data-public') == 'yes'));
		$('#cached').prop('checked', (tr.attr('data-cached') == 'yes'));
		$('#proxyfied').prop('checked', (tr.attr('data-proxyfied') == 'yes'));

		$('#exposed').prop('disabled', !$('#proxyfied').prop('checked'));
		
		$('#exposed').prop('checked', (tr.attr('data-exposed') == 'yes'));
		$('#customized').prop('checked', (tr.attr('data-customized') == 'yes'));
		$('#store_id').val(tds[2].getAttribute('data-value')).trigger('change');
		$('#group_id').val(tds[3].getAttribute('data-value').split(','));
		edit_row = {'layers': tds[1].innerHTML.split('<br>')};
	});
	
	$(document).on("click", ".edit_preview", function() {
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		window.location.href = 'edit_preview.php?id=' + id;
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
							
								const tds = [
									{ "display": name_a, "@data-order": data.get('name') },
									data.getAll('layers[]').join(','),
									$('#store_id').find(':selected').text(), 
									$('#group_id').find(':selected').toArray().map(item => item.text).join(','),
									`<a class="info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
									<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];

								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-id', response.id);
								dtrow.attr('data-public', is_public);
								dtrow.attr('data-customized', is_customized);
								dtrow.attr('data-cached', is_cached);
								dtrow.attr('data-proxyfied', is_proxyfied);
								dtrow.attr('data-exposed', is_exposed);
								dtrow.find('td:eq(0)').attr('data-order', $('#name').val());
								dtrow.find('td:eq(4)').attr('data-value', $('#store_id').val());
								dtrow.find('td:eq(5)').attr('data-value', $('#group_id').val().join(','));
							}
						}else{
							alert("Create failed:" + response.message);
						}
					}
				});
			}
	});
});
