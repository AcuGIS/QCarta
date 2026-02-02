$(document).ready(function() {
	$('[data-toggle="tooltip"]').tooltip();	
	
	// action click
	$(document).on("click", ".start, .stop, .restart, .enable, .disable", function() {
			var obj = $(this);
			var id = obj.parents("tr").attr('data-id');
			var data = {'action': obj.attr('class'), 'name': id}
			
			let tr = obj.parents("tr");
			
			obj.prop('disabled', true);
			
			$.ajax({
				type: "POST",
				url: 'action/service.php',
				data: data,
				dataType:"json",
				success: function(response){
					if(response.success) { // means, new record is added
						window.location.href = 'services.php';
					}else{
						alert(response.message);
					}
				},
				complete: function(data){
					obj.prop('disabled', false);
				}
			});
	});
});