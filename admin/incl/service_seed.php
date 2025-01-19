<div class="table-responsive">
	<table class="table table-bordered" id="sortTable">

		<thead>
			<tr>
				<th data-name="name">Cache</th>
				<th data-name="enabled">Progress</th>
				<th data-name="active">Active</th>
				<th data-name="pid">PID</th>
				<th data-name="cpu">CPU</th>
				<th data-editable='false' data-action='true'>Actions</th>
			</tr>
		</thead>

		<tbody> <?php while($row = pg_fetch_object($rows)) {
			$svc = 'mapproxy-seed@'.$row->id;
			$status = $bknd->service_status($svc);
			list($prog_zoom, $prog_perc) = mapproxy_Class::mapproxy_seed_progress(DATA_DIR.'/layers/'.$row->id.'/seed.log');
			?>
			<tr data-id="<?=$row->id?>" align="left">
				<td><?=$row->name?></td>
				<td>
					<div class="progress-stacked">
						<div class="progress" role="progressbar" aria-label="seed progress" aria-valuenow="<?=intval($prog_perc)?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=intval($prog_perc)?>%">
							<div class="progress-bar text-bg-success"><span title="<?=$prog_perc?>% at Zoom <?=$prog_zoom?>"><?=$prog_perc.'%'?></span></div>
						</div>
					</div>
				</td>
				<?php if(isset($status['active'])) { ?>
				<td><?=$status['active']?></td>
				<?php if(strstr($status['active'], 'running')){ ?>
					<td><?=$status['main pid']?></td>
					<td><?=$status['cpu']?></td>
					<td>
						<a class="stop" 		title="Stop"		data-toggle="tooltip">	<i class="text-danger bi bi-stop-fill"></i></a>
				<?php }else { ?>
					<td></td>
					<td></td>
					<td>
						<a class="start" 	title="Start"		data-toggle="tooltip">	<i class="text-success bi bi-play-fill"></i></a>
				<?php }
					} ?>
						<a class="edit" href="edit_seed.php?id=<?=$row->id?>" title="Edit" data-toggle="tooltip"><i class="text-warning bi bi-pencil-square"></i></a>
					</td>
			</tr> <?php } ?>
		</tbody>
	</table>

    <div class="col-4"><p>&nbsp;</p>

			<div class="alert alert-success">
			   <strong>Note:</strong> Seed Layers. <a href="https://quail.docs.acugis.com/en/latest/sections/mapproxy/index.html#seed-layer" target="_blank"> Documentation</a>
			</div>
		</div>


</div>
