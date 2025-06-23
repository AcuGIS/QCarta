$(document).ready(function() {

$('[data-toggle="tooltip"]').tooltip();
$('settings_form').submit(false);
	
	$(document).on("click", "#btn_update", function() {
			var obj = $(this);
			var input = $('settings_form').find('input[type="text"], select');
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
				$('#settings_form').find(".error").first().focus();
				obj.toggle();
			}else{
  			let data = new FormData($('#settings_form')[0]);
  			$.ajax({
  				type: "POST",
  				url: 'action/settings.php',
  				data: data,
  				processData: false,
  				contentType: false,
  				dataType: "json",
  				success: function(response){
  					if(!response.success){
  						alert("Create failed:" + response.message);
  					}
  				},
          complete: function(data){
            obj.toggle();
          }
        });
			}
	});

});
