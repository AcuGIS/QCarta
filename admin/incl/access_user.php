	<div class="table-responsive">
			<table class="table table-bordered" id="sortTable">
				<thead>
					<tr>
						<th data-name="id" data-editable='false'>ID</th>
						<th data-name="name">Name</th>
						<th data-name="email">Email</th>
						<th data-name="group_id" data-type="select">Access Group</th>
						<th data-name="accesslevel" data-type="select">Access Level</th>
						<th data-editable='false' data-name="secret_key">Secret Key</th>
						<th data-editable='false' data-action='true'>Actions</th>
					</tr>
				</thead>

				<tbody> <?php while($user = pg_fetch_assoc($rows)) {
					$row_grps = $grp_obj->getByKV('user', $user['id']);
					?>
					<tr data-id="<?=$user['id']?>"
					    data-password="<?=$user['password']?>"
					    align="left">
						<td><?=$user['id']?> </td>
						<td><?=$user['name']?></td>
						<td><?=$user['email']?></td>
						<td data-value="<?=implode(',', array_keys($row_grps))?>">
							<?=implode(',', array_values($row_grps))?>
						</td>
						<td data-value="<?=$user['accesslevel']?>"><?=$user['accesslevel']?></td>
						<td><?=$user['secret_key']?></td>
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
					   <strong>Note:</strong> Manage your users from here. <a href="https://QCarta.docs.acugis.com/en/latest/sections/users/index.html" target="_blank">Documentation</a>
					</div>
				</div>
		</div>
		
		<div id="addnew_modal" class="modal fade" role="dialog">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header bg-primary text-white">
						<h4 class="modal-title mb-0">
							<i class="bi bi-plus-circle me-2"></i>Create New User
						</h4>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					
					<div class="modal-body p-4" id="addnew_modal_body">
						<form id="user_form" action="" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="save"/>
							<input type="hidden" name="id" id="id" value="0"/>
							
							<!-- Basic Information Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-info-circle me-2"></i>Basic Information
									</h6>
								</div>
								<div class="col-md-6 mb-3">
									<label for="name" class="form-label fw-semibold">
										<i class="bi bi-person me-1"></i>Full Name
									</label>
									<input type="text" class="form-control" id="name" name="name" required 
										   placeholder="Enter full name"/>
								</div>
								<div class="col-md-6 mb-3">
									<label for="email" class="form-label fw-semibold">
										<i class="bi bi-envelope me-1"></i>Email Address
									</label>
									<input type="email" class="form-control" id="email" name="email" required 
										   placeholder="Enter email address"/>
								</div>
							</div>

							<!-- Security Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-shield-lock me-2"></i>Security Settings
									</h6>
								</div>
								<div class="col-md-6 mb-3">
									<label for="password" class="form-label fw-semibold">
										<i class="bi bi-key me-1"></i>Password
									</label>
									<input type="password" class="form-control" id="password" name="password" required 
										   placeholder="Enter password"/>
									<small class="form-text text-muted">Minimum 8 characters recommended</small>
								</div>
								<div class="col-md-6 mb-3">
									<label for="secret_key" class="form-label fw-semibold">
										<i class="bi bi-key-fill me-1"></i>Secret Key
									</label>
									<div class="input-group">
										<input type="text" class="form-control" id="secret_key" name="secret_key" 
											   placeholder="Auto-generated" disabled/>
										<button type="button" class="btn btn-outline-secondary secret_reset" title="Reset Secret Key">
											<i class="bi bi-arrow-clockwise"></i>
										</button>
									</div>
									<small class="form-text text-muted">Used for API access and authentication</small>
								</div>
							</div>

							<!-- Access Control Section -->
							<div class="row mb-4">
								<div class="col-12">
									<h6 class="text-primary mb-3 border-bottom pb-2">
										<i class="bi bi-gear me-2"></i>Access Control
									</h6>
								</div>
								<div class="col-md-6 mb-3">
									<label for="accesslevel" class="form-label fw-semibold">
										<i class="bi bi-person-badge me-1"></i>Access Level
									</label>
									<select name="accesslevel" id="accesslevel" required class="form-select">
										<option value="User">User</option>
										<option value="Admin">Admin</option>
									</select>
									<small class="form-text text-muted">Admin users have full system access</small>
								</div>
								<div class="col-md-6 mb-3">
									<label for="group_id" class="form-label fw-semibold">
										<i class="bi bi-people me-1"></i>Access Groups
									</label>
									<select name="group_id[]" id="group_id" multiple required class="form-select" style="min-height: 100px;">
										<?php $sel = 'selected';
										foreach($groups as $k => $v){ ?>
											<option value="<?=$k?>" <?=$sel?>><?=$v?></option>
										<?php $sel = ''; } ?>
									</select>
									<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple groups</small>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer bg-light border-top">
						<button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
							<i class="bi bi-x-circle me-1"></i>Cancel
						</button>
						<button type="button" class="btn btn-primary activate" id="btn_create">
							<i class="bi bi-check-circle me-1"></i>Create User
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
