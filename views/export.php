<style type="text/css">
	.rv-gravity-bulk-export-form label {
		display: block;
		margin-bottom: 1rem;

		font-weight: bold;
	}
	.rv-gravity-bulk-export-form label .label-help {
		display: block;

		color: #606060;
		font-size: 11px;
		font-weight: normal;
	}
</style>

<h2> Gravity forms bulk export </h2>
<p>
	<?php if (is_multisite()):?>
	This tool allows you to export entries from multiple Gravity Forms at once, from across different sites in your network. The CSV files for each form will be bundled in a zip file for you to download.
	<?php else:?>
	This tool allows you to export entries from multiple Gravity Forms at once. The CSV files for each form will be bundled in a zip file for you to download.
	<?php endif;?>
</p>

<form class="rv-gravity-bulk-export-form" action="?action=rv_gravity_bulk_export&amp;stage=<?php echo $stage;?>" method="POST">

	<ul>
		<?php if ($stage == 1):?>
		<li>
			<label for="gf-bulk-sites">
				Sites from which to export
			</label>
			<select id="gf-bulk-sites" name="gf-bulk-sites[]" multiple>
				<?php foreach ($sites as $site):?>
				<option value="<?php echo $site->blog_id;?>"><?php echo $site->blogname;?></option>	
				<?php endforeach;?>
			</select>
		</li>
		<?php elseif ($stage == 2):?>
		<li>
			<label for="gf-bulk-forms">
				Forms to export
				<span class="label-help">Hold <code>Ctrl</code> or <code>Cmd</code> to select multiple forms. To select all, select the first form, then hit <code>Shift</code> + <code>End</code></span>
			</label>
			<select id="gf-bulk-forms" name="gf-bulk-forms[]" multiple>
				<?php foreach ($forms as $form):?>
				<option value="<?php echo $form->blog_id;?>_<?php echo $form->id;?>"><?php echo $form->title;?></option>
				<?php endforeach;?>
			</select>
		</li>
		<?php elseif ($stage == 3):?>
		<li>
			<input id="gf-bulk-combine" name="gf-bulk-combine" type="checkbox" value="1">
			<label for="gf-bulk-combine">Combine entries into one export file?</label>
		</li>
		<li style="display: none">
			<label for="gf-bulk-fields">Select which fields to export (note: only fields which exist in all selected forms are shown)</label>
			<select id="gf-bulk-fields" name="gf-bulk-fields[]" multiple>
				<?php foreach ($common_labels as $common_label):?>
				<option><?php echo $common_label;?></option>
				<?php endforeach;?>
			</select>
		</li>
		<script type="text/javascript">
			var gfbeCombine = document.getElementById('gf-bulk-combine');
			var gfbeFieldsWrapper = document.getElementById('gf-bulk-fields').parentNode;
			gfbeCombine.addEventListener('change', function () {
				if (gfbeCombine.checked) {
					gfbeFieldsWrapper.style.display = 'list-item';
				} else {
					gfbeFieldsWrapper.style.display = 'none';
				}
			});
		</script>

		<input type="hidden" name="gf-bulk-forms" value="<?php echo $_GET['form_ids'];?>">
		<?php elseif ($stage == 4):?>
		<li>
			<label for="gf-bulk-date-start">Start date <span class="label-help">in <code>dd/mm/yyyy</code> format</span></label>
			<input id="gf-bulk-date-start" name="gf-bulk-date-start" type="date">
		</li>

		<li>
			<label for="gf-bulk-date-end">End date <span class="label-help">in <code>dd/mm/yyyy</code> format</span></label>
			<input id="gf-bulk-date-end" name="gf-bulk-date-end" type="date">
		</li>

		<input type="hidden" name="gf-bulk-fields" value="<?php echo $_GET['fields'];?>">
		<input type="hidden" name="gf-bulk-forms" value="<?php echo $_GET['form_ids'];?>">
		<input type="hidden" name="gf-bulk-combine" value="<?php echo $_GET['combine'];?>">
		<?php endif;?>
	</ul>

	<?php if ($stage < 4):?>
	<button type="submit">Next</button>
	<?php else:?>
	<button type="submit">Export</button>
	<?php endif;?>
</form>
