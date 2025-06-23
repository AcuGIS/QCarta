var tbl_action = 'qgs';

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
					
					const is_public = data.get('public') == 't' ? 'yes' : 'no';

					let tds = [
						data.get('name'),
						{ "display": data.get('upload_size'), "@data-order": data.get('upload_size') },
						is_public,
						$('#group_id').find(':selected').toArray().map(item => item.text).join(','),
						`<a class="info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
						 <a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
						 <a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
					];

					sortTable.row.add(tds).draw();
					let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
					dtrow.attr('data-id', response.id);
					dtrow.find('td:eq(3)').attr('data-value', $('#group_id').val().join(','));
				}
			}else{
				alert("Upload failed." + response.message);
			}
		}
	});	
}

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();	
$('#qgs_form').submit(false);
$("div .progress").hide();

	// Edit row on edit button click
	$(document).on("click", ".edit", function() {
		let tr = $(this).parents("tr");
		let tds = tr.find('td');
		
		$('#btn_upload').html('Update');
		$('#addnew_modal').modal('show');

		$('#id').val(tr.attr('data-id'));
		$('#name').val(tds[0].textContent);
		$('#public').prop('checked', (tds[2].textContent == 'yes'));
		$('#group_id').val(tds[3].getAttribute('data-value').split(','));
	});

	// Delete row on delete button click
	$(document).on("click", ".delete", function() {
			var obj = $(this);
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': 'delete', 'id': id}
			
			if(confirm('Store will be deleted ?')){
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
		
		$('#pg_store_id').prop('disabled', true);
		$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#qf_name').prop('disabled', true);
		$('#qf_name').prop('required', false);
	});

	$(document).on("click", "#src_pg_radio", function() {
		$('#qgs_file').prop('disabled', true);
		$('#qgs_file').prop('required', false);
		
		$('#pg_store_id').prop('disabled', false);
		$('#pg_store_id').prop('required', true);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#qf_name').prop('disabled', true);
		$('#qf_name').prop('required', false);
	});
	
	$(document).on("click", "#src_url_radio", function() {
		$('#qgs_file').prop('disabled', true);
		$('#qgs_file').prop('required', false);
		
		$('#pg_store_id').prop('disabled', true);
		$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', false);
		$('#src_url').prop('required', true);
		
		$('#qf_name').prop('disabled', true);
		$('#qf_name').prop('required', false);
	});
	
	$(document).on("click", "#src_qf_radio", function() {
		$('#qgs_file').prop('disabled', true);
		$('#qgs_file').prop('required', false);
		
		$('#pg_store_id').prop('disabled', true);
		$('#pg_store_id').prop('required', false);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
		
		$('#qf_name').prop('disabled', false);
		$('#qf_name').prop('required', true);
	});
});
