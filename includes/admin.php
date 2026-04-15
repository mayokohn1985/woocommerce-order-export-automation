<?php

if (!defined('ABSPATH')) {
	exit;
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

if (!function_exists('wooflow_handle_save_settings')) {
	function wooflow_handle_save_settings() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		check_admin_referer('wooflow_save_settings_action', 'wooflow_settings_nonce');

		$status      = isset($_POST['wooflow_status']) ? sanitize_text_field(wp_unslash($_POST['wooflow_status'])) : '';
		$date_from   = isset($_POST['wooflow_date_from']) ? sanitize_text_field(wp_unslash($_POST['wooflow_date_from'])) : '';
		$date_to     = isset($_POST['wooflow_date_to']) ? sanitize_text_field(wp_unslash($_POST['wooflow_date_to'])) : '';
		$enable_cron = isset($_POST['wooflow_enable_cron']) ? 'yes' : 'no';

		$allowed_statuses = array_merge([''], array_keys(wc_get_order_statuses()));

		if (!in_array($status, $allowed_statuses, true)) {
			$status = '';
		}

		$date_from = wooflow_validate_date_string($date_from);
		$date_to   = wooflow_validate_date_string($date_to);

		$settings = [
			'status'      => $status,
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'enable_cron' => $enable_cron,
		];

		update_option('wooflow_settings', $settings);
		wooflow_schedule_cron_event();

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

if (!function_exists('wooflow_handle_run_export_now')) {
	function wooflow_handle_run_export_now() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		check_admin_referer('wooflow_run_export_now_action', 'wooflow_run_export_now_nonce');

		$file_path = wooflow_generate_csv_file();

		$redirect_args = [
			'page' => 'wooflow-exporter',
		];

		$redirect_args['message'] = $file_path ? 'generated' : 'generate_failed';

		$redirect_url = add_query_arg(
			$redirect_args,
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}
}
add_action('admin_post_wooflow_run_export_now', 'wooflow_handle_run_export_now');

if (!function_exists('wooflow_handle_export_orders')) {
	function wooflow_handle_export_orders() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		if (
			!isset($_GET['wooflow_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['wooflow_nonce'])),
				'wooflow_export_orders_nonce'
			)
		) {
			wp_die(esc_html__('Invalid nonce.', 'wooflow-exporter'));
		}

		wooflow_stream_csv_download();
	}
}
add_action('admin_post_wooflow_export_orders', 'wooflow_handle_export_orders');

if (!function_exists('wooflow_handle_download_file')) {
	function wooflow_handle_download_file() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		if (
			!isset($_GET['wooflow_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['wooflow_nonce'])),
				'wooflow_download_file_nonce'
			)
		) {
			wp_die(esc_html__('Invalid nonce.', 'wooflow-exporter'));
		}

		if (!isset($_GET['file'])) {
			wp_die(esc_html__('Missing file.', 'wooflow-exporter'));
		}

		$file_name = basename(sanitize_text_field(wp_unslash($_GET['file'])));
		$file_path = trailingslashit(wooflow_get_export_dir()) . $file_name;

		if (!file_exists($file_path) || !is_readable($file_path)) {
			wp_die(esc_html__('File not found.', 'wooflow-exporter'));
		}

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $file_name . '"');
		header('Content-Length: ' . filesize($file_path));
		header('Pragma: no-cache');
		header('Expires: 0');

		readfile($file_path);
		exit;
	}
}
add_action('admin_post_wooflow_download_file', 'wooflow_handle_download_file');

if (!function_exists('wooflow_handle_delete_file')) {
	function wooflow_handle_delete_file() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'wooflow-exporter'));
		}

		if (
			!isset($_GET['wooflow_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['wooflow_nonce'])),
				'wooflow_delete_file_nonce'
			)
		) {
			wp_die(esc_html__('Invalid nonce.', 'wooflow-exporter'));
		}

		if (!isset($_GET['file'])) {
			wp_die(esc_html__('Missing file.', 'wooflow-exporter'));
		}

		$file_name = basename(sanitize_text_field(wp_unslash($_GET['file'])));
		$file_path = trailingslashit(wooflow_get_export_dir()) . $file_name;

		if (file_exists($file_path) && is_file($file_path)) {
			unlink($file_path);
		}

		$redirect_url = add_query_arg(
			[
				'page'    => 'wooflow-exporter',
				'message' => 'deleted',
			],
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}
}
add_action('admin_post_wooflow_delete_file', 'wooflow_handle_delete_file');

if (!function_exists('wooflow_render_admin_page')) {
	function wooflow_render_admin_page() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wooflow-exporter'));
		}

		$settings        = wooflow_get_settings();
		$statuses        = wc_get_order_statuses();
		$save_url        = admin_url('admin-post.php');
		$message         = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';
		$generated_files = wooflow_get_generated_files();

		$export_url = wp_nonce_url(
			admin_url('admin-post.php?action=wooflow_export_orders'),
			'wooflow_export_orders_nonce',
			'wooflow_nonce'
		);

		$run_now_url = wp_nonce_url(
			admin_url('admin-post.php?action=wooflow_run_export_now'),
			'wooflow_run_export_now_action',
			'wooflow_run_export_now_nonce'
		);

		$active_filters = [];

		if (!empty($settings['status']) && isset($statuses[$settings['status']])) {
			$active_filters[] = sprintf(
				esc_html__('Status: %s', 'wooflow-exporter'),
				esc_html($statuses[$settings['status']])
			);
		}

		if (!empty($settings['date_from'])) {
			$active_filters[] = sprintf(
				esc_html__('From: %s', 'wooflow-exporter'),
				esc_html($settings['date_from'])
			);
		}

		if (!empty($settings['date_to'])) {
			$active_filters[] = sprintf(
				esc_html__('To: %s', 'wooflow-exporter'),
				esc_html($settings['date_to'])
			);
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('WooFlow Exporter', 'wooflow-exporter'); ?></h1>

			<?php if ($message === 'saved') : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__('Settings saved.', 'wooflow-exporter'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ($message === 'generated') : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__('CSV file was generated successfully.', 'wooflow-exporter'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ($message === 'generate_failed') : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html__('CSV generation failed. Check folder permissions or debug log.', 'wooflow-exporter'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ($message === 'deleted') : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__('CSV file was deleted successfully.', 'wooflow-exporter'); ?></p>
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

					<tr>
						<th scope="row">
							<label for="wooflow_enable_cron"><?php echo esc_html__('Enable daily cron export', 'wooflow-exporter'); ?></label>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									name="wooflow_enable_cron"
									id="wooflow_enable_cron"
									value="yes"
									<?php checked($settings['enable_cron'], 'yes'); ?>
								>
								<?php echo esc_html__('Generate CSV automatically once per day.', 'wooflow-exporter'); ?>
							</label>
							<p class="description">
								<?php echo esc_html__('Generated files are saved to uploads/wooflow-exports/.', 'wooflow-exporter'); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(__('Save settings', 'wooflow-exporter')); ?>
			</form>

			<hr>

			<h2><?php echo esc_html__('Manual Export', 'wooflow-exporter'); ?></h2>

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
					<?php echo esc_html__('Download CSV Now', 'wooflow-exporter'); ?>
				</a>

				<a href="<?php echo esc_url($run_now_url); ?>" class="button">
					<?php echo esc_html__('Generate CSV File Now', 'wooflow-exporter'); ?>
				</a>
			</p>

			<hr>

			<h2><?php echo esc_html__('Generated Files', 'wooflow-exporter'); ?></h2>

			<?php if (!empty($generated_files)) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__('File', 'wooflow-exporter'); ?></th>
							<th><?php echo esc_html__('Created', 'wooflow-exporter'); ?></th>
							<th><?php echo esc_html__('Size', 'wooflow-exporter'); ?></th>
							<th><?php echo esc_html__('Actions', 'wooflow-exporter'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($generated_files as $file_path) : ?>
							<?php
							$file_name    = basename($file_path);
							$download_url = wp_nonce_url(
								admin_url('admin-post.php?action=wooflow_download_file&file=' . rawurlencode($file_name)),
								'wooflow_download_file_nonce',
								'wooflow_nonce'
							);
							$delete_url = wp_nonce_url(
								admin_url('admin-post.php?action=wooflow_delete_file&file=' . rawurlencode($file_name)),
								'wooflow_delete_file_nonce',
								'wooflow_nonce'
							);
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url($download_url); ?>">
										<?php echo esc_html($file_name); ?>
									</a>
								</td>
								<td><?php echo esc_html(wp_date('Y-m-d H:i:s', filemtime($file_path))); ?></td>
								<td><?php echo esc_html(size_format(filesize($file_path))); ?></td>
								<td>
									<a href="<?php echo esc_url($download_url); ?>" class="button button-small">
										<?php echo esc_html__('Download', 'wooflow-exporter'); ?>
									</a>
									<a
										href="<?php echo esc_url($delete_url); ?>"
										class="button button-small"
										onclick="return confirm('<?php echo esc_js(__('Delete this file?', 'wooflow-exporter')); ?>');"
									>
										<?php echo esc_html__('Delete', 'wooflow-exporter'); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php echo esc_html__('No generated CSV files found yet.', 'wooflow-exporter'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
