<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('wooflow_schedule_cron_event')) {
	function wooflow_schedule_cron_event() {
		$settings     = wooflow_get_settings();
		$is_scheduled = wp_next_scheduled('wooflow_daily_export_event');

		if ($settings['enable_cron'] === 'yes' && !$is_scheduled) {
			wp_schedule_event(time() + 60, 'daily', 'wooflow_daily_export_event');
		}

		if ($settings['enable_cron'] !== 'yes' && $is_scheduled) {
			wp_unschedule_event($is_scheduled, 'wooflow_daily_export_event');
		}
	}
}

if (!function_exists('wooflow_maybe_sync_cron_event')) {
	function wooflow_maybe_sync_cron_event() {
		wooflow_schedule_cron_event();
	}
}
add_action('init', 'wooflow_maybe_sync_cron_event');

if (!function_exists('wooflow_run_daily_export')) {
	function wooflow_run_daily_export() {
		wooflow_generate_csv_file();
	}
}
add_action('wooflow_daily_export_event', 'wooflow_run_daily_export');

if (!function_exists('wooflow_deactivate_plugin')) {
	function wooflow_deactivate_plugin() {
		$timestamp = wp_next_scheduled('wooflow_daily_export_event');

		if ($timestamp) {
			wp_unschedule_event($timestamp, 'wooflow_daily_export_event');
		}
	}
}
register_deactivation_hook(WOOFLOW_EXPORTER_FILE, 'wooflow_deactivate_plugin');
