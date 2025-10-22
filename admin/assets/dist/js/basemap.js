$(document).ready(function() {
  
    $('[data-toggle="tooltip"]').tooltip();
   
    $(document).on("click", ".add-modal", function() {
			$('#addnew_modal').modal('show');
			$('#btn_create').html('Create');
			
			$('#id').val(0);
			$('#name').val('');
			$('#description').val('');
			$('#url').val('');
			$('#type').val('xyz');
			$('#attribution').val('');
			$('#thumbnail').val('');
			$('#min_zoom').val(0);
			$('#max_zoom').val(18);
			$('#public').prop('checked', false);
			$('#group_id').val('');
		});
    
    // Handle edit modal
    $(document).on("click", ".edit", function() {
        let tr = $(this).parents("tr");
      
        $('#addnew_modal').modal('show');
        $('#btn_create').html('Update');
        
        // Populate form fields
        $('#id').val(tr.data('id'));
        $('#name').val(tr.data('name'));
        $('#description').val(tr.data('description'));
        $('#url').val(tr.data('url'));
        $('#type').val(tr.data('type'));
        $('#attribution').val(tr.data('attribution'));
        $('#thumbnail').val(tr.data('thumbnail'));
        $('#min_zoom').val(tr.data('min-zoom'));
        $('#max_zoom').val(tr.data('max-zoom'));
        $('#public').prop('checked', tr.data('public') === 't');
        $('#group_id').val(tr.attr('data-group_id').split(','));
        
        // Note: group_id population would need to be handled via AJAX
        // since we don't have that data in the data attributes
    });
    
    // Handle delete modal
    $(document).on("click", ".delete", function() {
        let obj = $(this);
        let tr = $(this).parents("tr");
        
        let id = tr.data('id');
        let name = tr.data('name');
        
        if(confirm('Basemap ' + name + ' will be deleted ?')){
          $.ajax({
              url: 'action/basemap.php',
              type: 'POST',
              data: {
                  action: 'delete',
                  id: id
              },
              success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                  sortTable.row(obj.parents("tr")).remove().draw();
                }else{
                  alert(response.message);
                }
              },
              error: function() {
                  showAlert('danger', 'Network error occurred');
              }
          });
        }
    });
    
    // Handle create/update button
    $(document).on("click", "#btn_create", function() {
        var formData = new FormData($('#basemap_form')[0]);
        
        // Add action parameter
        var isUpdate = $('#id').val() > 0;
        formData.set('action', isUpdate ? 'update' : 'create');
        
        // Add public checkbox value
        formData.set('public', $('#public').is(':checked') ? 't' : 'f');

        $.ajax({
            url: 'action/basemap.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
              var result = JSON.parse(response);
              if (result.success) {
                  $('#addnew_modal').modal('hide');
    							//location.reload();
              }
              showAlert('success', result.message);
            },
            error: function() {
                showAlert('danger', 'Network error occurred');
            }
        });
    });

    // Form validation
    $('#basemap_form').on('submit', function(e) {
        e.preventDefault();
        
        var name = $('#name').val().trim();
        var url = $('#url').val().trim();
        var type = $('#type').val();
        var minZoom = parseInt($('#min_zoom').val());
        var maxZoom = parseInt($('#max_zoom').val());
        
        if (!name) {
            showAlert('danger', 'Name is required');
            return false;
        }
        
        if (!url) {
            showAlert('danger', 'URL is required');
            return false;
        }
        
        if (minZoom > maxZoom) {
            showAlert('danger', 'Minimum zoom cannot be greater than maximum zoom');
            return false;
        }
        
        // Trigger create/update
        $('#btn_create').click();
        return false;
    });
    
    // URL validation
    $('#url').on('blur', function() {
        var url = $(this).val().trim();
        if (url && !isValidUrl(url)) {
            $(this).addClass('is-invalid');
            showAlert('warning', 'Please enter a valid URL');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Zoom validation
    $('#min_zoom, #max_zoom').on('input', function() {
        var minZoom = parseInt($('#min_zoom').val()) || 0;
        var maxZoom = parseInt($('#max_zoom').val()) || 18;
        
        if (minZoom > maxZoom) {
            $('#min_zoom, #max_zoom').addClass('is-invalid');
        } else {
            $('#min_zoom, #max_zoom').removeClass('is-invalid');
        }
    });
});

// Helper function to show alerts
function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the content
    $('.content-wrapper').prepend(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Helper function to validate URL
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// Helper function to format zoom levels
function formatZoomLevel(level) {
    return level || 0;
}
