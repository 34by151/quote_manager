<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shipping method class of sendle post
 */
final class Sendle_Shipping extends Sendle {

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
	 * @since 1.0.2
	 * @return void
	 */
	public function options_html() { ?>
		<tr>
			<th><?php esc_html_e('Sender address line1', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="sender_address_line1" type="text">
				<p class="field-note">
					<?php esc_html_e('The street address for the location. Do not include the postcode, state, or suburb in this field. Best practice is to keep this under 40 chars due to label size limitations.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Sender address line2', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="sender_address_line2" type="text">
				<p class="field-note">
					<?php esc_html_e('Second line of the street address for the location. Best practice is to keep this under 40 chars due to label size limitations.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Suburb or town', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="sender_suburb" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Suburb or town of the location.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Sender postcode', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="sender_postcode" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Postcode, postal code, or ZIP code of the location.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Sender country', 'live-shipping-rates-australia') ?></th>
			<td>
				<select v-model="sender_country">
					<option value="AU"><?php esc_html_e('Australia', 'live-shipping-rates-australia') ?></option>
					<option value="CA"><?php esc_html_e('Canada', 'live-shipping-rates-australia') ?></option>
					<option value="US"><?php esc_html_e('United States', 'live-shipping-rates-australia') ?></option>
				</select>
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
	function order_metabox($order_shipping) {

		$product_code = $order_shipping->get_meta('product_code');
		echo '<div class="meta-info">';
		echo '<small>' . esc_html__('Product Code:', 'live-shipping-rates-australia') . '</small>';
		echo esc_html($product_code);
		echo '</div>';

		$product_service = $order_shipping->get_meta('product_service');
		echo '<div class="meta-info">';
		echo '<small>' . esc_html__('Product Service:', 'live-shipping-rates-australia') . '</small>';
		echo esc_html($product_service);
		echo '</div>';


		$shipping_data = Utils::json_string_to_array($order_shipping->get_meta('shipping_data'));

		$sender_details = array();

		$sender_keys = array(
			'sender_address_line1' => esc_html__('Address Line1', 'live-shipping-rates-australia'),
			'sender_address_line2' => esc_html__('Address Line2', 'live-shipping-rates-australia'),
			'sender_suburb' => esc_html__('Suburb', 'live-shipping-rates-australia'),
			'sender_postcode' => esc_html__('Postcode', 'live-shipping-rates-australia'),
		);

		foreach ($sender_keys as $key => $label) {
			if (isset($shipping_data[$key])) {
				$sender_details[] = sprintf('<dt>%s</dt><dd>%s</dd>', $label, $shipping_data[$key]);
			}
		}

		if (!empty($shipping_data['sender_country'])) {
			$country_code = $shipping_data['sender_country'];
			$sender_details[] = sprintf(
				'<dt>%s</dt><dd>%s (%s)</dd>',
				esc_html__('Country', 'live-shipping-rates-australia'),
				WC()->countries->countries[$country_code],
				$country_code
			);
		}

		echo '<h3 class="meta-box-title meta-box-title-bordered">' . esc_html__('Use the following information to create a shipment manually.', 'live-shipping-rates-australia') . '</h3>';

		echo '<h4 class="shipping-rate-data-title">' . esc_html__('Sender details', 'live-shipping-rates-australia') . '</h4>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post(implode('', $sender_details)) . '</dl>';


		$receiver_key = array(
			'receiver_address_line1' => esc_html__('Address Line1', 'live-shipping-rates-australia'),
			'receiver_address_line2' => esc_html__('Address Line2', 'live-shipping-rates-australia'),
			'receiver_suburb' => esc_html__('Suburb', 'live-shipping-rates-australia'),
			'receiver_postcode' => esc_html__('Postcode', 'live-shipping-rates-australia'),
		);

		$receiver_details = array();

		foreach ($receiver_key as $key => $label) {
			if (isset($shipping_data[$key])) {
				$receiver_details[] = sprintf('<dt>%s</dt><dd>%s</dd>', $label, $shipping_data[$key]);
			}
		}

		if (!empty($shipping_data['receiver_country'])) {
			$country_code = $shipping_data['receiver_country'];
			$receiver_details[] = sprintf(
				'<dt>%s</dt><dd>%s (%s)</dd>',
				esc_html__('Country', 'live-shipping-rates-australia'),
				WC()->countries->countries[$country_code],
				$country_code
			);
		}

		echo '<h4 class="shipping-rate-data-title">' . esc_html__('Receiver details', 'live-shipping-rates-australia') . '</h4>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post(implode('', $receiver_details)) . '</dl>';

		$others_info = array();
		if (isset($shipping_data['length_value'])) {
			$others_info[] = sprintf('<dt>%s</dt><dd>%s</dd>', esc_html__('Length', 'live-shipping-rates-australia'), $shipping_data['length_value']);
		}

		if (isset($shipping_data['width_value'])) {
			$others_info[] = sprintf('<dt>%s</dt><dd>%s</dd>', esc_html__('Width', 'live-shipping-rates-australia'), $shipping_data['width_value']);
		}

		if (isset($shipping_data['height_value'])) {
			$others_info[] = sprintf('<dt>%s</dt><dd>%s</dd>', esc_html__('Height', 'live-shipping-rates-australia'), $shipping_data['height_value']);
		}

		if (isset($shipping_data['weight_value'])) {
			$others_info[] = sprintf(
				'<dt>%s</dt><dd>%s</dd>',
				esc_html__('Weight', 'live-shipping-rates-australia'),
				$shipping_data['weight_value'],
			);
		}

		if (isset($shipping_data['weight_units'])) {
			$others_info[] = sprintf('<dt>%s</dt><dd>%s</dd>', esc_html__('Weight unit', 'live-shipping-rates-australia'), $shipping_data['weight_units']);
		}

		if (isset($shipping_data['dimension_units'])) {
			$others_info[] = sprintf('<dt>%s</dt><dd>%s</dd>', esc_html__('Dimensions unit', 'live-shipping-rates-australia'), $shipping_data['dimension_units']);
		}

		echo '<h4 class="shipping-rate-data-title">' . esc_html__('Others info', 'live-shipping-rates-australia') . '</h4>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post(implode('', $others_info)) . '</dl>';
	}

	/**
	 * Check if current shipping rate available
	 * 
	 * @since 1.0.2
	 * @return boolean
	 */
	public function is_available($package) {
		$sendle_id = $this->get_sendle_id();
		if (empty($sendle_id)) {
			$this->log_error(array(
				'post_content' => esc_html__('The Sendle ID has not been provided in the Sendle settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_sendle_id'
				)
			));

			return false;
		}

		$api_key = $this->get_sendle_api_key();
		if (empty($api_key)) {
			$this->log_error(array(
				'post_content' => esc_html__('The Sendle API key has not been provided in the Sendle settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_api_key'
				)
			));

			return false;
		}

		$sender_suburb = $this->get_option('sender_suburb');
		if (empty($sender_suburb)) {
			$this->log_error(array(
				'post_content' => esc_html__('The sender suburb has not been provided in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_sender_suburb'
				)
			));

			return false;
		}

		$sender_postcode = $this->get_option('sender_postcode');
		if (empty($sender_postcode)) {
			$this->log_error(array(
				'post_content' => esc_html__('The sender postcode has not been provided in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_sender_postcode'
				)
			));

			return false;
		}

		$sender_country = $this->get_option('sender_country');
		if (empty($sender_country)) {
			$this->log_error(array(
				'post_content' => esc_html__('The sender country has not been provided in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_sender_country'
				)
			));

			return false;
		}

		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();
		if (empty($shipping_address['city'])) {
			$this->debug_log(esc_html__('Please enter your city.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($shipping_address['postcode'])) {
			$this->debug_log(esc_html__('Please enter your postcode.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($shipping_address['country'])) {
			$this->debug_log(esc_html__('Please select your country.', 'live-shipping-rates-australia'));
			return false;
		}

		return true;
	}

	/**
	 * Add shipping rate
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public function calculate_shipping($package, $main_shipping_method) {
		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();

		$shipping_services = array_filter($this->get_services('services'), function ($item) {
			return $item['active'] === true;
		});

		if (0 === count($shipping_services)) {
			return $this->debug_log(esc_html__('Please activate at least one service in the "Services" section of the Australia Post settings.', 'live-shipping-rates-australia'));
		}

		$cart_measurements = $this->get_cart_measurements($package['contents']);


		$sender_country = $this->get_option('sender_country');

		$max_weight = 20;
		if ('AU' == $sender_country && $sender_country === $shipping_address['country']) {
			$max_weight = 25;

			if ($cart_measurements['weight'] > $max_weight) {
				$error_message = sprintf(
					/* translators: %1$s for total weight of cart, %2$s for supported weight */
					esc_html__('The total cart weight is %1$s kg, exceeding the limit of %2$s kg in Australia.', 'live-shipping-rates-australia'),
					$cart_measurements['weight'],
					$max_weight
				);

				return $this->debug_log($error_message);
			}
		}

		if ('CA' == $sender_country && $sender_country === $shipping_address['country']) {
			$max_weight = 30;

			if ($cart_measurements['weight'] > $max_weight) {
				$error_message = sprintf(
					/* translators: %1$s for total weight of cart, %2$s for supported weight */
					esc_html__('The total cart weight is %1$s kg, exceeding the limit of %2$s kg in Canada.', 'live-shipping-rates-australia'),
					$cart_measurements['weight'],
					$max_weight
				);

				return $this->debug_log($error_message);
			}
		}

		if ('US' == $sender_country && $sender_country === $shipping_address['country']) {
			$max_weight = 22.67;

			if ($cart_measurements['weight'] > $max_weight) {
				$error_message = sprintf(
					/* translators: %1$s for total weight of cart, %2$s for supported weight */
					esc_html__('The total cart weight is %1$s kg, exceeding the limit of %2$s kg in USA.', 'live-shipping-rates-australia'),
					$cart_measurements['weight'],
					$max_weight
				);

				return $this->debug_log($error_message);
			}
		}

		if ($cart_measurements['weight'] > $max_weight) {
			$error_message = sprintf(
				/* translators: %s for total weight of cart */
				esc_html__('The total cart weight is %s kg, exceeding the limit of 22 kg.', 'live-shipping-rates-australia'),
				$cart_measurements['weight'],
			);

			return $this->debug_log($error_message);
		}

		$request_data = array(
			'sender_address_line1' => $this->get_option('sender_address_line1'),
			'sender_address_line2' => $this->get_option('sender_address_line2'),
			'sender_suburb' => $this->get_option('sender_suburb'),
			'sender_postcode' => $this->get_option('sender_postcode'),
			'sender_country' => $sender_country,

			'receiver_address_line1' => $shipping_address['address_1'],
			'receiver_address_line2' => $shipping_address['address_2'],
			'receiver_suburb' => $shipping_address['city'],
			'receiver_postcode' => $shipping_address['postcode'],
			'receiver_country' => $shipping_address['country'],

			'dimension_units' => 'cm',
			'weight_units' => 'kg',
			'length_value' => $cart_measurements['length'],
			'width_value' => $cart_measurements['width'],
			'height_value' => $cart_measurements['height'],
			'weight_value' => $cart_measurements['weight'],
		);

		$result = $this->get_rate_cache_data('live_shipping_rates_australia_sendle_rates_result', $request_data);

		if (!is_array($result) || empty($result)) {
			$result = array();
			$end_point_url = $this->get_api_endpoint('api/products') . '?' . http_build_query($request_data);

			$response = wp_remote_get($end_point_url, array(
				'headers' => array(
					'authorization' => 'Basic ' . base64_encode($this->get_sendle_id() . ':' . $this->get_sendle_api_key())
				),
			));

			if (is_wp_error($response)) {
				return $this->log_error(array(
					'post_content' => $response->get_error_message(),
					'meta_input' => array(
						'error_code' => $response->get_error_code()
					)
				));
			}

			if (401 === wp_remote_retrieve_response_code($response)) {
				$this->log_error(array(
					'post_content' => esc_html__('HTTP Basic: Access denied', 'live-shipping-rates-australia'),
					'meta_input' => array(
						'error_code' => 401
					)
				));
			}

			$result = json_decode(wp_remote_retrieve_body($response), true);

			if (200 !== wp_remote_retrieve_response_code($response) && !empty($result['messages'])) {
				$this->log_error(array(
					'post_content' => $result['error_description'] . ' ' . wp_json_encode($result['messages']),
					'meta_input' => array(
						'error_code' => $result['error']
					)
				));
			}

			if (200 === wp_remote_retrieve_response_code($response)) {
				$this->set_rate_cache_data('live_shipping_rates_australia_sendle_rates_result', $request_data, $result);
			}
		}

		foreach ($result as $rate_data) {
			if (!isset($rate_data['quote'])) {
				continue;
			}

			$quote_product_id = $this->get_key_from_array(array(
				$rate_data['product']['service'],
				$rate_data['product']['code']
			));

			$shipping_service_items = array_filter($shipping_services, function ($product_item) use ($quote_product_id) {
				return $product_item['id'] == $quote_product_id;
			});

			if (count($shipping_service_items) === 0) {
				continue;
			}

			$shipping_service_item = current($shipping_service_items);

			$rate_id = $this->get_key_from_array(array(
				$main_shipping_method->get_rate_id(),
				$rate_data['product']['service'],
				$rate_data['product']['code']
			));

			$currency_code = $rate_data['quote']['gross']['currency'];

			$shipping_cost = $this->exchange_currency($rate_data['quote']['gross']['amount'], $currency_code);
			if (false === $shipping_cost) {
				continue;
			}

			$shipping_costs = apply_filters($this->get_hook_name('shipping_costs'), array('shipping_cost' => $shipping_cost), $this, $rate_data);

			$this->debug_log(sprintf(
				/* translators: %s for shipping costs */
				esc_html__('Shipping cost breakdown: %s', 'live-shipping-rates-australia'),
				wp_json_encode($shipping_costs)
			));

			$main_shipping_method->add_rate(array(
				'id' => $rate_id,
				'cost' => array_sum($shipping_costs),
				'label' => $shipping_service_item['title'],
				'package' => $package,
				'meta_data' => array(
					'shipping_company' => $this->get_id(),
					'shipping_data' => wp_json_encode($request_data),
					'product_code' => $rate_data['product']['code'],
					'product_service' => $rate_data['product']['service'],
					'description' => $shipping_service_item['description'],
				)
			));
		}
	}
}
