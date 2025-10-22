<?php
    session_start(['read_and_close' => true]);
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
	require('class/table_ext.php');
	require('class/qgs.php');
    require('incl/qgis.php');
    
    if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit;
    }

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$id = (empty($_GET['id'])) ? -1 : intval($_GET['id']);
	
	$obj = new qgs_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	if(($id <= 0) && !$obj->isOwnedByUs($id)){
		http_response_code(405);	//not allowed
		die(405);
	}	
	
	require('../stores/'.$id.'/env.php');
	$feats = layer_get_features(urldecode(QGIS_FILENAME_ENCODED));
	$plotlyDefaults = ['defaults' => [], 'layerDefaults' => [], 'chartTypes' => [], 'fieldMappings' => []];
	$def_feature = '';
	
	$plotly_defaults_file = DATA_DIR.'/stores/'.$id.'/plotly_defaults.json';
	if(is_file($plotly_defaults_file)){
	    $plotlyDefaults = json_decode(file_get_contents($plotly_defaults_file), true);
		$def_feature = $plotlyDefaults['defaults']['layer'];
	}
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" >

<head>
	<title>QCarta</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/sql-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/sql/sql.min.js"></script>
		
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">

	<script type="text/javascript">
	    const STORE_ID = <?=$id?>;
	</script>
	<script src="assets/dist/js/edit_plotly_defaults.js"></script>
</head>

<body>
  
	<div id="container">
		
		<?php const NAV_SEL = 'Store Plotly Defaults'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
		<div id="content">
		
				<h1>Edit default values for Plotly Charts in store <?=$_GET['id']?></h1>
			<div style="width: 99%">
			
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>
								</div>
						</div>
						
						<div class="text-end upgrade-btn">
							<a class="btn btn-warning add-modal" role="button" aria-pressed="true" style="background-color: #ffc107; border-color: #ffc107; color: #212529; transition: background 0.2s, color 0.2s;" onmouseover="this.style.backgroundColor='#e0a800';this.style.borderColor='#d39e00';this.style.color='#212529';" onmouseout="this.style.backgroundColor='#ffc107';this.style.borderColor='#ffc107';this.style.color='#212529';"><i class="bi bi-plus-square"></i> Add New</a>
						</div>
				</div>
			
				<div class="table-responsive">
						<table class="table table-bordered" id="sortTable">
							<thead>
								<tr>
									<th data-name="feature">Feature</th>
									<th data-name="chartType">Chart Type</th>
									<th data-name="chartTypes">Allowed Chart Types</th>
									<th data-name="xField">X</th>
									<th data-name="yField">Y</th>
									
									<th data-editable='false' data-action='true'>Actions</th>
								</tr>
							</thead>

							<tbody> <?php foreach($plotlyDefaults['layerDefaults'] as $feature => $layerDefaults) {
							    $charTypes = $plotlyDefaults['chartTypes'][$feature];
							?>
								<tr data-feature="<?=$feature?>" data-default="<?=($feature == $def_feature) ? '1' : '0'?>" align="left">
									<td><?=$feature?></td>
									<td><?=$layerDefaults['chartType']?></td>
									<td><?=join(',', $charTypes)?></td>
									<td><?=$layerDefaults['xField']?></td>
									<td><?=$layerDefaults['yField']?></td>
									<td>
										<a class="edit" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
										<a class="delete" title="Delete" data-toggle="tooltip"><i class="text-danger bi bi-x-square"></i></a>
									</td>
								</tr> <?php } ?>
							</tbody>
						</table>           
					</div>

					<div class="row">
					    <div class="col-8"><p>&nbsp;</p>

								<div class="alert alert-success">
								   <strong>Note:</strong> Manage your Plotly defaults from here. <a href="https://QCarta.docs.acugis.com/en/latest/sections/plotly/index.html" target="_blank">Documentation</a>
								</div>
							</div>
					</div>
					
					<div id="addnew_modal" class="modal fade" role="dialog">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header">
									<h4 style="color:black!important" class="modal-title">Create Defaults</h4>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								
								<div class="modal-body" id="addnew_modal_body">
									<form id="defaults_form" class="border shadow p-3 rounded"
												action="" method="post" style="width: 450px;">

										<input type="hidden" name="action" value="save"/>
										<input type="hidden" name="id" id="id" value="<?=$_GET['id']?>"/>
										
										<div class="form-group">
											<label for="feature">Feature</label>
											<select class="form-select" id="feature" name="feature">
                                                <?php foreach($feats as $v){ ?>
                                                    <option value="<?=$v?>"><?=$v?></option>
                                                <?php } ?>
                                            </select>
                                            
                                            <input type="checkbox" name="is_default" id="is_default" value="1"/>
                                            <label for="is_default">Default</label>
										</div>

										<div class="form-group">
											<label for="chartType">Chart Type</label>
											<select id="chartType" name="chartType" class="form-select form-select-sm">
                                                <option value="scatter" selected>Scatter</option>
                                                <option value="bar">Bar</option>
                                                <option value="line">Line</option>
                                                <option value="box">Box Plot</option>
                                                <option value="violin">Violin Plot</option>
                                                <option value="histogram">Histogram</option>
                                                <option value="pie">Pie Chart</option>
                                                <option value="sunburst">Sunburst</option>
                                                <option value="treemap">Treemap</option>
                                                <option value="funnel">Funnel</option>
                                                <option value="waterfall">Waterfall</option>
                                                <option value="candlestick">Candlestick</option>
                                                <option value="ohlc">OHLC</option>
                                                <option value="contour">Contour</option>
                                                <option value="heatmap">Heatmap</option>
                                                <option value="surface">3D Surface</option>
                                                <option value="scatter3d">3D Scatter</option>
                                                <option value="mesh3d">3D Mesh</option>
                                            </select>
										</div>
										
										<div class="form-group">
											<label for="chartTypes">Allowed Chart Types</label>
											<select id="chartTypes" name="chartTypes[]" class="form-select form-select-sm" multiple>
                                                <option value="scatter" selected>Scatter</option>
                                                <option value="bar">Bar</option>
                                                <option value="line">Line</option>
                                                <option value="box">Box Plot</option>
                                                <option value="violin">Violin Plot</option>
                                                <option value="histogram">Histogram</option>
                                                <option value="pie">Pie Chart</option>
                                                <option value="sunburst">Sunburst</option>
                                                <option value="treemap">Treemap</option>
                                                <option value="funnel">Funnel</option>
                                                <option value="waterfall">Waterfall</option>
                                                <option value="candlestick">Candlestick</option>
                                                <option value="ohlc">OHLC</option>
                                                <option value="contour">Contour</option>
                                                <option value="heatmap">Heatmap</option>
                                                <option value="surface">3D Surface</option>
                                                <option value="scatter3d">3D Scatter</option>
                                                <option value="mesh3d">3D Mesh</option>
                                            </select>
										</div>
										
										<div class="form-group">
											<label for="xField">X</label>
											<select class="form-select" id="xField" name="xField"></select>
										</div>
										
										<div class="form-group">
											<label for="yField">Y</label>
											<select class="form-select" id="yField" name="yField"></select>
										</div>
									</form>
								</div>
								<div class="modal-footer">
									<button type="button" class="activate btn btn-secondary" id="btn_create" data-dismiss="modal">Create</button>
								</div>
							</div>
						</div>
					</div>
				</div>

			
			</div>
		</div>
</div>
<?php include("incl/footer.php"); ?>
<script>
    var sortTable = new DataTable('#sortTable', { paging: false });
</script>
</body>
</html>
