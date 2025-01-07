var tbl_action = 'user';

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
$('#user_form').submit(false);

// Edit row on edit button click
$(document).on("click", ".edit", function() {
	let tr = $(this).parents("tr");
	let tds = tr.find('td');
	
	$('#btn_create').html('Update');
	$('#addnew_modal').modal('show');

	$('#id').val(tds[0].textContent);
	$('#name').val(tds[1].textContent);
	$('#email').val(tds[2].textContent);
	$('#password').val(tds[3].textContent);
	$('#group_id').val(tds[4].getAttribute('data-value').split(','));
	$('#accesslevel').val(tds[5].textContent);
	$('#secret_key').val(tds[6].textContent);
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
	
	// Delete row on delete button click
	$(document).on("click", ".secret_reset", function() {
			var obj = $(this);
			var data = {'action': 'secret_reset', 'id': $('#id').val() }

			$.ajax({
					type: "POST",
					url: 'action/' + tbl_action + '.php',
					data: data,
					dataType:"json",
					success: function(response){
						if(response.success) { // means, new record is added
							$('#secret_key').val(response.secret_key);
							obj.parents("tr").remove();
						}else{
							alert(response.message);
						}
					}
			});
	});
	
	$(document).on("click", "#btn_create", function() {
			var obj = $(this);
			var input = $('#user_form').find('input[type="text"], input[type="checkbox"], select');
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
				$('#user_form').find(".error").first().focus();
				obj.toggle();
			}else{
				let data = new FormData($('#user_form')[0]);
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

								let tds = [
									data.get(response.id),
									data.get('name'),
									data.get('email'),
									data.get('*******'),
									data.getAll('group_id[]').join(','),
									data.get('accesslevel'),
									data.get('secret_key'),
									`<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
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

});