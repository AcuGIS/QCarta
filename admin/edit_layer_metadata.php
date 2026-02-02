<?php
    session_start(['read_and_close' => true]);
    require('incl/const.php');
    require('class/database.php');
   	require('class/table.php');
	require('class/table_ext.php');
	require('class/layer.php');
    require('class/qgs_layer.php');
    require('class/layer_metadata.php');
    
    if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit;
    }
    
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$id = empty($_GET['id']) ? 0 : intval($_GET['id']);
	
	$lm = ['id' => 0, 'layer_id' => 0, 'title' =>'', 'abstract' => '', 'purpose' => '', 'keywords' => '', 'language' => '', 'character_set' => '', 'maintenance_frequency' => '012', 'cit_date' => '',  'cit_responsible_org' => '', 'cit_responsible_person' => '',
        'cit_role' => '', 'west' => '', 'east' => '', 'south' => '', 'north' => '', 'start_date' => '', 'end_date' => '', 'coordinate_system' => '',
        'spatial_resolution' => 0, 'lineage' => '', 'scope' => '', 'conformity_result' => '',
        'metadata_organization' => '', 'metadata_email' => '', 'metadata_role' => '',
        'use_constraints' => '', 'use_limitation' => '', 'access_constraints' => '', 'inspire_point_of_contact' => '', 'inspire_conformity' => '', 'spatial_data_service_url' => '',
       'distribution_url' => '', 'data_format' => '', 'coupled_resource' => ''
    ];

	if($id > 0){
    	$obj = new layer_metadata_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
        $lqgs_obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
        
    	if(($id <= 0) && !$$lqgs_obj->isOwnedByUs($id)){
    		http_response_code(405);	//not allowed
    		die(405);
    	}
    	$result = $obj->getByLayerId($id);
        if($result && (pg_num_rows($result) == 1)){
            $lm = pg_fetch_assoc($result);
            pg_free_result($result);
        }else{
            $lm['layer_id'] = $id;
        }
	}
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" >

<head>
	<title>QCarta</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
		
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">
	<script src="assets/dist/js/edit_layer_metadata.js"></script>
</head>
<body>
  
	<div id="container">
		
		<?php const NAV_SEL = 'Layers'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
		<div id="content">
		    <h1>Edit ISO 19115 + INSPIRE Metadata for layer <?=$_GET['id']?></h1>
			<div style="width: 99%">
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
					<div class="row align-items-center">
						<div class="col-6">
							<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>
						</div>
					</div>
				</div>

				<form id="metadata_form" class="border shadow p-3 rounded"
    				action=""
    				method="post"
    				style="width: 450px;">
                    
                    <input type="hidden" name="action" value="save"/>
                    <input type="hidden" name="id" value="<?=$lm['id']?>" />
                    <input type="hidden" name="layer_id" value="<?=$lm['layer_id']?>" />
                    
                    <fieldset>
                        <legend>Identification Info</legend>
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input class="form-control" type="text" id="title" name="title" required placeholder="Dataset Title" value="<?=$lm['title']?>" />
                        </div>
                        
                        <div class="form-group">
                            <label for="abstract">Abstract *</label>
                            <textarea class="form-control" id="abstract" name="abstract" required placeholder="Description of dataset"><?=$lm['abstract']?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" placeholder="Purpose of dataset"><?=$lm['purpose']?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="keywords">Keywords (comma separated)</label>
                            <input class="form-control" type="text" id="keywords" name="keywords" placeholder="keyword1, keyword2" value="<?=$lm['keywords']?>"/>
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" class="form-control" id="language" name="language" placeholder="Language of dataset" value="<?=$lm['language']?>"/>
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Character Set</label>
                            <input type="text" class="form-control" id="character_set" name="character_set" placeholder="Character Set" value="<?=$lm['character_set']?>"/>
                        </div>
                        
                        <div class="form-group">
                            <label for="maintenance_frequency">Maintenance Frequency</label>
                            <select class="form-control" id="maintenance_frequency" name="maintenance_frequency" placeholder="Maintenance Frequency">
                                <?php foreach(FREQUENCY_TABLE as $k => $v){ ?>
                                    <option value="<?=$k?>" <?php if($k == $lm['maintenance_frequency']) { echo 'selected';} ?> ><?=$v?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Citation</legend>
                        
                        <div class="form-group">
                            <label for="cit_date">Citation Date *</label>
                            <input class="form-control" type="date" id="cit_date" name="cit_date" required value="<?=$lm['cit_date']?>"/>
                        </div>

                        <div class="form-group">
                            <label for="cit_responsible_org">Responsible Organization *</label>
                            <input class="form-control" type="text" id="cit_responsible_org" name="cit_responsible_org" required placeholder="Organization name" value="<?=$lm['cit_responsible_org']?>"/>
                        </div>
                    
                        <div class="form-group">
                            <label for="cit_responsible_person">Responsible Person</label>
                            <input class="form-control" type="text" id="cit_responsible_person" name="cit_responsible_person" placeholder="Name" value="<?=$lm['cit_responsible_person']?>"/>
                        </div>
                        
                        <div class="form-group">
                            <label for="cit_role">Role</label>
                            <select class="form-select" id="cit_role" name="cit_role">
                                <?php foreach(CIT_ROLES as $k => $v){ ?>
                                    <option value="<?=$k?>" <?php if($lm['cit_role'] == $k) {echo 'selected'; } ?> ><?=$v?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Spatial Information</legend>
                        
                        <div class="form-group">
                            <label>West Longitude *</label>
                            <input class="form-control" type="number" id="west" name="west" step="any" required placeholder="West Bound" value="<?=$lm['west']?>"/>
                            <label>East Longitude *</label>
                            <input class="form-control" type="number" id="east" name="east" step="any" required placeholder="East Bound" value="<?=$lm['east']?>"/>
                            <label>South Latitude *</label>
                            <input class="form-control" type="number" id="south" name="south" step="any" required placeholder="South Bound" value="<?=$lm['south']?>"/>
                            <label>North Latitude *</label>
                            <input class="form-control" type="number" id="north" name="north" step="any" required placeholder="North Bound" value="<?=$lm['north']?>"/>
                            <label for="coordinate_system">Coordinate System (EPSG Code)</label>
                            <input class="form-control" type="text" id="coordinate_system" name="coordinate_system" placeholder="e.g. 4326" value="<?=$lm['coordinate_system']?>"/>
                            <label for="spatial_resolution">Spatial Resolution</label>
                            <input class="form-control" type="number" id="spatial_resolution" name="spatial_resolution" step="any" required placeholder="Spatial Resolution" value="<?=$lm['spatial_resolution']?>"/>
                        </div>    
                    </fieldset>

                    
                    <fieldset>
                        <legend>Temporal Information</legend>
                        
                        <div class="form-group">
                            <label>Start Date</label>
                            <input class="form-control" type="date" id="start_date" name="start_date" value="<?=$lm['start_date']?>"/>
                            <label>End Date</label>
                            <input class="form-control" type="date" id="end_date" name="end_date" value="<?=$lm['end_date']?>"/>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Responsible Parties</legend>
                        
                        <div class="form-group">
                            <label>Metadata Organization</label>
                            <input class="form-control" type="text" id="metadata_organization" name="metadata_organization" value="<?=$lm['metadata_organization']?>"/>
                            <label>Metadata Email</label>
                            <input class="form-control" type="text" id="metadata_email" name="metadata_email" value="<?=$lm['metadata_email']?>"/>
                            <label>Metadata Role</label>
                            <input class="form-control" type="text" id="metadata_role" name="metadata_role" value="<?=$lm['metadata_role']?>"/>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Data Quality</legend>
                        
                        <div class="form-group">
                            <label>Lineage</label>
                            <input class="form-control" type="text" id="lineage" name="lineage" value="<?=$lm['lineage']?>"/>
                            <label>Scope</label>
                            <input class="form-control" type="text" id="scope" name="scope" value="<?=$lm['scope']?>"/>
                            <label>Conformity Result</label>
                            <input class="form-control" type="text" id="conformity_result" name="conformity_result" value="<?=$lm['conformity_result']?>"/>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Constraints</legend>
                        <div class="form-group">
                            <label for="use_constraints">Use Constraints</label>
                            <textarea class="form-control" id="use_constraints" name="use_constraints" placeholder="Use constraints"><?=$lm['use_constraints']?></textarea>
                            <label for="use_limitation">Use Limitation</label>
                            <textarea class="form-control" id="use_limitation" name="use_limitation" placeholder="Use limitation"><?=$lm['use_limitation']?></textarea>
                            <label for="access_constraints">Access Constraints</label>
                            <textarea class="form-control" id="access_constraints" name="access_constraints" placeholder="Access constraints"><?=$lm['access_constraints']?></textarea>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>INSPIRE Metadata</legend>
                        <div class="form-group">
                            <label for="inspire_point_of_contact">INSPIRE Point of Contact Organization</label>
                            <input class="form-control" type="text" id="inspire_point_of_contact" name="inspire_point_of_contact" placeholder="Organization name" value="<?=$lm['inspire_point_of_contact']?>"/>
                        </div>

                        <div class="form-group">
                            <label for="inspire_conformity">Conformity Result</label>
                            <select class="form-select" id="inspire_conformity" name="inspire_conformity">
                                <?php foreach(INSPIRE_CONFORMITY as $k => $v){ ?>
                                    <option value="<?=$k?>" <?php if($lm['inspire_conformity'] == $k) {echo 'selected'; } ?> ><?=$v?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="spatial_data_service_url">Spatial Data Service URL</label>
                            <input class="form-control" type="url" id="spatial_data_service_url" name="spatial_data_service_url" placeholder="https://example.com/service" value="<?=$lm['spatial_data_service_url']?>"/>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend>Distribution</legend>
                        <div class="form-group">
                            <label for="distri">Distribution URL</label>
                            <textarea class="form-control" id="distribution_url" name="distribution_url" placeholder="Distribution URL"><?=$lm['distribution_url']?></textarea>
                            <label for="data_format">Data Format(s)</label>
                            <textarea class="form-control" id="data_format" name="data_format" placeholder="Data formats"><?=$lm['data_format']?></textarea>
                            <label for="coupled_resource">Coupled Resource</label>
                            <textarea class="form-control" id="coupled_resource" name="coupled_resource" placeholder="Coupled Resource"><?=$lm['coupled_resource']?></textarea>
                        </div>
                    </fieldset>

                    <button type="button" class="btn btn-primary" id="btn_update">Update</button>
                </form>
			</div>
		</div>
</div>
<?php include("incl/footer.php"); ?>
</body>
</html>
