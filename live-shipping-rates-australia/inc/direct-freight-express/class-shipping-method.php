<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shipping method class of direct freight express
 */
final class Direct_Freight_Express_Shipping extends Direct_Freight_Express {

	/** 
	 * Hold the main shipping method
	 * 
	 * @var \WC_Shipping_Method
	 */
	private $main_shipping_method = null;

	/**
	 * Constructor.
	 */
	public function __construct($shipping_method) {
		if (!is_a($shipping_method, '\Live_Shipping_Rates_Australia\Shipping_Method')) {
			return;
		}

		$this->main_shipping_method = $shipping_method;
		$this->set_options($shipping_method->get_option('settings'));
	}

	/**
	 * Shipping method options
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function options_html() { ?>
		<tr>
			<th><?php esc_html_e('Suburb From', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="suburb_from" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Enter your suburb where you want to ship your parcel, otherwise this shipping method will not show on the front-end.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Postcode From', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="postcode_from" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Enter your postcode from where you want to ship your parcel, otherwise this shipping method will not show on the front-end.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>
<?php
	}

	/**
	 * Order meta box
	 * 
	 * @since 1.0.3
	 * @return void
	 */
	function order_metabox($order_shipping, $order_id = 0) {
		$order = wc_get_order($order_id);
		$shipping_data = Utils::json_string_to_array($order_shipping->get_meta('shipping_data'));

		echo '<h3 class="meta-box-title meta-box-title-bordered">' . esc_html__('Use the following information to create a shipment manually.', 'live-shipping-rates-australia') . '</h3>';

		$sender_details = Utils::get_order_dl_data($shipping_data, array(
			'SuburbFrom' => esc_html__('Suburb From', 'live-shipping-rates-australia'),
			'PostcodeFrom' => esc_html__('Postcode From', 'live-shipping-rates-australia'),
		));

		echo '<h4 class="shipping-rate-data-title">' . esc_html__('Sender details', 'live-shipping-rates-australia') . '</h4>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post($sender_details) . '</dl>';

		$receiver_name = array(
			$order->get_shipping_first_name(),
			$order->get_shipping_last_name(),
		);

		$receiver_data = array(
			'receiver_name' => implode(' ', $receiver_name),
			'receiver_address1' => $order->get_shipping_address_1(),
			'receiver_address2' => $order->get_shipping_address_2(),
			'receiver_suburb' => $order->get_shipping_city(),
			'postcode' => $order->get_shipping_postcode(),
			'state' => $order->get_shipping_state(),
		);

		$receiver_html = Utils::get_order_dl_data($receiver_data, array(
			'receiver_name' => esc_html__('Name', 'live-shipping-rates-australia'),
			'receiver_address1' => esc_html__('Address 1', 'live-shipping-rates-australia'),
			'receiver_address2' => esc_html__('Address 2', 'live-shipping-rates-australia'),
			'receiver_suburb' => esc_html__('Suburb', 'live-shipping-rates-australia'),
			'postcode' => esc_html__('Postcode', 'live-shipping-rates-australia'),
			'state' => esc_html__('State', 'live-shipping-rates-australia'),
		));

		echo '<h4 class="shipping-rate-data-title">' . esc_html__('Receiver details', 'live-shipping-rates-australia') . '</h4>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post($receiver_html) . '</dl>';

		if (isset($shipping_data['ConsignmentLineItems']) && is_array($shipping_data['ConsignmentLineItems'])) {
			foreach ($shipping_data['ConsignmentLineItems'] as $line_item) {
				$line_item_html = Utils::get_order_dl_data($line_item, array(
					'RateType' => esc_html__('Rate Type', 'live-shipping-rates-australia'),
					'Items' => esc_html__('Items', 'live-shipping-rates-australia'),
					'Kgs' => esc_html__('Quantity', 'live-shipping-rates-australia'),
					'Length' => esc_html__('Length', 'live-shipping-rates-australia'),
					'Width' => esc_html__('Width', 'live-shipping-rates-australia'),
					'Height' => esc_html__('Height', 'live-shipping-rates-australia'),
				));

				echo '<h4 class="shipping-rate-data-title">' . esc_html__('Line item', 'live-shipping-rates-australia') . '</h4>';
				echo '<dl class="shipping-rate-data">' . wp_kses_post($line_item_html) . '</dl>';
			}
		}
	}

	/**
	 * Get suburb from
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_suburb_from() {
		return $this->get_option('suburb_from');
	}

	/**
	 * Get postcode from
	 * 
	 * @since 1.0.0
	 * @return integer
	 */
	public function get_postcode_from() {
		return $this->get_option('postcode_from');
	}

	/**
	 * Check if current shipping rate available
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available($package) {
		$settings = $this->get_settings();
		if (empty($settings['api_key'])) {
			$this->log_error(array(
				'post_content' => esc_html__('The API key for Direct Freight Express is missing.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_api_key'
				)
			));

			return false;
		}

		if (empty($settings['account_number'])) {
			$this->log_error(array(
				'post_content' => esc_html__('The account number for Direct Freight Express is missing.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_account_number'
				)
			));

			return false;
		}

		$suburb_from = $this->get_suburb_from();
		if (empty($suburb_from)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter "Suburb From" in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_suburb_from'
				)
			));

			return false;
		}

		$postcode_from = $this->get_postcode_from();
		if (empty($postcode_from)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter "Postcode Form" in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_postcode_from'
				)
			));

			return false;
		}

		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();
		if (empty($shipping_address['country'])) {
			$this->debug_log(esc_html__('Please select your country.', 'live-shipping-rates-australia'));
			return false;
		}

		if ('AU' != $shipping_address['country']) {
			$this->debug_log(esc_html__('Direct Freight Express is currently available only in Australia.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($shipping_address['city'])) {
			$this->debug_log(esc_html__('Please enter your city.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($shipping_address['postcode'])) {
			$this->debug_log(esc_html__('Please enter your postcode.', 'live-shipping-rates-australia'));
			return false;
		}

		return true;
	}

	/**
	 * Add shipping rate
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function calculate_shipping($package, $main_shipping_method) {
		$request_data = array(
			'SuburbFrom' => $this->get_suburb_from(),
			'PostcodeFrom' => $this->get_postcode_from(),
			'SuburbTo' => $package['destination']['city'],
			'PostcodeTo' => $package['destination']['postcode'],
			'ConsignmentLineItems' => array()
		);

		$supported_rate_types = array_keys($this->get_rate_types());

		foreach ($package['contents'] as $cart_item) {
			$product = $cart_item['data'];
			if ($product->is_virtual()) {
				continue;
			}

			$rate_type_meta_key = $this->get_key('rate_type');

			$product_rate_type = $product->get_meta($rate_type_meta_key);
			if ($product->is_type('variation') && 'inherit' === $product_rate_type) {
				$product_rate_type = get_post_meta($product->get_parent_id(), $rate_type_meta_key, true);
			}

			if (!in_array($product_rate_type, $supported_rate_types)) {
				$product_rate_type = $this->get_setting('default_rate_type', 'ITEM');
			}

			$measurements = Utils::get_product_measurements($product, $this);
			$consignment_line_item = apply_filters($this->get_hook_name('line_item'), array(
				'RateType' => $product_rate_type,
				'Items' => $cart_item['quantity'],
				'Kgs' => wc_get_weight($measurements['weight'] * $cart_item['quantity'], 'kg'),
				'Length' => round(wc_get_dimension($measurements['length'], 'cm')),
				'Width' => round(wc_get_dimension($measurements['width'], 'cm')),
				'Height' => round(wc_get_dimension($measurements['height'], 'cm')),
			), $product, $measurements);

			$request_data['ConsignmentLineItems'][] = $consignment_line_item;
		}

		if (0 == count($request_data['ConsignmentLineItems'])) {
			return $this->debug_log(esc_html__('There is no validated cart item in the cart.', 'live-shipping-rates-australia'));
		}

		$shipping_prices = $this->get_rate_cache_data($this->main_shipping_method->get_rate_id(), $request_data);
		if (false === $shipping_prices) {
			$response = wp_remote_post('https://webservices.directfreight.com.au/Dispatch/api/GetConsignmentPrice/', array(
				'headers' => array(
					'Authorisation' => $this->get_setting('api_key'),
					'AccountNumber' => $this->get_setting('account_number'),
					'Content-Type' => 'application/json'
				),

				'body' => wp_json_encode($request_data)
			));

			if (is_wp_error($response)) {
				return $this->log_error(array(
					'post_content' => $response->get_error_message(),
					'meta_input' => array(
						'error_code' => $response->get_error_code()
					)
				));
			}

			$shipping_prices = json_decode(wp_remote_retrieve_body($response), true);
			if (isset($shipping_prices['Message'])) {
				return $this->log_error(array(
					'post_title' => __('No HTTP resource was found', 'live-shipping-rates-australia'),
					'post_content' => $shipping_prices['Message'],
					'meta_input' => array(
						'error_code' => 404
					)
				));
			}
		}

		if (isset($shipping_prices['ResponseCode']) && 300 != $shipping_prices['ResponseCode']) {
			$this->log_error(array(
				'post_content' => $shipping_prices['ResponseMessage'],
				'meta_input' => array(
					'error_code' => $shipping_prices['ResponseCode'],
					'shipping_city' => $package['destination']['city'],
					'shipping_postcode' => $package['destination']['postcode'],
				)
			));
		}

		if (!isset($shipping_prices['ResponseCode']) || 300 != $shipping_prices['ResponseCode']) {
			return;
		}

		$this->set_rate_cache_data($this->main_shipping_method->get_rate_id(), $request_data, $shipping_prices);

		$total_freight_charge = $this->exchange_currency($shipping_prices['TotalFreightCharge'], 'AUD');
		if (false === $total_freight_charge) {
			return;
		}

		$shipping_costs = array(
			'total_freight_charge' => $total_freight_charge,
			'fuel_levy_charge' => $this->exchange_currency($shipping_prices['FuelLevyCharge'], 'AUD'),
		);

		$shipping_costs = apply_filters($this->get_hook_name('shipping_costs'), $shipping_costs, $this, $shipping_prices);

		$this->debug_log(sprintf(
			/* translators: %s for shipping costs */
			esc_html__('Shipping cost breakdown: %s', 'live-shipping-rates-australia'),
			wp_json_encode($shipping_costs)
		));

		$main_shipping_method->add_rate(array(
			'cost' => array_sum($shipping_costs),
			'id' => $main_shipping_method->get_rate_id(),
			'label' => $main_shipping_method->title,
			'package' => $package,
			'meta_data' => array(
				'shipping_company' => $this->get_id(),
				'shipping_data' => wp_json_encode($request_data),
			)
		));
	}
}
