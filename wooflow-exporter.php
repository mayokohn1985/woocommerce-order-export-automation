<?php
/**
 * Plugin Name: WooCommerce Order Export & Automation
 * Plugin URI: https://github.com/mayokohn1985/woocommerce-order-export-automation
 * Description: Automatically export WooCommerce orders and send them where they need to go. CSV, JSON, API, automation.
 * Version: 0.2.0
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

if (!function_exists('wooflow_get_settings')) {
	function wooflow_get_settings() {
		$defaults = [
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
		];

		$settings = get_option('wooflow_settings', []);

		if (!is_array($settings)) {
			$settings = [];
		}

		return wp_parse_args($settings, $defaults);
	}
}

if (!function_exists('wooflow_csv_text')) {
	function wooflow_csv_text($value) {
		$value = (string) $value;
		return '="' . str_replace('"', '""', $value) . '"';
	}
}

if (!function_exists('wooflow_register_admin_menu')) {
	function wooflow_register_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('OrderFlow Exporter', 'wooflow-exporter'),
			__('OrderFlow Exporter', 'wooflow-exporter'),
			'manage_woocommerce',
			'wooflow-exporter',
			'wooflow_render_admin_page'
		);
	}
}
add_action('admin_menu', 'wooflow_register_admin_menu');

if (!function_exists('wooflow_handle_save_settings')) {
	function wooflow_handle_save_settings() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		check_admin_referer('wooflow_save_settings_action', 'wooflow_settings_nonce');

		$status    = isset($_POST['wooflow_status']) ? sanitize_text_field(wp_unslash($_POST['wooflow_status'])) : '';
		$date_from = isset($_POST['wooflow_date_from']) ? sanitize_text_field(wp_unslash($_POST['wooflow_date_from'])) : '';
		$date_to   = isset($_POST['wooflow_date_to']) ? sanitize_text_field(wp_unslash($_POST['wooflow_date_to'])) : '';

		$allowed_statuses = array_merge([''], array_keys(wc_get_order_statuses()));

		if (!in_array($status, $allowed_statuses, true)) {
			$status = '';
		}

		if ($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
			$date_from = '';
		}

		if ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
			$date_to = '';
		}

		$settings = [
			'status'    => $status,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		];

		update_option('wooflow_settings', $settings);

		$redirect_url = add_query_arg(
			[
				'page'    => 'wooflow-exporter',
				'message' => 'saved',
			],
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}
}
add_action('admin_post_wooflow_save_settings', 'wooflow_handle_save_settings');

if (!function_exists('wooflow_render_admin_page')) {
	function wooflow_render_admin_page() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wooflow-exporter'));
		}

		$settings   = wooflow_get_settings();
		$statuses   = wc_get_order_statuses();
		$save_url   = admin_url('admin-post.php');
		$message    = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';
		$export_url = wp_nonce_url(
			admin_url('admin-post.php?action=wooflow_export_orders'),
			'wooflow_export_orders_nonce',
			'wooflow_nonce'
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('OrderFlow Exporter', 'wooflow-exporter'); ?></h1>

			<?php if ($message === 'saved') : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__('Settings saved.', 'wooflow-exporter'); ?></p>
				</div>
			<?php endif; ?>

			<p><?php echo esc_html__('Export WooCommerce orders to CSV using saved filters.', 'wooflow-exporter'); ?></p>

			<h2><?php echo esc_html__('Export Settings', 'wooflow-exporter'); ?></h2>

			<form method="post" action="<?php echo esc_url($save_url); ?>">
				<input type="hidden" name="action" value="wooflow_save_settings">

				<?php wp_nonce_field('wooflow_save_settings_action', 'wooflow_settings_nonce'); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wooflow_status"><?php echo esc_html__('Default order status', 'wooflow-exporter'); ?></label>
						</th>
						<td>
							<select name="wooflow_status" id="wooflow_status">
								<option value=""><?php echo esc_html__('All statuses', 'wooflow-exporter'); ?></option>
								<?php foreach ($statuses as $status_key => $status_label) : ?>
									<option value="<?php echo esc_attr($status_key); ?>" <?php selected($settings['status'], $status_key); ?>>
										<?php echo esc_html($status_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo esc_html__('Optional. Limit export to one order status.', 'wooflow-exporter'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wooflow_date_from"><?php echo esc_html__('Date from', 'wooflow-exporter'); ?></label>
						</th>
						<td>
							<input
								type="date"
								name="wooflow_date_from"
								id="wooflow_date_from"
								value="<?php echo esc_attr($settings['date_from']); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wooflow_date_to"><?php echo esc_html__('Date to', 'wooflow-exporter'); ?></label>
						</th>
						<td>
							<input
								type="date"
								name="wooflow_date_to"
								id="wooflow_date_to"
								value="<?php echo esc_attr($settings['date_to']); ?>"
							>
						</td>
					</tr>
				</table>

				<?php submit_button(__('Save settings', 'wooflow-exporter')); ?>
			</form>

			<hr>

			<h2><?php echo esc_html__('Manual Export', 'wooflow-exporter'); ?></h2>

			<?php
			$active_filters = [];

			if (!empty($settings['status']) && isset($statuses[$settings['status']])) {
				$active_filters[] = sprintf(
					/* translators: %s: order status label */
					esc_html__('Status: %s', 'wooflow-exporter'),
					esc_html($statuses[$settings['status']])
				);
			}

			if (!empty($settings['date_from'])) {
				$active_filters[] = sprintf(
					/* translators: %s: date from */
					esc_html__('From: %s', 'wooflow-exporter'),
					esc_html($settings['date_from'])
				);
			}

			if (!empty($settings['date_to'])) {
				$active_filters[] = sprintf(
					/* translators: %s: date to */
					esc_html__('To: %s', 'wooflow-exporter'),
					esc_html($settings['date_to'])
				);
			}
			?>

			<?php if (!empty($active_filters)) : ?>
				<p>
					<strong><?php echo esc_html__('Active filters:', 'wooflow-exporter'); ?></strong>
					<?php echo esc_html(implode(' | ', $active_filters)); ?>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__('No filters applied. Export will include all orders.', 'wooflow-exporter'); ?></p>
			<?php endif; ?>

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
				$date_conditions[] = '>' . $settings['date_from'] . ' 00:00:00';
			}

			if (!empty($settings['date_to'])) {
				$date_conditions[] = '<' . $settings['date_to'] . ' 23:59:59';
			}

			$query_args['date_created'] = $date_conditions;
		}

		$orders = wc_get_orders($query_args);

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

		foreach ($orders as $order) {
			if (!$order instanceof WC_Order) {
				continue;
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

		fclose($output);
		exit;
	}
}
add_action('admin_post_wooflow_export_orders', 'wooflow_handle_export_orders');
