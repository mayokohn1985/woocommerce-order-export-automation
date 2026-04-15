<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('wooflow_get_order_query_args')) {
	function wooflow_get_order_query_args() {
		$settings = wooflow_get_settings();

		$query_args = [
			'limit'   => -1,
			'orderby' => 'date',
			'order'   => 'DESC',
		];

		if (!empty($settings['status'])) {
			$query_args['status'] = [$settings['status']];
		} else {
			$query_args['status'] = array_keys(wc_get_order_statuses());
		}

		if (!empty($settings['date_from']) || !empty($settings['date_to'])) {
			$date_conditions = [];

			if (!empty($settings['date_from'])) {
				$date_conditions[] = '>=' . $settings['date_from'] . ' 00:00:00';
			}

			if (!empty($settings['date_to'])) {
				$date_conditions[] = '<=' . $settings['date_to'] . ' 23:59:59';
			}

			$query_args['date_created'] = $date_conditions;
		}

		return $query_args;
	}
}

if (!function_exists('wooflow_write_csv_header')) {
	function wooflow_write_csv_header($output) {
		fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
		fwrite($output, "sep=;\n");

		fputcsv($output, [
			'Order ID',
			'Order Number',
			'Date Created',
			'Status',
			'Currency',
			'Total',
			'Payment Method',
			'Customer First Name',
			'Customer Last Name',
			'Customer Email',
			'Customer Phone',
			'Billing Company',
			'Billing Address 1',
			'Billing Address 2',
			'Billing City',
			'Billing Postcode',
			'Billing Country',
			'Shipping First Name',
			'Shipping Last Name',
			'Shipping Company',
			'Shipping Address 1',
			'Shipping Address 2',
			'Shipping City',
			'Shipping Postcode',
			'Shipping Country',
			'Items',
		], ';');
	}
}

if (!function_exists('wooflow_write_order_row')) {
	function wooflow_write_order_row($output, $order) {
		if (!$order instanceof WC_Order) {
			return;
		}

		$items_summary = [];

		foreach ($order->get_items() as $item) {
			$product_name    = $item->get_name();
			$quantity        = $item->get_quantity();
			$items_summary[] = $product_name . ' x ' . $quantity;
		}

		$date_created = $order->get_date_created();
		$date_string  = $date_created ? $date_created->date_i18n('Y-m-d H:i:s') : '';

		fputcsv($output, [
			wooflow_csv_text($order->get_id()),
			wooflow_csv_text($order->get_order_number()),
			wooflow_csv_text($date_string),
			$order->get_status(),
			$order->get_currency(),
			$order->get_total(),
			$order->get_payment_method_title(),
			$order->get_billing_first_name(),
			$order->get_billing_last_name(),
			wooflow_csv_text($order->get_billing_email()),
			wooflow_csv_text($order->get_billing_phone()),
			$order->get_billing_company(),
			$order->get_billing_address_1(),
			$order->get_billing_address_2(),
			$order->get_billing_city(),
			wooflow_csv_text($order->get_billing_postcode()),
			$order->get_billing_country(),
			$order->get_shipping_first_name(),
			$order->get_shipping_last_name(),
			$order->get_shipping_company(),
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
			$order->get_shipping_city(),
			wooflow_csv_text($order->get_shipping_postcode()),
			$order->get_shipping_country(),
			implode(' | ', $items_summary),
		], ';');
	}
}

if (!function_exists('wooflow_prepare_export_dir')) {
	function wooflow_prepare_export_dir() {
		$export_dir = wooflow_get_export_dir();

		if (!is_dir($export_dir)) {
			wp_mkdir_p($export_dir);
		}

		if (!is_dir($export_dir)) {
			error_log('WooFlow Exporter: export directory could not be created.');
			return false;
		}

		$htaccess_path = trailingslashit($export_dir) . '.htaccess';

		if (!file_exists($htaccess_path)) {
			$result = file_put_contents($htaccess_path, "Deny from all\n");

			if ($result === false) {
				error_log('WooFlow Exporter: could not create .htaccess in export directory.');
			}
		}

		if (!is_writable($export_dir)) {
			error_log('WooFlow Exporter: export directory is not writable.');
			return false;
		}

		return $export_dir;
	}
}

if (!function_exists('wooflow_generate_csv_file')) {
	function wooflow_generate_csv_file() {
		$orders = wc_get_orders(wooflow_get_order_query_args());

		$export_dir = wooflow_prepare_export_dir();

		if ($export_dir === false) {
			return false;
		}

		$filename  = 'wooflow-orders-' . wp_date('Y-m-d-H-i-s') . '.csv';
		$file_path = trailingslashit($export_dir) . $filename;

		$output = fopen($file_path, 'w');

		if ($output === false) {
			error_log('WooFlow Exporter: could not create CSV file.');
			return false;
		}

		wooflow_write_csv_header($output);

		foreach ($orders as $order) {
			wooflow_write_order_row($output, $order);
		}

		fclose($output);

		return $file_path;
	}
}

if (!function_exists('wooflow_stream_csv_download')) {
	function wooflow_stream_csv_download() {
		$orders = wc_get_orders(wooflow_get_order_query_args());

		$filename = 'wooflow-orders-' . wp_date('Y-m-d-H-i-s') . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Pragma: no-cache');
		header('Expires: 0');

		$output = fopen('php://output', 'w');

		if ($output === false) {
			wp_die(esc_html__('Could not open output stream.', 'wooflow-exporter'));
		}

		wooflow_write_csv_header($output);

		foreach ($orders as $order) {
			wooflow_write_order_row($output, $order);
		}

		fclose($output);
		exit;
	}
}

if (!function_exists('wooflow_get_generated_files')) {
	function wooflow_get_generated_files() {
		$export_dir = wooflow_get_export_dir();

		if (!is_dir($export_dir)) {
			return [];
		}

		$files = glob(trailingslashit($export_dir) . '*.csv');

		if (!is_array($files) || empty($files)) {
			return [];
		}

		usort(
			$files,
			function ($a, $b) {
				return filemtime($b) - filemtime($a);
			}
		);

		return $files;
	}
}
