<?php
if (!isset($taxonomy_topics)) {
	$taxonomy_topics = [];
}
if (!isset($taxonomy_gemets)) {
	$taxonomy_gemets = [];
}
if (!isset($taxonomy_selected_topic_ids)) {
	$taxonomy_selected_topic_ids = [];
}
if (!isset($taxonomy_selected_gemet_ids)) {
	$taxonomy_selected_gemet_ids = [];
}
?>
					<div class="row mb-4">
						<div class="col-12">
							<h6 class="text-primary mb-3 border-bottom pb-2">
								<i class="bi bi-bookmarks me-2"></i>Topics &amp; Keywords (GEMET)
							</h6>
						</div>
						<div class="col-md-6 mb-3">
							<label for="topic_id" class="form-label fw-semibold">
								<i class="bi bi-folder2-open me-1"></i>Topics
							</label>
							<select name="topic_id[]" id="topic_id" class="form-select" multiple style="min-height: 100px;">
								<?php foreach ($taxonomy_topics as $t) {
									$tid = (int) $t['id'];
									$tname = htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8');
									$tsel = in_array($tid, $taxonomy_selected_topic_ids, true) ? ' selected' : '';
									echo '<option value="'.$tid.'"'.$tsel.'>'.$tname."</option>\n";
								} ?>
							</select>
							<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple topics</small>
						</div>
						<div class="col-md-6 mb-3">
							<label for="gemet_id" class="form-label fw-semibold">
								<i class="bi bi-tags me-1"></i>Keywords (GEMET themes)
							</label>
							<select name="gemet_id[]" id="gemet_id" class="form-select" multiple style="min-height: 100px;">
								<?php foreach ($taxonomy_gemets as $g) {
									$gid = (int) $g['id'];
									$gname = htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8');
									$gsel = in_array($gid, $taxonomy_selected_gemet_ids, true) ? ' selected' : '';
									echo '<option value="'.$gid.'"'.$gsel.'>'.$gname."</option>\n";
								} ?>
							</select>
							<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple keywords</small>
						</div>
					</div>
