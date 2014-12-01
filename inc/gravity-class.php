<?php
/**
 * @file Gravity forms helper/model class
 * @author Tom Jenkins tom@itsravenous.com
 */

Class rv_gravity {
	public static function get_forms() {
		global $wpdb;
		$forms = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."rg_form");

		return $forms;
	}

	public static function get_form($form_id) {
		global $wpdb;
		$result = mysql_query("SELECT * FROM ".$wpdb->prefix."rg_form WHERE id='$form_id' LIMIT 1");
		if ($result) {
			$form = mysql_fetch_object($result);
		} else {
			die("Couldn't find form with id $form_id");
		}

		return $form;
	}

	public static function get_form_meta($form_id) {
		global $wpdb;
		$result = mysql_query("SELECT display_meta FROM ".$wpdb->prefix."rg_form_meta WHERE form_id='$form_id' LIMIT 1");

		$row = mysql_fetch_object($result);

		// First try unserializing data
		$meta = unserialize($row->display_meta);
		// If not, might be stored as JSON
		if (!$meta) {
			$meta = (array) JSON_decode($row->display_meta);
		}
		return $meta;
	}

	public static function get_form_fields($form_id) {
		$meta = self::get_form_meta($form_id);
		return $meta['fields'];
	}

	public static function get_entry_detail($entry_id) {
		global $wpdb;
		$result = mysql_query("SELECT * FROM ".$wpdb->prefix."rg_lead_detail WHERE lead_id=$entry_id");

		$values = array();
		while ($row = mysql_fetch_object($result)) {
			$values[] = $row;
		}

		return $values;
	}

	public static function get_form_labels_by_id($form_id) {
		// Get fields
		$fields = self::get_form_fields($form_id);

		$labels_by_id = array();
		foreach($fields as $field) {
			$field = (array) $field; // When form meta stored as JSON, fields wil be objects not arrays
			// Skip separator fields
			if ($field['type'] == 'section' || $field['type'] == 'page') continue;

			if ($field['inputs']) {
				foreach ($field['inputs'] as $input) {
					$input = (array) $input;
					if ($field['type'] == 'checkbox') {
						$labels_by_id[(string) $input['id']] = $input['label'];
					} else {
						$labels_by_id[(string) $input['id']] = $field['label'] . ' (' . $input['label'] . ')';
					}
				}
			} else {
				$labels_by_id[(string) $field['id']] = $field['label'];
			}
		}

		return $labels_by_id;
	}

	public static function get_form_entries($form_id, $date_from = FALSE, $date_to = FALSE) {
		global $wpdb;

		// Get labels keyed by ID
		$labels_by_id = self::get_form_labels_by_id($form_id);

		// Build query
		if ($date_from && $date_to) {
			$query = "SELECT * from ".$wpdb->prefix."rg_lead WHERE form_id=$form_id AND date_created > '$date_from' AND date_created < '$date_to'";
		} elseif ($date_from) {
			$query = "SELECT * from ".$wpdb->prefix."rg_lead WHERE form_id=$form_id AND date_created > '$date_from'";
		} elseif ($date_to) {
			$query = "SELECT * from ".$wpdb->prefix."rg_lead WHERE form_id=$form_id AND date_created < '$date_to'";
		} else {
			$query = "SELECT * from ".$wpdb->prefix."rg_lead WHERE form_id=$form_id";
		}

		// Get entries
		$result = mysql_query($query);
		$entries = array();
		while ($row = mysql_fetch_object($result)) {
			$values = self::get_entry_detail($row->id);
			$values = array_map(function ($value) use($labels_by_id) {
				$new_value = new StdClass();
				$new_value->label = $labels_by_id[(string) $value->field_number];
				$new_value->value = $value->value;
				$new_value->field_id = $value->field_number;

				return $new_value;
			}, $values);
			$row->values = $values;
			$entries[] = $row;
		}

		return $entries;
	}

	/**
	 * Returns a list of labels for fields two or more fields have in common
	 * @param {Array} form IDs
	 * @return {Array} field labels
	 */
	public static function get_common_field_labels ($form_ids) {
		$is_multisite = is_multisite();
		$labels = array_map(function ($form_id) use ($is_multisite) {
			if ($is_multisite) {
				$id_parts = explode('_', $form_id);
				$blog_id = $id_parts[0];
				$form_id = $id_parts[1];
				switch_to_blog($blog_id);
			}

			$form = self::get_form($form_id);
			$form->labels = self::get_form_labels_by_id($form_id);
			
			if ($is_multisite) {	
				restore_current_blog();
			}

			return $form->labels;
		}, $form_ids);

		$common_labels = call_user_func_array('array_intersect', $labels);
		return $common_labels;
	}
}

?>