<div class="table-responsive">
	<table class="table table-bordered" id="sortTable">

		<thead>
			<tr>
				<th data-name="name">Service</th>
				<th data-name="enabled">Enabled</th>
				<th data-name="active">Active</th>
				<th data-name="pid">PID</th>
				<th data-name="cpu">CPU</th>
				<th data-editable='false' data-action='true'>Actions</th>
			</tr>
		</thead>

		<tbody> <?php foreach($rows as $svc){
				$status = $bknd->service_status($svc);
				$enabled = 'disabled';
				if(!empty($status['enabled'])){
					$enabled = (strstr($status['enabled'], 'enabled') !== false) ? 'checked' : '';
				}
			?>
			<tr data-id="<?=$svc?>" align="left">
				<td><?=$svc?></td>
				<td>
					<input type="checkbox" class="disable" <?=$enabled?>/>
				</td>
				<?php if(isset($status['active'])) { ?>
				<td><?=$status['active']?></td>
				<?php if(strstr($status['active'], 'running')){ ?>
					<td><?=$status['main pid']?></td>
					<td><?=$status['cpu']?></td>
					<td>
						<a class="stop" 		title="Stop"		data-toggle="tooltip">	<i class="text-danger bi bi-stop-fill"></i></a>
						<a class="restart"	title="Restart" data-toggle="tooltip">	<i class="text-primary bi bi-bootstrap-reboot"></i></a>
				<?php }else { ?>
					<td></td>
					<td></td>
					<td>
						<a class="start" 	title="Start"		data-toggle="tooltip">	<i class="text-success bi bi-play-fill"></i></a>
				<?php }
					}else{ ?>
						<td>service running in Docker</td>
						<td></td>
						<td></td>
						<td>
					<?php }
				 ?>
						<a class="edit" href="edit_mapproxy.php" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
					</td>
			</tr> <?php } ?>
		</tbody>
	</table>

    <div class="col-4"><p>&nbsp;</p>

			<div class="alert alert-success">
			   <strong>Note:</strong> Create Layers from PostGIS Stores. <a href="https://quail.docs.acugis.com/en/latest/sections/mapproxy/index.html" target="_blank"> Documentation</a>
			</div>
		</div>




</div>