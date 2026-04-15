<?php
/**
 * Plugin Name: WooCommerce Order Export & Automation
 * Plugin URI: https://yourdomain.com/orderflow
 * Description: Automatically export WooCommerce orders and send them where they need to go. CSV, JSON, API, automation.
 * Version: 0.1.0
 * Author: Marián Kohn
 * Author URI: https://mayokohn.com
 * License: GPL2+
 * Text Domain: wooflow-exporter
 */

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

if (!wooflow_is_woocommerce_active()) {
	add_action('admin_notices', 'wooflow_admin_notice_missing_wc');
	return;
}

if (!function_exists('wooflow_register_admin_menu')) {
	function wooflow_register_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('WooFlow Exporter', 'wooflow-exporter'),
			__('WooFlow Exporter', 'wooflow-exporter'),
			'manage_woocommerce',
			'wooflow-exporter',
			'wooflow_render_admin_page'
		);
	}
}
add_action('admin_menu', 'wooflow_register_admin_menu');

if (!function_exists('wooflow_render_admin_page')) {
	function wooflow_render_admin_page() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wooflow-exporter'));
		}

		$export_url = wp_nonce_url(
			admin_url('admin-post.php?action=wooflow_export_orders'),
			'wooflow_export_orders_nonce',
			'wooflow_nonce'
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('WooFlow Exporter', 'wooflow-exporter'); ?></h1>

			<p><?php echo esc_html__('Export WooCommerce orders to CSV.', 'wooflow-exporter'); ?></p>

			<p>
				<a href="<?php echo esc_url($export_url); ?>" class="button button-primary">
					<?php echo esc_html__('Export Orders CSV', 'wooflow-exporter'); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

if (!function_exists('wooflow_handle_export_orders')) {
	function wooflow_handle_export_orders() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		if (
			!isset($_GET['wooflow_nonce']) ||
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['wooflow_nonce'])), 'wooflow_export_orders_nonce')
		) {
			wp_die(esc_html__('Invalid nonce.', 'wooflow-exporter'));
		}

		$orders = wc_get_orders([
			'limit'   => -1,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => array_keys(wc_get_order_statuses()),
		]);

		$filename = 'wooflow-orders-' . gmdate('Y-m-d-H-i-s') . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Pragma: no-cache');
		header('Expires: 0');

		$output = fopen('php://output', 'w');

		if ($output === false) {
			wp_die(esc_html__('Could not open output stream.', 'wooflow-exporter'));
		}

		// UTF-8 BOM pre Excel.
		fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

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
		]);

		foreach ($orders as $order) {
			if (!$order instanceof WC_Order) {
				continue;
			}

			$items_summary = [];

			foreach ($order->get_items() as $item) {
				$product_name = $item->get_name();
				$quantity     = $item->get_quantity();
				$items_summary[] = $product_name . ' x ' . $quantity;
			}

			$date_created = $order->get_date_created();
			$date_string  = $date_created ? $date_created->date_i18n('Y-m-d H:i:s') : '';

			fputcsv($output, [
				$order->get_id(),
				$order->get_order_number(),
				$date_string,
				$order->get_status(),
				$order->get_currency(),
				$order->get_total(),
				$order->get_payment_method_title(),
				$order->get_billing_first_name(),
				$order->get_billing_last_name(),
				$order->get_billing_email(),
				$order->get_billing_phone(),
				$order->get_billing_company(),
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_postcode(),
				$order->get_billing_country(),
				$order->get_shipping_first_name(),
				$order->get_shipping_last_name(),
				$order->get_shipping_company(),
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
				$order->get_shipping_city(),
				$order->get_shipping_postcode(),
				$order->get_shipping_country(),
				implode(' | ', $items_summary),
			]);
		}

		fclose($output);
		exit;
	}
}
add_action('admin_post_wooflow_export_orders', 'wooflow_handle_export_orders');