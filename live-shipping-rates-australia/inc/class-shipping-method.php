<?php

namespace Live_Shipping_Rates_Australia;


if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shipping Method
 */
class Shipping_Method extends \WC_Shipping_Method {

	/**
	 * Hold child method 
	 * 
	 * @since 1.0.0
	 */
	var $child_method = null;

	/**
	 * Constructor
	 * 
	 * @since 1.0.0
	 * @return void
	 */

	public function __construct($instance_id = 0) {
		parent::__construct($instance_id);

		$this->id = 'live_shipping_rates_australia';
		$this->enabled = 'yes';
		$this->method_title = esc_html__('Live Shipping Rates Australia', 'live-shipping-rates-australia');
		$this->method_description = esc_html__('Provide your customers with the most accurate shipping costs by integrating real-time rate calculations directly from the shipping company.', 'live-shipping-rates-australia');

		$this->supports = array(
			'shipping-zones',
			'instance-settings'
		);

		$this->instance_form_fields = $this->get_settings();
		$this->title = $this->get_option('name', 'Live Shipping Rates Australia');
		$this->tax_status = $this->get_option('tax_status');

		$child_options = Utils::json_string_to_array($this->get_option('settings'));
		if (!empty($child_options['shipping_company'])) {
			$company_id = $child_options['shipping_company'];
			$shipping_companies = Main::get_shipping_companies();
			if (isset($shipping_companies[$company_id])) {
				$this->child_method = $shipping_companies[$company_id]->get_child_shipping_method($this);
			}
		}
	}

	/**
	 * Get the settings options of shipping method
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {
		$settings = array(
			'name' => array(
				'title'       => __('Name', 'live-shipping-rates-australia'),
				'type'        => 'text',
				'default'     => __('Live Shipping Rates Australia', 'live-shipping-rates-australia'),
			),

			'tax_status' => array(
				'title'   => __('Tax status', 'live-shipping-rates-australia'),
				'type'    => 'select',
				'default' => 'taxable',
				'class'   => 'wc-enhanced-select',
				'options' => array(
					'taxable' => __('Taxable', 'live-shipping-rates-australia'),
					'none'    => __('None', 'live-shipping-rates-australia'),
				),
			),

			'settings' => array(
				'default' => '',
				'title'   => __('Shipping Company', 'live-shipping-rates-australia'),
				'type'    => 'live_shipping_rates_australia_settings',
			),
		);

		return $settings;
	}

	/**
	 * Check if this shipping method availble for this cart
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available($package) {
		
		if (!parent::is_available($package)) {
			return false;
		}

		if (count($package['contents']) == 0 || is_null($this->child_method)) {
			return false;
		}

		if ($this->child_method->is_debugging() && !current_user_can('manage_woocommerce')) {
			return false;
		}

		return $this->child_method->is_available($package);
	}

	/**
	 * Calculate the shipping cost
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function calculate_shipping($package = array()) {
		if (count($package['contents']) == 0 || is_null($this->child_method)) {
			return;
		}

		$this->child_method->calculate_shipping($package, $this);
	}
}
