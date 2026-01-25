<?php

/**
 * Plugin Name: Live Shipping Rates Australia
 * Plugin URI: https://wordpress.org/plugins/live-shipping-rates-australia/
 * Description: Integrate real-time shipping rates into WooCommerce, providing reliable local and international shipping options for Australian businesses.
 * Version: 1.1.1
 * Author: Repon Hossain
 * Author URI: https://workwithrepon.com
 * Text Domain: live-shipping-rates-australia
 * 
 * Requires Plugins: woocommerce
 * Requires at least: 6.2.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 * 
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

define('LIVE_SHIPPING_RATES_AUSTRALIA_FILE', __FILE__);
define('LIVE_SHIPPING_RATES_AUSTRALIA_VERSION', '1.1.1');
define('LIVE_SHIPPING_RATES_AUSTRALIA_BASENAME', plugin_basename(__FILE__));
define('LIVE_SHIPPING_RATES_AUSTRALIA_URI', trailingslashit(plugins_url('/', __FILE__)));
define('LIVE_SHIPPING_RATES_AUSTRALIA_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('LIVE_SHIPPING_RATES_AUSTRALIA_PHP_MIN', '7.4');

define('LIVE_SHIPPING_RATES_AUSTRALIA_API_URI', 'https://codiepress.com');
define('LIVE_SHIPPING_RATES_AUSTRALIA_PLUGIN_ID', 1578);

/**
 * Check PHP version. Show notice if version of PHP less than our 7.4.3 
 * 
 * @since 1.0.0
 * @return void
 */
function live_shipping_rates_australia_php_missing_notice() {
	$notice = sprintf(
		/* translators: 1 for plugin name, 2 for PHP, 3 for PHP version */
		esc_html__('%1$s need %2$s version %3$s or greater.', 'live-shipping-rates-australia'),
		'<strong>Live Shipping Rates Australia</strong>',
		'<strong>PHP</strong>',
		LIVE_SHIPPING_RATES_AUSTRALIA_PHP_MIN
	);

	printf('<div class="notice notice-warning"><p>%1$s</p></div>', wp_kses_post($notice));
}

if (version_compare(PHP_VERSION, LIVE_SHIPPING_RATES_AUSTRALIA_PHP_MIN, '<')) {
	return add_action('admin_notices', 'live_shipping_rates_australia_php_missing_notice');
}

require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'vendor/autoload.php';
require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/class-main.php';