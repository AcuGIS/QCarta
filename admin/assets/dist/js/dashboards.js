var tbl_action = 'dashboard';

function btn_upload_post(data){
	$.ajax({
		type: "POST",
		url: 'action/' + tbl_action + '.php',
		data: data,
		processData: false,
		contentType: false,
		dataType: "json",
		success: function(response){
			if(response.success){
				$('#btn_upload').toggle();
				$('#addnew_modal').modal('hide');
				
				if(data.get('id') > 0){	// if edit
					location.reload();
				}else if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
					location.reload();
				}else{
					const name_a = '<a href="../dashboard.php?id=' + response.id + '">' + data.get('name') + '</a>';
					const is_public = data.get('public') == 't' ? 'yes' : 'no';

					let tds = [
						response.id,
						{ "display": name_a, "@data-order": data.get('name') },
						data.get('description'),
						{ "display": $('#layer_id option:selected').text(), "@data-order": data.get('layer_id') },
						`<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
						<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
						<a class="edit_preview me-2" title="Edit Preview" data-toggle="tooltip"><i class="text-warning bi bi-easel fs-5"></i></a>`
					];
					sortTable.row.add(tds).draw();
					let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
					dtrow.attr('data-id', response.id);
					dtrow.attr('data-public', is_public);
					dtrow.attr('data-layer_id', $('#layer_id').val());
					dtrow.attr('data-group_id', $('#group_id').val().join(','));
				}
			}else{
				alert("Upload failed." + response.message);
			}
		}
	});	
}

$(document).on("click", ".edit_preview", function() {
	let tr = $(this).parents("tr");
	let id = tr.attr('data-id');
	window.location.href = 'edit_dashboard.php?id=' + id;
});

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('doc_form').submit(false);
$("div .progress").hide();

$(document).on("click", ".add-modal", function() {
	$('#addnew_modal').modal('show');
	$('#btn_create').html('Create');
	
	$('#id').val(0);
	$('#filename').prop('disabled', false);
});

// Edit row on edit button click
$(document).on("click", ".edit", function() {
	let tr = $(this).parents("tr");
	let tds = tr.find('td');
	
	$('#btn_create').html('Update');
	$('#addnew_modal').modal('show');

	$('#id').val(tds[0].textContent);
	$('#name').val(tds[1].textContent);
	$('#description').val(tds[2].textContent);
	//$('#filename').val(tds[3].textContent);
	//$('#filename').prop('disabled', true);
	$('#public').prop('checked', (tr.attr('data-public') == 'yes'));
	$('#layer_id').val(tr.attr('data-layer_id'));
	$('#group_id').val(tr.attr('data-group_id').split(','));
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
		
	$(document).on("click", "#btn_create", function() {
			var obj = $(this);
			var input = $('#dashboard_form').find('input[type="text"], input[type="checkbox"], select');
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
				$('#dashboard_form').find(".error").first().focus();
				obj.toggle();
			}else{
  	    const fileInput = document.getElementById('filename');
  
  			let data = new FormData($('#dashboard_form')[0]);
  			data.delete('filename');
        if(fileInput.files.length > 0){
    		  data.append('filename', fileInput.files[0].name);
     			uploadFile('filename', 'action/upload.php', btn_upload_post, data);
        }else{
          btn_upload_post(data);
        }
			}
	});

});
