	<div class="table-responsive">
			<table class="table table-bordered" id="sortTable">
				<thead>
					<tr>
						<!-- <th data-name="id" data-editable='false'>ID</th>-->
						<th data-editable='false' data-name="access_key">Access Key</th>
						<th data-name="valid_until">Valid Until</th>
						<th data-name="allow_from">Allow from</th>
						<th data-editable='false' data-action='true'>Actions</th>
					</tr>
				</thead>

				<tbody> <?php while($row = pg_fetch_assoc($rows)) {
					if($row['ip_restricted']){
						list($ips, $err) = $database->select1('addr', 'FROM access_key_ips WHERE access_key_id='.$row['id']);
						$row['allow_from'] = implode('<br>', $ips);
					}else{
						$row['allow_from'] = '';
					}
				?>
					<tr data-id="<?=$row['id']?>" align="left">
						<!--<td><?=$row['id']?> </td>-->
						<td><?=$row['access_key']?></td>
						<td><?=$row['valid_until']?></td>
						<td><?=$row['allow_from']?></td>
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
					   <strong>Note:</strong> Manage access groups from here. Server time is <?=date('Y-m-d H:i:s')?> <a href="https://quail.docs.acugis.com/en/latest/sections/keys/index.html" target="_blank"> Documentation</a>

					</div>
				</div>
		</div>
		
		<div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Create Key</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body" id="addnew_modal_body">
						<form id="key_form" class="border shadow p-3 rounded"
									action=""
									method="post"
									style="width: 450px;">

							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<div class="form-group">
								<label for="name">Valid Until:</label>
								<input type="datetime-local" class="form-control" id="valid_until" placeholder="Enter date" name="valid_until" value="<?=date("Y-m-d\TH:i:s")?>" min="<?=date("Y-m-d\TH:i:s")?>" required>

								<label for="name">Allow from:</label>
								<input type="text" class="form-control" id="allow_from" placeholder="IP/domain access list" name="allow_from">
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