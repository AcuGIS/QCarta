var tbl_action = 'pg_layer';

function load_select(name, arr){
	var obj = $('#' + name);
	if(arr.length === 0){
		obj.replaceWith(`<input type="text" class="form-control" name="` + name +`" id="` + name + `" value="" required/>`);
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
	obj.replaceWith(`<select class="form-control" id="`+ name + `" name="`+ name +`">` + opts + `</select>`);
	// selecting first element
	if(edit_row != null){
		$('#' + name).val(edit_row[name]);
	}else{
		$('#' + name).val(arr[idx]);
	}
	$('#' + name).trigger('change');
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
		$('#name').val(tds[0].textContent);
		$('#public').prop('checked', (tds[1].textContent == 'yes'));
		$('#store_id').val(tds[2].getAttribute('data-value')).trigger('change');
		$('#group_id').val(tds[5].getAttribute('data-value').split(','));
		edit_row = {'tbl': tds[3].textContent, 'geom':tds[4].textContent};
	});

	// Delete row on delete button click
	$(document).on("click", ".delete", function() {
			var obj = $(this);
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': 'delete', 'id': id}
			
			if(confirm('Layer will be deleted ?')){
				$.ajax({
					type: "POST",
					url: 'action/pg_layer.php',
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
				 url: 'action/pg_layer.php',
				 data: data,
				 dataType:"json",
				 success: function(response){
						 if(response.success) {
							 let tbl = '<table class="table table-striped table-bordered">';
								$.each(response.message, function(k){
									tbl += '<tr><td>'+ k + '</td><td>' + response.message[k] + '</td></tr>';
								});
								tbl += '</table>';
								$('#info_modal .modal-body').html(tbl);
								$('#info_modal').modal('show');
						}else{
							alert(response.message);
						}
				 }
			 });
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

			if(empty){
				$('#layer_form').find(".error").first().focus();
				obj.toggle();
			}else{
				let data = new FormData($('#layer_form')[0]);
				$.ajax({
					type: "POST",
					url: 'action/pg_layer.php',
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
								const is_public = data.get('public') == 't' ? 'yes' : 'no';
								let tds = [
									data.get('name'),
									is_public,
									data.get('store_id'),
									data.get('tbl'),
									data.get('geom'),
									data.getAll('group_id[]').join(','),
									`<a class="info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
									<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];
								sortTable.row.add(tds).draw();
								$("table tbody tr:last-child").attr('data-id', response.id);
							}
						}else{
							alert("Create failed:" + response.message);
						}
					}
				});
			}
	});

	$(document).on("change", 'select[name="store_id"]', function() {
		let obj = $(this);
		var data = { 'id' : obj.find('option:selected').val(), 'tables' : true };
		
		$('#tbl').prop('disabled', true);
		
		$.ajax({
			type: "POST",
			url: 'action/pglink.php',
			data: data,
			dataType:"json",
			success: function(response){
				 if(response.success) {
					 load_select('tbl', response.tables);
					 	$('#tbl').prop('disabled', false);
				 }else{
					 alert('Error: Failed to list tables. ' + response.message);
				 }
			},
			fail: function(){	alert('Error: POST failure');	}
		});
	});
	
	$(document).on("change", 'select[name="tbl"]', function() {
		let obj = $(this);
		var data = { 'id' : $('#store_id').find('option:selected').val(),
			'tbl' : $('#tbl').find('option:selected').val(),
			'columns' : true
		};
		
		$('#geom').prop('disabled', true);
		
		$.ajax({
			type: "POST",
			url: 'action/pglink.php',
			data: data,
			dataType:"json",
			success: function(response){
				 if(response.success) {
					 load_select('geom', 		response.geoms);
					 	$('#geom').prop('disabled', false);
				 }else{
					 alert('Error: Failed to list geoms/columns. ' + response.message);
				 }
			},
			fail: function(){	alert('Error: POST failure');	}
		});
	});
});