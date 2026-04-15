<?php
/**
 * Plugin Name: WooFlow Exporter
 * Plugin URI: https://github.com/mayokohn1985/woocommerce-order-export-automation
 * Description: Export WooCommerce orders to CSV manually or automatically via cron.
 * Version: 0.5.0
 * Author: Marián Kohn
 * Author URI: https://mayokohn.com
 * License: GPL2+
 * Text Domain: wooflow-exporter
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WOOFLOW_EXPORTER_FILE', __FILE__);
define('WOOFLOW_EXPORTER_PATH', plugin_dir_path(__FILE__));
define('WOOFLOW_EXPORTER_URL', plugin_dir_url(__FILE__));

require_once WOOFLOW_EXPORTER_PATH . 'includes/helpers.php';

if (!wooflow_is_woocommerce_active()) {
	add_action('admin_notices', 'wooflow_admin_notice_missing_wc');
	return;
}

require_once WOOFLOW_EXPORTER_PATH . 'includes/settings.php';
require_once WOOFLOW_EXPORTER_PATH . 'includes/export.php';
require_once WOOFLOW_EXPORTER_PATH . 'includes/cron.php';
require_once WOOFLOW_EXPORTER_PATH . 'includes/admin.php';
