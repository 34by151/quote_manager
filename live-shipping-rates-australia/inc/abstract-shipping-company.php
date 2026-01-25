<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Abstract class for shipping settings
 */
abstract class Shipping_Company {

	/**
	 * Hold prefix of settings key
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	const PREFIX = 'live_shipping_rates_australia';

	/**
	 * Hold the nonce key
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_KEY_VALUE = '_nonce_value_live_shipping_rates_australia_settings';

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $company_id = '';

	/**
	 * Hold class name of child shipping method class
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $shipping_method_class = '';

	/**
	 * Hold settings of shipping company
	 * 
	 * @var array
	 */
	private $settings = null;

	/**
	 * Hold options of shipping method
	 * 
	 * @var array
	 */
	private $options = [];


	/**
	 * Hold all shipping methods
	 * 
	 * @var array
	 */
	public $shipping_methods = [];

	/**
	 * Hold errors
	 * 
	 * @since 1.0.0
	 * @var WP_Error
	 */
	protected $error = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->error = new \WP_Error();
		add_action('init', array($this, 'save_settings'));
	}

	/**
	 * Get id of the company
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_id() {
		return $this->company_id;
	}

	/**
	 * Get key
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_key($key, $separator = '_') {
		return implode($separator, array_filter(array(self::PREFIX, $this->get_id(), sanitize_key($key))));
	}

	/**
	 * Get key with separator
	 * 
	 * @since 1.0.2
	 * @return string
	 */
	public function get_key_from_array($args, $separator = ':') {
		return implode($separator, array_filter(array_map('sanitize_key', $args)));
	}

	/**
	 * Get field name of settings
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_settings_key() {
		return $this->get_key('settings');
	}

	/**
	 * Get hook name
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_hook_name($key) {
		return $this->get_key($key, '/');
	}

	/**
	 * Get ajax hook name
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_ajax_hook_name($key) {
		return 'wp_ajax_' . $this->get_key($key, '/');
	}

	/**
	 * Get settings models of shipping company
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_models() {
		$default_models = apply_filters('live_shipping_rates_australia/common_setting_models', array(
			'weight' => '',
			'width' => '',
			'height' => '',
			'length' => '',
			'debugging' => false,
		));

		return apply_filters($this->get_hook_name('settings_models'), wp_parse_args($this->_get_models(), $default_models));
	}

	/**
	 * Get helper models of settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_helper_models() {
		$helper_models = method_exists($this, '_get_helper_models') ? $this->_get_helper_models() : array();
		return wp_parse_args($helper_models, array(
			'api_error' => '',
			'api_message' => '',
			'api_checking' => false,
			'company_id' => $this->get_id(),
			'api_connected' => $this->is_api_connected(),
		));
	}

	/**
	 * Get models of shipping method
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_shipping_models() {
		return array();
	}

	/**
	 * Check if current company in debugging mode
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_debugging() {
		return $this->get_setting('debugging') == true;
	}

	/**
	 * Get connection status of shipping company
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_api_connected() {
		return false;
	}

	/**
	 * Save settings of shipping company
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings() {
		$settings_key = $this->get_settings_key();
		if (!isset($_POST['_nonce_live_shipping_rates_australia']) || !isset($_POST[$settings_key])) {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce_live_shipping_rates_australia'])), self::NONCE_KEY_VALUE)) {
			return;
		}

		update_option($settings_key, sanitize_text_field(wp_unslash($_POST[$settings_key])));
		wp_safe_redirect(remove_query_arg(null));  //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Get settings of shipping company
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {
		if (is_null($this->settings)) {
			$settings = Utils::json_string_to_array(get_option($this->get_settings_key()));
			$this->settings = wp_parse_args($settings, $this->get_models());
			if (method_exists($this, 'modify_settings')) {
				$this->settings = $this->modify_settings($this->settings);
			}
		}

		return $this->settings;
	}

	/**
	 * Get setting of a key
	 * 
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_setting($key, $default = null) {
		$settings = $this->get_settings();
		if (isset($settings[$key])) {
			if (is_array($settings[$key]) || is_object($settings[$key])) {
				return $settings[$key];
			}

			return trim($settings[$key]);
		}

		return $default;
	}

	/**
	 * Set shipping method options
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	protected function set_options($shipping_method_settings) {
		$this->options = Utils::json_string_to_array($shipping_method_settings);
	}

	/**
	 * Get shipping method option from key
	 * 
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_option($key, $default = null) {
		if (isset($this->options[$key])) {
			return trim($this->options[$key]);
		}

		return $default;
	}

	/**
	 * Get shipping method
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_child_shipping_method($shipping_object) {
		$instance_id = $shipping_object->instance_id;
		if (!isset($this->shipping_methods[$instance_id])) {
			$this->shipping_methods[$instance_id] = new $this->shipping_method_class($shipping_object);
		}

		return $this->shipping_methods[$instance_id];
	}

	/**
	 * Log error
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function log_error($post_data) {
		if ($this->is_debugging()) {
			return $this->debug_log($post_data['post_content']);
		}

		$post_data = wp_parse_args($post_data, array(
			'post_type' => Main::ERROR_POST_TYPE,
			'post_status' => 'publish'
		));

		$post_data['meta_input']['shipping_company_id'] = $this->get_id();
		wp_insert_post($post_data);
	}

	/**
	 * Show debug log on frontend for store manager
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	protected function debug_log($error_info, $type = 'notice') {
		if (!$this->is_debugging() || !current_user_can('manage_woocommerce')) {
			return;
		}

		if (function_exists('wc_add_notice')) {
			wc_add_notice($error_info, $type);
		}
	}

	/**
	 * Sanitize service item
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function sanitize_service_item($service_args) {
		return wp_parse_args($service_args, array(
			'id' => '',
			'code' => '',
			'title' => '',
			'option' => '',
			'suboption' => '',
			'description' => '',
			'active' => true,
		));
	}

	/**
	 * Get services
	 * 
	 * @since 1.0.0
	 * @param string $service_key settings key or class property
	 * @return array
	 */
	public function get_services($service_key) {
		$services = $this->get_setting($service_key);
		if (empty($services) || !is_array($services)) {
			if (property_exists($this, $service_key)) {
				$services = $this->$service_key;
			}
		}

		array_walk($services, function (&$item) {
			$item = $this->sanitize_service_item($item);
		});

		if (!property_exists($this, $service_key)) {
			return $services;
		}

		foreach ($this->$service_key as $service_item) {
			$service_item = $this->sanitize_service_item($service_item);
			$found_item = array_filter($services, function ($old_item) use ($service_item) {
				return $old_item['id'] == $service_item['id'];
			});

			if (count($found_item) > 0) {
				continue;
			}

			$service_item['active'] = false;
			$services[] = $service_item;
		}

		$service_ids = wp_list_pluck($this->$service_key, 'id');
		$services = array_filter($services, function ($item) use ($service_ids) {
			return in_array($item['id'], $service_ids);
		});

		return $services;
	}

	/**
	 * Get transient key of cache
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	private function get_rate_cache_key($rate_id, $requested_data) {
		return implode(':', array_filter(array($rate_id, md5(wp_json_encode($requested_data)))));
	}

	/**
	 * Set rate data at cache
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	protected function set_rate_cache_data($rate_id, $requested_data, $results) {
		set_transient($this->get_rate_cache_key($rate_id, $requested_data), $results, HOUR_IN_SECONDS / 3);
	}

	/**
	 * Get rate data from cache
	 * 
	 * @since 1.0.0
	 * @return mixed
	 */
	protected function get_rate_cache_data($rate_id, $requested_data) {
		return get_transient($this->get_rate_cache_key($rate_id, $requested_data));
	}

	/**
	 * Get total measurements of cart
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	function get_cart_measurements($cart_items) {
		$total_weight = $max_length = $max_width = $max_height = 0;

		foreach ($cart_items as $cart_item) {
			$product_measurements = Utils::get_product_measurements($cart_item['data'], $this);

			// Get dimensions and weight for the product
			$length = $product_measurements['length'];
			$width = $product_measurements['width'];
			$height = $product_measurements['height'];
			$weight = $product_measurements['weight'];

			// Get quantity
			$quantity = $cart_item['quantity'];

			// Update total weight
			$total_weight += $weight * $quantity;

			// Update maximum dimensions for combined parcel
			$max_length = max($max_length, $length);
			$max_width = max($max_width, $width);
			$max_height += $height * $quantity;
		}

		return array(
			'length' => wc_get_dimension($max_length, 'cm'),
			'width' => wc_get_dimension($max_width, 'cm'),
			'height' => wc_get_dimension($max_height, 'cm'),
			'weight' => wc_get_weight($total_weight, 'kg'),
		);
	}

	/**
	 * Get amount to store currency
	 * 
	 * @since 1.0.2
	 * @return float|false
	 */
	public function exchange_currency($amount, $currency_code) {
		$store_currency = strtolower(get_woocommerce_currency());
		$currency_code = strtolower($currency_code);
		if ($currency_code === $store_currency) {
			return $amount;
		}

		$currency_rates_meta_key = 'live_shipping_rates_australia_currency_rates_' . $store_currency;

		$rates = get_transient($currency_rates_meta_key);
		if (!empty($rates['date'])) {
			$rate_time = strtotime($rates['date']);
			if (false !== $rate_time) {
				if ($rate_time < strtotime('-2 days')) {
					delete_transient($currency_rates_meta_key);
				}
			}
		}

		if (!isset($rates[$store_currency])) {
			$response = wp_remote_get('https://latest.currency-api.pages.dev/v1/currencies/' . $store_currency . '.json');
			$rates = json_decode(wp_remote_retrieve_body($response), true);
			if (isset($rates[$store_currency]) && is_array($rates[$store_currency])) {
				set_transient($currency_rates_meta_key, $rates, HOUR_IN_SECONDS);
			}
		}

		if (isset($rates[$store_currency][$currency_code])) {
			$current_rate = floatval($rates[$store_currency][$currency_code]);

			$debug_message = sprintf(
				/* translators: %1$s for store currency, %2$s for current rate, %3$s for converted currency */
				esc_html__('The current exchange rate is 1 %1$s = %2$s %3$s.', 'live-shipping-rates-australia'),
				strtoupper($store_currency),
				$current_rate,
				strtoupper($currency_code)
			);

			$this->debug_log($debug_message);
			return $amount / $current_rate;
		}

		$this->log_error(array(
			'post_content' => esc_html__('Failed to retrieve the exchange rate from the API. If you continue to experience this issue, please feel free to reach out support@codiepress.com for assistance.', 'live-shipping-rates-australia'),
		));

		return false;
	}
}
