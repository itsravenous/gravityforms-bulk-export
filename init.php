<?php
/**
 * Plugin name: Gravity Forms Bulk Export
 */

$plugdir = dirname(__FILE__) . '/';

require_once($plugdir.'inc/export-class.php');

// Connect to DB
mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
mysql_select_db(DB_NAME);

class rv_gravity_bulk_export {

	/**
	 * Creates the plugin's menu entry
	 */
	public static function create_menu() {
		add_menu_page('Gravity Bulk Export', 'Gravity Bulk Export', 'gform_full_access', 'gravity-bulk-export', array('rv_gravity_bulk_export', 'render_export_page'));
	}

	/**
	 * Processes form submission and routes accordingly
	 */
	public static function process_form() {
		$stage = $_GET['stage'];
		if ($stage == 2) {
			$forms = implode(',', $_POST['gf-bulk-forms']);
			wp_redirect(admin_url('admin.php?page=gravity-bulk-export&stage=3&form_ids='.$forms));
		} elseif ($stage == 1) {
			$sites = implode(',', $_POST['gf-bulk-sites']);
			wp_redirect(admin_url('admin.php?page=gravity-bulk-export&stage=2&site_ids='.$sites));
		} elseif ($stage == 3) {
			$form_ids = $_POST['gf-bulk-forms'];
			$combine = !empty($_POST['gf-bulk-combine']) ? 1 : 0;
			$fields = implode(',', array_map(function ($field) {
				return urlencode($field);
			}, $_POST['gf-bulk-fields']));
			wp_redirect(admin_url('admin.php?page=gravity-bulk-export&stage=4&form_ids='.$form_ids.'&combine='.$combine.'&fields='.$fields));
		} elseif ($stage == 4) {
			self::do_export();
		}
	}

	/**
	 * Calls the appropriate render function based on the current stage
	 */
	public static function render_export_page() {
		global $plugdir;

		if (is_multisite()) {
			// Multisite flow
			if (!empty($_GET['fields']) || $_GET['stage'] == 4) {
				self::stage4();
			}
			// Stage 3
			elseif (!empty($_GET['form_ids'])) {
				self::stage3();
			}
			// Stage 2
			elseif (!empty($_GET['site_ids'])) {
				self::stage2();
			// Stage 1
			} else {
				self::stage1();
			}
		} else {
			// Normal flow
			// Stage 4
			if (!empty($_GET['fields']) || $_GET['stage'] == 4) {
				self::stage4();
			}
			// Stage 3
			elseif (!empty($_POST['gf-bulk-forms'])) {
				self::stage3();
			// Stage 2
			} else {
				self::stage2();
			}
		}
	}

	/**
	 * Stage 1 - selecting sites (multisite installations only)
	 */
	private static function stage1 () {
		global $plugdir;
		$sites = wp_get_sites();
		$sites = array_map(function ($site) {
			return (object) get_blog_details($site['blog_id'], $getall = TRUE);
		}, $sites);

		$stage = 1;
		include($plugdir.'views/export.php');
	}

	/**
	 * Stage 2 - selecting forms
	 */
	private static function stage2 () {
		if (is_multisite()) {
			// Get forms from sites selected
			$sites = explode(',', $_GET['site_ids']);
			$forms = array();
			foreach ($sites as $site_id) {
				switch_to_blog($site_id);
				// Mark form with site ID
				$site_forms = array_map(function ($form) use ($site_id) {
					$form->blog_id = $site_id;
					return $form;
				}, rv_gravity::get_forms());
				$forms = array_merge($forms, $site_forms);
				restore_current_blog();
			}
		} else {
			$forms = rv_gravity::get_forms();
		}
		
		$stage = 2;
		include($plugdir.'views/export.php');
	}

	/**
	 * Stage 3 - combine option and selecting fields
	 */
	private static function stage3 () {
		$stage = 3;
		
		$forms = explode(',', $_GET['form_ids']);

		// Get fields in common
		$common_labels = rv_gravity::get_common_field_labels($forms);

		include($plugdir.'views/export.php');
	}

	/**
	 * Stage 4 - date range selection
	 */
	private static function stage4 () {
		global $plugdir;

		$stage = 4;

		include($plugdir.'views/export.php');
	}

	/**
	 * Does the actual export
	 */
	public static function do_export() {
		global $plugdir;

		// Get dates
		$date_start = !empty($_POST['gf-bulk-date-start']) ? $_POST['gf-bulk-date-start'] : FALSE;
		$date_end = !empty($_POST['gf-bulk-date-end']) ? $_POST['gf-bulk-date-end'] : FALSE;

		// Validate and format dates
		$date_regex = '/^(0[1-9]|[12][0-9]|3[01])[\/](0[1-9]|1[012])[\/](19|20)\d\d$/';
		if ($date_start && !preg_match($date_regex, $date_start)) {
			wp_die('Invalid start date.');
		} elseif($date_start) {
			$date_start = date('Y-m-d', strtotime(str_replace('/', '-', $date_start)));
		}
		if ($date_end && !preg_match($date_regex, $date_end)) {
			wp_die('Invalid end date.');
		} elseif($date_end) {
			$date_end = date('Y-m-d', strtotime(str_replace('/', '-', $date_end)));
		}

		$form_ids = explode(',', $_POST['gf-bulk-forms']);
		$fields = !empty($_POST['gf-bulk-fields']) ? explode(',', $_POST['gf-bulk-fields']) : FALSE;
		$combine = $_POST['gf-bulk-combine'] == 1;

		$exporter = new rv_gravity_export(array(
			'db' => array(
				'host' => DB_HOST,
				'user' => DB_USER,
				'password' => DB_PASSWORD,
				'name' => DB_NAME,
			),
		));

		// Create timestamp for export files
		$timestamp = date('Y-m-d-H-i-s');

		// Open zip archive
		$zipfile = $plugdir.'export/gravity-bulk-export-'.$timestamp.'.zip';
		$zip = new ZipArchive();
		if ($zip->open($zipfile, ZipArchive::CREATE) !== TRUE) {
			wp_die("cannot open zipfile");
		}

		// Loop over forms and export to zip
		$csv_files = array();
		foreach ($form_ids as $form_id) {

			// Multisite or not?
			if (is_multisite()) {
				// Yes, split site ID and form ID
				$id_parts = explode('_', $form_id);
				$blog_id = $id_parts[0];
				$form_id = $id_parts[1];

				// Switch to correct multisite
				switch_to_blog($blog_id);
			} else {
				// No, use 1 as blog ID
				$blog_id = 1;
			}

			// Get all forms
			$forms = rv_gravity::get_forms();

			// Build export filename
			$form = array_values(array_filter($forms, function ($form) use ($form_id) {
				return $form->id == $form_id;
			}));
			$form = $form[0];
			$filename = $plugdir.'export/export-'.$form->title.'-'.$blog_id.'-'.$timestamp.'.csv';

			// Export form entries to CSV file
			$exporter->export_entries(array(
				'form_id' => $form_id,
				'date_from' => $date_start,
				'date_to' => $date_end,
				'out' => $filename,
				'fields' => $fields,
			));

			$csv_files[] = $filename;

			restore_current_blog();
		}

		if ($combine) {
			$combined_csv = '';
			foreach ($csv_files as $csv_file) {
				$combined_csv .= "\n" . file_get_contents($csv_file);
			}
			$combined_filename = $plugdir.'export/export-'.$timestamp.'.csv';
			file_put_contents($combined_filename, $combined_csv);
			$zip->addFile($combined_filename, basename($combined_filename));
		} else {
			foreach ($csv_files as $csv_file) {
				$zip->addFile($csv_file, basename($csv_file));
			}
		}

		$zip->close();

		header('Content-type: application/zip');
		header('Content-Disposition: attachment; filename="'.basename($zipfile).'"');
		die(file_get_contents($zipfile));
	}
}

add_action('admin_menu', array('rv_gravity_bulk_export', 'create_menu'));
add_action('admin_action_rv_gravity_bulk_export', array('rv_gravity_bulk_export', 'process_form'));

?>