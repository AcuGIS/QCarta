const tbl_action = 'plotly_defaults';
let features = null;
let edit_row = null;

function load_select(id, name, multi, arr){
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
	
	//change input to select
	obj.replaceWith(`<select class="form-select" id="`+ id + `" name="`+ name +`" ` + multi + ` required>` + opts + `</select>`);
	// selecting first element	
	if(edit_row != null){
		$('#' + id).val(edit_row[name]);
	}
	$('#' + id).trigger('change');
}

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('#defaults_form').submit(false);

$(document).on("click", ".add-modal", function() {
  $('#feature').prop('disabled', false);
  $("feature").val($("#feature option:first").val());
  $('#feature').trigger('change');
  
  $('#addnew_modal').modal('show');
});

// Edit row on edit button click
$(document).on("click", ".edit", function() {
	let tr = $(this).parents("tr");
	let tds = tr.find('td');
	
	edit_row = {'feature' : tds[0].textContent, 'chartType': tds[1].textContent, 'chartTypes[]': tds[2].textContent.split(','),
	  'xField': tds[3].textContent,
		'yField': tds[4].textContent
	};
	
	$('#feature').val(tds[0].textContent);
	$('#chartType').val(tds[1].textContent);
	$('#chartTypes').val(tds[2].textContent.split(','));
	$('#is_default').prop('checked', (tr.attr('data-default') == '1'));
	
	$('#feature').trigger('change');
	$('#feature').prop('disabled', true);
	
	$('#btn_create').html('Update');
	$('#addnew_modal').modal('show');
});

	// Delete row on delete button click
	$(document).on("click", ".delete", function() {
			var obj = $(this);
			var data = {'action': 'delete', 'id': obj.parents("tr").attr('data-id')}

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
	});
	
	$(document).on("change", '#feature', function() {
		let obj = $(this);
		
		$('#feature').prop('disabled', true);
		
		$.ajax({
				type: "GET",
				url: '../stores/' + STORE_ID + '/wfs.php?service=WFS&version=1.1.0&request=GetFeature&typeName=' + obj.find('option:selected').val() + '&OUTPUTFORMAT=application%2Fjson',
				success: function(response){
				  features = response.features;
					properties = Object.keys(features[0].properties).sort();
          load_select('xField', 'xField', '', properties);
          load_select('yField', 'yField', '', properties);

     			$('#feature').prop('disabled', false);
				}
		});
	});
	
	$(document).on("click", "#btn_create", function() {
			var obj = $(this);
			var input = $('#defaults_form').find('input[type="text"], select');
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
				$('#defaults_form').find(".error").first().focus();
				obj.toggle();
			}else{
				let data = new FormData($('#defaults_form')[0]);

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
							
							if(data.get('id') > 0){ // if edit
							  location.reload();
							}else if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
								location.reload();
							}else{

								let tds = [
									data.get('feature'),
									data.get('chartType'),
									data.get('chartTypes'),
									data.get('xField'),
									data.get('yField'),
									`<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];
								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-feature', response.id);
							}
						}else{
							alert("Create failed:" + response.message);
						}
					}
				});
			}
	});

});
