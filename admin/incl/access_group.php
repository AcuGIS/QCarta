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
					   <strong>Note:</strong> Manage access groups from here.<a href="https://quail.docs.acugis.com/en/latest/sections/usergroups/index.html" target="_blank">Documentation</a>

					</div>
				</div>
		</div>
		
		<div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Create Group</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body" id="addnew_modal_body">
						<form id="group_form" class="border shadow p-3 rounded"
									action=""
									method="post"
									enctype="multipart/form-data"
									style="width: 450px;">

							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<div class="form-group">
								<label for="name">Name:</label>
								<input type="text" class="form-control" id="name" placeholder="Enter name" name="name" required>
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
