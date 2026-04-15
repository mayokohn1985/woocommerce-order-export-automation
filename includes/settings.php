<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('wooflow_get_settings')) {
	function wooflow_get_settings() {
		$defaults = [
			'status'      => '',
			'date_from'   => '',
			'date_to'     => '',
			'enable_cron' => 'no',
		];

		$settings = get_option('wooflow_settings', []);

		if (!is_array($settings)) {
			$settings = [];
		}

		return wp_parse_args($settings, $defaults);
	}
}

if (!function_exists('wooflow_validate_date_string')) {
	function wooflow_validate_date_string($date) {
		if ($date === '') {
			return '';
		}

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
	}
}
