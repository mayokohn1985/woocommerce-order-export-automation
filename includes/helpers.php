<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('wooflow_is_woocommerce_active')) {
	function wooflow_is_woocommerce_active() {
		return class_exists('WooCommerce');
	}
}

if (!function_exists('wooflow_admin_notice_missing_wc')) {
	function wooflow_admin_notice_missing_wc() {
		if (!current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>WooFlow Exporter:</strong> WooCommerce is not active.</p></div>';
	}
}

if (!function_exists('wooflow_csv_text')) {
	function wooflow_csv_text($value) {
		$value = (string) $value;
		return '="' . str_replace('"', '""', $value) . '"';
	}
}

if (!function_exists('wooflow_get_export_dir')) {
	function wooflow_get_export_dir() {
		return trailingslashit(WP_CONTENT_DIR) . 'wooflow-private';
	}
}

