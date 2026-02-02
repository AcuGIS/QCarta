var tbl_action = 'access_key';

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('#key_form').submit(false);

// Edit row on edit button click
$(document).on("click", ".edit", function() {
	let tr = $(this).parents("tr");
	let tds = tr.find('td');
	
	$('#btn_create').html('Update');
	$('#addnew_modal').modal('show');

	$('#id').val(tr.attr('data-id'));
	$('#valid_until').val(tds[1].textContent);
	$('#allow_from').val(tds[2].getHTML().replace('<br>', ','));
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
	$(document).on("click", ".clear-expired", function() {
			var obj = $(this);
			var data = {'action': 'clear-expired'}

			$.ajax({
					type: "POST",
					url: 'action/' + tbl_action + '.php',
					data: data,
					dataType:"json",
					success: function(response){
						if(response.success){
							window.location.href = 'access.php?tab=key';
						}else{
							alert(response.message);
						}
					}
			});
	});
	
	$(document).on("click", "#btn_create", function() {
			var obj = $(this);
			var input = $('#key_form').find('input[type="text"], input[type="datetime-local"]');
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
				$('#key_form').find(".error").first().focus();
				obj.toggle();
			}else{
				let data = new FormData($('#key_form')[0]);
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
									data.get('access_key'),
									data.get('valid_until'),
									data.get('allow_from').replace(',', '<br>'),
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