<?php require('../../admin/incl/index_prefix.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<base target="_top">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<title>PG layer example</title>
	
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
	<?php require('../../admin/incl/meta.php'); ?>
	<link href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.dataTables.min.css" rel="stylesheet">
	<script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.min.js"></script>
	
	<script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.html5.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.print.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

</head>
<body>
	
	<div id='dataTables'>
		<div class="tab-content pt-2">
			<table id="sortTable" class="table table-striped table-bordered" cellspacing="0" width="100%"></table>
		</div>
	</div>
	
<script type="text/javascript">
$(document).ready(function() {

	$.getJSON( "geojson.php", function( data ) {
		let columns = Object.keys(data.features[0].properties).map(function(k){
			return {'title' : k};
		});

		const colData = data.features.map(function(e) {
			let props = Object.keys(e.properties).map(function(k){
				return e.properties[k];
			});			
			return props;
		});

		$('#sortTable').DataTable({
			columns: columns,
			deferRender: true,
			data: colData,
			layout: {
	    	topStart: {
	      	buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
				}
	    }
		});
	});
});
</script>
</body>
</html>
