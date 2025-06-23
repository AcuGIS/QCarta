$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('topic_form').submit(false);

$(document).on("click", ".add-modal", function() {
	$('#addnew_modal').modal('show');
	$('#btn_create').html('Create');
	
	$('#id').val(0);
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
	
	TOPIC_RESOURCES.forEach((topic_type) => {
	  console.log('data-' + topic_type + '_id');
	  $('#' + topic_type + '_id').val(tr.attr('data-' + topic_type + '_id').split(','));
	});
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
			var input = $('topic_form').find('input[type="text"], select');
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
				$('#topic_form').find(".error").first().focus();
				obj.toggle();
			}else{
  			let data = new FormData($('#topic_form')[0]);
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
  								`<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
  								<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>`
  							];
  							sortTable.row.add(tds).draw();
  							let dtrow = sortTable.rows(sortTable.rows().count()-1).nodes().to$();
  							dtrow.attr('data-id', response.id);
  							TOPIC_RESOURCES.forEach((topic_type) => {
  								dtrow.attr('data-' + topic_type + '_id', $('#' + topic_type + '_id').val().join(','));
  							});
  						}
  					}else{
  						alert("Create failed:" + response.message);
  					}
  				}
        });
			}
	});

});
