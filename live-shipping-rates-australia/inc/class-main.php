<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Main class plugin
 */
final class Main {

	/**
	 * Hold the current instance of plugin
	 * 
	 * @since 1.0.0
	 * @var Main
	 */
	private static $instance = null;

	/**
	 * Get instance of current class
	 * 
	 * @since 1.0.0
	 * @return Main
	 */
	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hold all registered companies
	 * 
	 * @var array
	 */
	public static $companies = [];

	/**
	 * Add new shipping company
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_shipping_company($shipping_company) {
		self::$companies[$shipping_company->get_id()] = $shipping_company;
	}

	/**
	 * Get all shipping company company
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_shipping_companies() {
		return self::$companies;
	}

	/** 
	 * Error post type
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	const ERROR_POST_TYPE = 'lsra_error';

	/**
	 * Hold admin class
	 * 
	 * @var Admin
	 */
	var $admin = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/class-utils.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/class-admin.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/class-suggested-plugin.php';

		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/abstract-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/sendle/class-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/sendle/class-shipping-method.php';

		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/aramex/class-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/aramex/class-shipping-method.php';

		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/australia-post/class-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/australia-post/class-shipping-method.php';

		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/aramex-connect/class-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/aramex-connect/class-shipping-method.php';

		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/direct-freight-express/class-shipping-company.php';
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/direct-freight-express/class-shipping-method.php';

		$this->init();
	}

	/**
	 * Class initization
	 * 
	 * @since 1.0.0
	 * @return void
	 */

	public function init() {
		$this->admin = new Admin();

		add_action('init', array($this, 'register_post_type'));
		add_filter('plugin_action_links', array($this, 'add_plugin_links'), 10, 2);
		add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
		add_filter('woocommerce_after_shipping_rate', array($this, 'add_shipping_description'));
	}

	/**
	 * Register post type for debug
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(self::ERROR_POST_TYPE, array(
			'labels' => array(
				'name' => __('Live Shipping Rates Australia Error', 'live-shipping-rates-australia'),
			),
			'public' => false,
		));
	}

	/**
	 * Add add get pro link in plugin links
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function add_plugin_links($actions, $plugin_file) {
		if (LIVE_SHIPPING_RATES_AUSTRALIA_BASENAME == $plugin_file) {
			$setting_page_url = menu_page_url('live-shipping-rates-australia', false);

			$new_links[] = sprintf('<a href="%s">%s</a>', $setting_page_url, __('Settings', 'live-shipping-rates-australia'));
			if (!file_exists(WP_PLUGIN_DIR . '/live-shipping-rates-australia-pro/live-shipping-rates-australia-pro.php')) {
				$new_links[] = sprintf('<a href="https://codiepress.com/plugins/live-shipping-rates-australia-for-woocommerce/?utm_campaign=live+shipping+rates+australia&utm_source=plugins&utm_medium=get+pro" target="_blank">%s</a>', __('Get Pro', 'live-shipping-rates-australia'));
			}

			$actions = array_merge($new_links, $actions);
		}

		return $actions;
	}

	/**
	 * Register the shipping method to WooCommerce.
	 * 
	 * @since 1.0.0
	 * @param array $methods List of shipping methods.
	 * @return array List of modified shipping methods.
	 */
	public function add_shipping_method($methods) {
		require_once LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/class-shipping-method.php';
		$methods['live_shipping_rates_australia'] = Shipping_Method::class;
		return $methods;
	}

	/**
	 * Handle shipping rate description
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_shipping_description($shipping_rate) {
		if ('live_shipping_rates_australia' !== $shipping_rate->get_method_id()) {
			return;
		}

		$meta_data = $shipping_rate->get_meta_data();
		if (!empty($meta_data['description'])) {
			echo '<div class="live-shipping-rates-australia-rate-description">' . wp_kses_post($meta_data['description']) . '</div>';
		}
	}
}

Main::get_instance();
