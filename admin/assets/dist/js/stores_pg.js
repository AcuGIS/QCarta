var tbl_action = 'pglink';

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

function btn_import_post(data){
	let pg_store_id = 0;

	$.ajax({
		type: "POST",
		url: 'action/import.php',
		data: data,
		processData: false,
		contentType: false,
		dataType: "html",
		success: function(response){
			
			pg_store_id = response.match(/<p><b>Link ID:<\/b>(\d+)<\/p>/)[1];
			
			$('#btn_import').toggle();

			$('#import_modal').modal('hide');
			
			$("#import_output").show();
			$("#import_output").html(response);
		},
		complete: function(){			
			if(data.get('create_qgs') && (pg_store_id > 0)){
				let qgs_data = {'action': 'save', 'name': data.get('dbname') + ' QGS',
				'pg_store_id[]': [pg_store_id], 'group_id[]' : data.get('group_id[]')
				};
												
				$.ajax({
					 type: "POST",
					 url: 'action/qgs.php',
					 data: qgs_data,
					 dataType:"json",
					 success: function(qgs_response){
							if(qgs_response.success) {
								alert("QGS Store created with ID:" + qgs_response.id);
							}else{
								alert(qgs_response.message);
							}
					 }
				 });
			}
		}
	});
}

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
	
$('#import_form').submit(false);
$("#import_output").hide();
$("div .progress").hide();

$(document).on("click", ".import-modal", function() {
	$('#import_modal').modal('show');
});

	// Edit row on edit button click
	$(document).on("click", ".edit", function() {
		let tr = $(this).parents("tr");
		let tds = tr.find('td');
		let ai = $(this).siblings('.pwd_vis').find('i');
		
		$('#btn_upload').html('Update');
		$('#addnew_modal').modal('show');

		$('#id').val(tr.attr('data-id'));
		$('#name').val(tds[0].textContent);
		$('#group_id').val(tds[1].getAttribute('data-value').split(','));
		$('#host').val(tds[2].textContent);
		$('#port').val(tds[3].textContent);
		$('#schema').val(tds[4].textContent);
		
		$('#dbname').val(tds[5].textContent);
		$('#username').val(tds[6].textContent);
		
			
		if(ai.hasClass('bi-eye')){
			let data = {'pwd_vis': true, 'id': $(this).parents("tr").attr('data-id')}
			$.ajax({
				 type: "POST",
				 url: 'action/pglink.php',
				 data: data,
				 dataType:"json",
				 success: function(response){
					if(response.success) {
						$('#password').val(response.message);
					}
				 }
			 });
		}else{
			$('#password').val(tds[7].textContent);
		}
		
		
		edit_row = {'dbname': tds[5].textContent};
		
		$('.list_databases').trigger('click');
	});

	// Delete row on delete button click
	$(document).on("click", ".delete", function() {
			var obj = $(this);
			var id = obj.parents("tr").attr('data-id');
			var data = {'delete': true, 'id': id}
			
			if(confirm('PG link will be deleted ?')){

				let host = obj.closest("tr").children("td").eq(3).text();
				if((host == 'localhost') && confirm('Delete local database too ?')){
					data['drop'] = true;
				}

				$.ajax({
					type: "POST",
					url: 'action/pglink.php',
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
	
	// Change on password visibility
	$(document).on("click", ".pwd_vis", function() {
			let obj = $(this);	// <a> with the icon
			let id = obj.parents("tr").attr('data-id');
			let data = {'pwd_vis': true, 'id': id}
			
			let ai = obj.find('i');
			let td = obj.closest("td").prev();
			const is_edit = (td.find('input').length > 0);
			
			if(ai.hasClass('bi-eye')){
				$.ajax({
					 type: "POST",
					 url: 'action/pglink.php',
					 data: data,
					 dataType:"json",
					 success: function(response){
						if(response.success) {
							ai.toggleClass("bi-eye bi-eye-slash");
							if(is_edit){
								td.find('input').prop('type', 'text');
							}else{
								td.text(response.message);
							}
						}
					 }
				 });
							 
			}else{
				ai.toggleClass("bi-eye bi-eye-slash");
				if(is_edit){
					td.find('input').prop('type', 'password');
				}else{
					td.text('******');
				}
			}
	});
	
	// Show PG connection info
	$(document).on("click", ".conn_info", function() {
			var obj = $(this);	// <a> with the icon
			var id = obj.parents("tr").attr('data-id');
			var data = {'conn_info': true, 'id': id}
											
			$.ajax({
							 type: "POST",
							 url: 'action/pglink.php',
							 data: data,
							 dataType:"json",
							 success: function(response){
									 if(response.success) {
										$('#conn_modal .modal-body').html(response.message);
										$('#conn_modal').modal('show');
									}
							 }
				 });
	});

	$(document).on("click", ".clone", function() {
		var obj = $(this);	// <a> with the icon
		var id = obj.parents("tr").attr('data-id');
		
		$('#clone_id').val(id);
		$('#clone_modal').modal('show');
	});
	
	$(document).on("click", ".backup", function() {
		let obj = $(this);	// <a> with the icon
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		let tds = tr.find('td');
		
		$('#backup_id').val(id);
		$('#backup_prefix').val(tds[6].textContent);
		$('#backup_modal').modal('show');
	});
	
	$(document).on("click", ".restore", function() {
		let obj = $(this);	// <a> with the icon
		let tr = $(this).parents("tr");
		let id = tr.attr('data-id');
		let tds = tr.find('td');
		
		$.ajax({
			 type: "POST",
			 url: 'action/pglink.php',
			 data: {'id': id, 'list_backups': true},
			 dataType:"json",
			 success: function(response){
					 if(response.success) {
						load_select('dump_file', 'dump_file', response.dump_files);
					}else{
						alert(response.message);
					}
			 }
		});
		
		$('#restore_id').val(id);
		$('#restore_modal').modal('show');
	});
	
	// Show PG connection info
	$(document).on("click", "#create_only", function() {
		if($(this).prop('checked')){
			$('#import_file').prop('disabled', true);
			$('#btn_import').html('Create');
		}else{
			$('#import_file').prop('disabled', false);
			$('#btn_import').html('Import');
		}
	});
	
	$(document).on("click", ".list_databases", function() {
		var obj = $(this);	// <a> with the icon
		var id = obj.parents("tr").attr('data-id');
		let data = new FormData($('#pglink_form')[0]);
		data.append('list_databases', true);
		data.delete('save');
										
		$.ajax({
			 type: "POST",
			 url: 'action/pglink.php',
			 data: data,
			 dataType:"json",
			 processData: false,
			 contentType: false,
			 success: function(response){
				 if(response.success) {
					 load_select('dbname', 'dbname', response.databases);
				 }else{
					 alert(response.message);
				 }
			 }
		 });
	});

	$(document).on("click", ".visibility", function() {
		var ai = $(this).find('i');
		if(ai.hasClass('bi-eye')){
			$('#password').prop('type', 'text');
		}else{
			$('#password').prop('type', 'password');
		}
		ai.toggleClass('bi-eye bi-eye-slash');
	});
	
	$(document).on("click", "#submit_pglink", function() {
			var obj = $(this);
			var input = $('#pglink_form').find('input[type="text"], input[type="number"], input[type="password"]');
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
				$('#import_form').find(".error").first().focus();
				obj.toggle();
			}else{
					let data = new FormData($('#pglink_form')[0]);
					$.ajax({
						type: "POST",
						url: 'action/pglink.php',
						data: data,
						processData: false,
						contentType: false,
						dataType: "html",
						success: function(response){
							obj.toggle();
							$('#conn_modal').modal('hide');
							
							if(data.get('id') > 0){	// if edit
								location.reload();
							}else if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
								location.reload();
							}else{
								let tds = [
									data.get('name'),
									data.getAll('group_id[]').join(','),
									data.get('host'),
									data.get('port'),
									data.get('schema'),
									data.get('dbname'),
									data.get('username'),
									'******',
									`<a class="conn_info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
									<a class="pwd_vis" title="Show Password" data-toggle="tooltip"><i class="text-secondary bi bi-eye"></i></a>
									<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete_pg" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];

								sortTable.row.add(tds).draw();
								$("table tbody tr:last-child").attr('data-id', response.id);
							}
						}
					});
			}
	});
	
	$(document).on("click", "#btn_import", function() {
			var obj = $(this);
			var input = $('#import_form').find('input[type="text"], input[type="file"]');
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
				$('#import_form').find(".error").first().focus();
				obj.toggle();
			}else{

				const fileInput = document.getElementById('import_file');

				let data = new FormData($('#import_form')[0]);
				data.delete('source[]');
				
				if(data.get('src_url')){
					uploadURL('src_url', 'action/upload.php', btn_import_post, data);
				}else{
					for (var i = 0; i < fileInput.files.length; i++) {
						data.append('source[]', fileInput.files[i].name);
					}

					uploadFile('import_file', 'action/upload.php', btn_import_post, data);
				}
			}
	});
	
	$(document).on("click", "#clone_pglink", function() {
			var obj = $(this);
			var input = $('#clone_form').find('input[type="text"], input[type="checkbox"]');
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
				$('#clone_form').find(".error").first().focus();
				obj.toggle();
			}else{
					let data = new FormData($('#clone_form')[0]);
					$.ajax({
						type: "POST",
						url: 'action/pglink.php',
						data: data,
						processData: false,
						contentType: false,
						dataType: "json",
						success: function(response){
							obj.toggle();
							$('#clone_modal').modal('hide');
							
							if(sortTable.rows().count() == 0){ // if no rows in table, there are no data-order tags!
								location.reload();
							}else{								
								let tds = [
									data.get('name'),
									$('#group_id').find(':selected').toArray().map(item => item.text).join(','),
									data.get('host'),
									data.get('port'),
									data.get('schema'),
									data.get('dbname'),
									data.get('username'),
									'******',
									`<a class="conn_info" title="Show Connection" data-toggle="tooltip"><i class="text-info bi bi-info-circle"></i></a>
									<a class="pwd_vis" title="Show Password" data-toggle="tooltip"><i class="text-secondary bi bi-eye"></i></a>
									<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete_pg" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];

								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-id', response.id);
								dtrow.find('td:eq(1))').attr('data-value', $('#group_id').val().join(','));
							}
						}
					});
			}
	});
	
	$(document).on("click", "#clone_pglink", function() {
			var obj = $(this);
			var input = $('#backup_form').find('input[type="text"], input[type="checkbox"]');
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
				$('#backup_form').find(".error").first().focus();
				obj.toggle();
			}else{
					let data = new FormData($('#backup_form')[0]);
					$.ajax({
						type: "POST",
						url: 'action/pglink.php',
						data: data,
						processData: false,
						contentType: false,
						dataType: "json",
						success: function(response){
							obj.toggle();
							$('#clone_modal').modal('hide');
							alert(response.message);
						}
					});
			}
	});
	
	$(document).on("click", "#restore_pglink", function() {
			var obj = $(this);
			var input = $('#restore_form').find('input[type="text"], input[type="checkbox"], select');
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
				$('#restore_form').find(".error").first().focus();
				obj.toggle();
			}else{
					let data = new FormData($('#restore_form')[0]);
					$.ajax({
						type: "POST",
						url: 'action/pglink.php',
						data: data,
						processData: false,
						contentType: false,
						dataType: "json",
						success: function(response){
							obj.toggle();
							$('#restore_modal').modal('hide');
							alert(response.message);
						}
					});
			}
	});
	
	$(document).on("click", "#delete_dump", function() {
			let obj = $(this);
			let data = {'id': $('#restore_id').val(),
				'dump_file': $('#dump_file').find(":selected").val(),
				'delete_dump': true };
			
			obj.toggle();
			
			$.ajax({
				type: "POST",
				url: 'action/pglink.php',
				data: data,
				dataType: "json",
				success: function(response){
					obj.toggle();
					if(response.success){
						$('#dump_file').find('[value="' + data.dump_file + '"]').remove();
					}else{
						alert(response.message);
					}
				}
			});
	});
	
	$(document).on("click", "#src_file_radio", function() {
		$('#import_file').prop('disabled', false);
		$('#import_file').prop('required', true);
		
		$('#src_url').prop('disabled', true);
		$('#src_url').prop('required', false);
	});
	
	$(document).on("click", "#src_url_radio", function() {
		$('#import_file').prop('disabled', true);
		$('#import_file').prop('required', false);
		
		$('#src_url').prop('disabled', false);
		$('#src_url').prop('required', true);
	});
});
