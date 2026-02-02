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
					   <strong>Note:</strong> Manage access groups from here. Server time is <?=date('Y-m-d H:i:s')?> <a href="https://QCarta.docs.acugis.com/en/latest/sections/keys/index.html" target="_blank"> Documentation</a>

					</div>
				</div>
		</div>
		
		<div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header bg-primary text-white">
						<h4 class="modal-title mb-0">
							<i class="bi bi-plus-circle me-2"></i>Create New Access Key
						</h4>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body p-4" id="addnew_modal_body">
						<form id="key_form" action="" method="post">
							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<!-- Key Configuration Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-key me-2"></i>Key Configuration
									</h6>
								</div>
								<div class="col-md-6 mb-3">
									<label for="valid_until" class="form-label fw-semibold">
										<i class="bi bi-calendar-check me-1"></i>Valid Until
									</label>
									<input type="datetime-local" class="form-control" id="valid_until" name="valid_until" 
										   value="<?=date("Y-m-d\TH:i:s")?>" min="<?=date("Y-m-d\TH:i:s")?>" required/>
									<small class="form-text text-muted">Set expiration date and time for this key</small>
								</div>
								<div class="col-md-6 mb-3">
									<label for="allow_from" class="form-label fw-semibold">
										<i class="bi bi-globe me-1"></i>IP/Domain Restrictions
									</label>
									<input type="text" class="form-control" id="allow_from" name="allow_from" 
										   placeholder="192.168.1.1, example.com"/>
									<small class="form-text text-muted">Comma-separated list of allowed IPs or domains (optional)</small>
								</div>
							</div>

							<!-- Security Information Section -->
							<div class="row mb-3">
								<div class="col-12">
									<div class="alert alert-info">
										<i class="bi bi-info-circle me-2"></i>
										<strong>Security Note:</strong> Access keys provide programmatic access to your QCarta system. 
										Make sure to store them securely and set appropriate expiration dates.
									</div>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer bg-light border-top">
						<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
							<i class="bi bi-x-circle me-1"></i>Cancel
						</button>
						<button type="button" class="btn btn-primary activate" id="btn_create">
							<i class="bi bi-check-circle me-1"></i>Create Key
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
