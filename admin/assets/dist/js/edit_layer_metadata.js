const tbl_action = 'layer_metadata';

$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('metadata_form').submit(false);
	
	$(document).on("click", "#btn_update", function() {
			var obj = $(this);
			var input = $('metadata_form').find('input[type="text"], input[type="number"], input[type="date"], textarea, select');
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
				$('#metadata_form').find(".error").first().focus();
				obj.toggle();
			}else{
  			let data = new FormData($('#metadata_form')[0]);
  			$.ajax({
  				type: "POST",
  				url: 'action/layer_metadata.php',
  				data: data,
  				processData: false,
  				contentType: false,
  				dataType: "json",
  				success: function(response){
  						alert(response.message);
  				},
          complete: function(data){
            obj.toggle();
          }
        });
			}
	});

});
