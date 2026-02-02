	<div class="table-responsive">
			<table class="table table-bordered" id="sortTable">
				<thead>
					<tr>
						<th data-name="id" data-editable='false'>ID</th>
						<th data-name="name">Name</th>
						<th data-editable='false' data-action='true'>Actions</th>
					</tr>
				</thead>

				<tbody> <?php while($user = pg_fetch_assoc($rows)) { ?>
					<tr data-id="<?=$user['id']?>" align="left">
						<td><?=$user['id']?> </td>
						<td><?=$user['name']?></td>
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
					   <strong>Note:</strong> Manage access groups from here.<a href="https://QCarta.docs.acugis.com/en/latest/sections/usergroups/index.html" target="_blank">Documentation</a>

					</div>
				</div>
		</div>
		
		<div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header bg-primary text-white">
						<h4 class="modal-title mb-0">
							<i class="bi bi-plus-circle me-2"></i>Create New Access Group
						</h4>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body p-4" id="addnew_modal_body">
						<form id="group_form" action="" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<!-- Basic Information Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-info-circle me-2"></i>Group Information
									</h6>
								</div>
								<div class="col-12 mb-3">
									<label for="name" class="form-label fw-semibold">
										<i class="bi bi-tag me-1"></i>Group Name
									</label>
									<input type="text" class="form-control" id="name" name="name" required 
										   placeholder="Enter group name"/>
									<small class="form-text text-muted">Choose a descriptive name for this access group</small>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer bg-light border-top">
						<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
							<i class="bi bi-x-circle me-1"></i>Cancel
						</button>
						<button type="button" class="btn btn-primary activate" id="btn_create">
							<i class="bi bi-check-circle me-1"></i>Create Group
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
