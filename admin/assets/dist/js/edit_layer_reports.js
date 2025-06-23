const tbl_action = 'layer_report';
var editor1 = null;
$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('#query_form').submit(false);

// Edit row on edit button click
$(document).on("click", ".edit", function() {
	let tr = $(this).parents("tr");
	let tds = tr.find('td');
	
	$('#btn_create').html('Update');
	$('#addnew_modal').modal('show');

	$('#id').val(tr.attr('data-id'));
	$('#name').val(tds[0].textContent);
	$('#description').val(tds[1].textContent);
	$('#badge').val(tds[2].textContent);
	$('#database_type').val(tds[3].getAttribute('data-value'));
	editor1.setValue(tds[4].textContent);
	setTimeout(function() {
	  editor1.refresh();
  },500);
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
			var input = $('#query_form').find('input[type="text"], select');
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
				$('#query_form').find(".error").first().focus();
				obj.toggle();
			}else{
				let data = new FormData($('#query_form')[0]);
				
				data.append('sql_query', editor1.getValue());
				
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
									response.id,
									data.get('name'),
									data.get('description'),
									data.get('badge'),
									data.get('database_type'),
									data.get('sql_query'),
									`<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
									<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
								];
								sortTable.row.add(tds).draw();
								let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
								dtrow.attr('data-id', response.id);
							}
						}else{
							alert("Create failed:" + response.message);
						}
					}
				});
			}
	});

});
