<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

use DVDoug\BoxPacker\Rotation;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\Test\TestBox;  // use your own `Box` implementation
use DVDoug\BoxPacker\Test\TestItem; // use your own `Item` implementation


/**
 * Shipping method class of australia post
 */
final class Australia_Post_Shipping extends Australia_Post {

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
			<th><?php esc_html_e('From Postcode', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="from_postcode" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Enter your postcode from where you want to ship your parcel.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>
<?php
	}

	/**
	 * Get from postcode
	 * 
	 * @since 1.0.0
	 * @return integer
	 */
	public function get_from_postcode() {
		return $this->get_option('from_postcode');
	}

	/**
	 * Order meta box
	 * 
	 * @since 1.0.3
	 * @return void
	 */
	function order_metabox($order_shipping) {


		$shipping_data = Utils::json_string_to_array($order_shipping->get_meta('shipping_data'));

		$data_keys = array(
			'from_postcode' => esc_html__('From Postcode', 'live-shipping-rates-australia'),
			'to_postcode' => esc_html__('To Postcode', 'live-shipping-rates-australia'),
			'length' => esc_html__('Length', 'live-shipping-rates-australia'),
			'width' => esc_html__('Width', 'live-shipping-rates-australia'),
			'height' => esc_html__('Height', 'live-shipping-rates-australia'),
			'weight' => esc_html__('Weight', 'live-shipping-rates-australia'),
			'service_code' => esc_html__('Service Code', 'live-shipping-rates-australia'),
			'option_code' => esc_html__('Option Code', 'live-shipping-rates-australia'),
			'suboption_code' => esc_html__('Suboption Code', 'live-shipping-rates-australia'),

			'contact_name' => esc_html__('Contact Name', 'live-shipping-rates-australia'),
			'business_name' => esc_html__('Business Name', 'live-shipping-rates-australia'),
			'address_line1' => esc_html__('Address Line 1', 'live-shipping-rates-australia'),
			'email_address' => esc_html__('Email', 'live-shipping-rates-australia'),
			'phone_number' => esc_html__('Phone', 'live-shipping-rates-australia'),
		);

		$line_items = $order_shipping->get_meta('line_items');
		if (is_array($line_items) && count($line_items) > 0) {
			unset($data_keys['length'], $data_keys['width'], $data_keys['height'], $data_keys['weight']);
		}

		$shipping_details = array();
		foreach ($data_keys as $key => $label) {
			if (isset($shipping_data[$key])) {
				$shipping_details[] = sprintf('<dt>%s</dt><dd>%s</dd>', $label, $shipping_data[$key]);
			}
		}

		if (!empty($shipping_data['country_code'])) {
			$country_code = $shipping_data['country_code'];
			$shipping_details[] = sprintf(
				'<dt>%s</dt><dd>%s (%s)</dd>',
				esc_html__('Country', 'live-shipping-rates-australia'),
				WC()->countries->countries[$country_code],
				$country_code
			);
		}

		if (is_array($line_items) && count($line_items) > 0) {

			$description_html = '<ul class="line-items-list">';
			foreach ($line_items as $line_item) {
				$description_html .= sprintf(
					'<li>Length: %scm x Width: %scm x Height: %scm - Weight: %s kgs</li>',
					$line_item['length'],
					$line_item['width'],
					$line_item['height'],
					$line_item['weight'],
				);
			}
			$description_html .= '</ul>';

			$shipping_details[] = sprintf(
				'<dt>%s</dt><dd class="line-item-container">%s</dd>',
				esc_html__('Line items', 'live-shipping-rates-australia'),
				$description_html
			);
		}

		echo '<h3 class="meta-box-title meta-box-title-bordered">' . esc_html__('Shipping details.', 'live-shipping-rates-australia') . '</h3>';
		echo '<dl class="shipping-rate-data">' . wp_kses_post(implode('', $shipping_details)) . '</dl>';
	}

	/**
	 * Check if current shipping rate available
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available($package) {
		$api_key = $this->get_setting('api_key');
		if (empty($api_key)) {
			$this->log_error(array(
				'post_content' => esc_html__('The Australia Post API Key is missing.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_api_key'
				)
			));

			return false;
		}

		if (empty($this->get_from_postcode())) {
			$this->log_error(array(
				'post_content' => esc_html__('You have not provided "Origin Postcode" in the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_origin_postcode'
				)
			));

			return false;
		}

		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();
		if (empty($shipping_address['country'])) {
			$this->debug_log(esc_html__('Please select your country.', 'live-shipping-rates-australia'));
			return false;
		}

		if ('AU' === $shipping_address['country'] && empty($shipping_address['postcode'])) {
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
		$is_international = false;
		$max_supported_weight = 22;
		$service_setting_key = 'domestic_services';

		if ('AU' !== $package['destination']['country']) {
			$is_international = true;
			$max_supported_weight = 20;
			$service_setting_key = 'international_services';
		}

		$shipping_services = array_filter($this->get_services($service_setting_key), function ($item) {
			return $item['active'] === true;
		});

		if (0 === count($shipping_services)) {
			if ($is_international) {
				$this->debug_log(esc_html__('Please activate at least one service in the "International Services" section of the Australia Post settings.', 'live-shipping-rates-australia'));
			} else {
				$this->debug_log(esc_html__('Please activate at least one service in the "Domestic Services" section of the Australia Post settings.', 'live-shipping-rates-australia'));
			}

			return;
		}

		$cart_total = Utils::get_cart_total_dimensions_and_weight($package['contents'], $this);

		$cart_total_mm = array(
			'height' => 1050,
			'length' => min(wc_get_dimension($cart_total['length'], 'mm', 'cm'), 1050),
			'width' => min(wc_get_dimension($cart_total['width'], 'mm', 'cm'), 1050)
		);

		$cart_total_mm['height'] = 250000000 / ($cart_total_mm['length'] * $cart_total_mm['width']);
		if ($cart_total_mm['height'] > 1050) {
			$cart_total_mm['height'] = 1050;
		}

		if (($cart_total['height'] * 10) <= $cart_total_mm['height']) {
			$cart_total_mm['height'] = $cart_total['height'] * 10;
		}

		$packer = new Packer();
		$packer->addBox(
			new TestBox(
				'Default Box', //reference: 
				$cart_total_mm['width'], //outerWidth
				$cart_total_mm['length'], //outerLength
				$cart_total_mm['height'], //outerDepth
				10, //emptyWeight
				$cart_total_mm['width'], //innerWidth
				$cart_total_mm['length'], //innerLength
				$cart_total_mm['height'], //innerDepth
				$max_supported_weight * 1000, //maxWeight
			)
		);

		foreach ($package['contents'] as $cart_item) {
			$product_measurements = Utils::get_product_measurements($cart_item['data'], $this);

			$packer->addItem(
				new TestItem(
					$cart_item['data']->get_name(), //description
					wc_get_dimension($product_measurements['width'], 'mm'), //width
					wc_get_dimension($product_measurements['length'], 'mm'), //length
					wc_get_dimension($product_measurements['height'], 'mm'), //depth
					wc_get_weight($product_measurements['weight'], 'g'), //weight
					Rotation::BestFit //allowedRotation
				),
				
				$cart_item['quantity']
			);
		}

		try {
			$packedBoxes = $packer->pack();
		} catch (\Exception $e) {
			//error_log(print_r($e, true));
			return $this->log_error(array('post_content' => $e->getMessage()));
		}

		$api_key = $this->get_setting('api_key');
		$api_request_url = 'https://digitalapi.auspost.com.au/postage/parcel/domestic/calculate';

		$request_data = array(
			'from_postcode' => $this->get_from_postcode(),
			'to_postcode' => $package['destination']['postcode'],
		);

		if ($is_international) {
			unset($request_data['from_postcode'], $request_data['to_postcode']);
			$request_data['country_code'] = $package['destination']['country'];
			$api_request_url = 'https://digitalapi.auspost.com.au/postage/parcel/international/calculate';
		}

		$customer = WC()->customer;

		$extra_request_data = array(
			'contact_name' => $customer->get_display_name(),
			'business_name' => $customer->get_shipping_company(),
			'address_line1' => $customer->get_shipping_address_1(),
			'email_address' => $customer->get_email(),
			'phone_number' => $customer->get_shipping_phone(),
		);


		foreach ($shipping_services as $service_item) {
			if (empty($service_item['code'])) {
				$service_item['code'] = 'AUS_PARCEL_REGULAR';
			}

			if (empty($service_item['title'])) {
				$service_item['title'] = $this->main_shipping_method->title;
			}

			$request_data['service_code'] = $service_item['code'];
			$request_data['option_code'] = $service_item['option'];
			$request_data['suboption_code'] = $service_item['suboption'];

			$rate_id = $this->get_key_from_array(array(
				$this->main_shipping_method->get_rate_id(),
				$service_item['code'],
				$service_item['option'],
				$service_item['suboption'],
			));


			$line_items = array();
			foreach ($packedBoxes as $packedBox) {
				$boxType = $packedBox->box;

				$line_item = array(
					'length' => wc_get_dimension($boxType->getOuterLength(), 'cm', 'mm'),
					'width' => wc_get_dimension($boxType->getOuterWidth(), 'cm', 'mm'),
					'height' => wc_get_dimension($boxType->getOuterDepth(), 'cm', 'mm'),
					'weight' => wc_get_weight($packedBox->getWeight(), 'kg', 'g'),
				);

				$request_data = array_merge($request_data, $line_item);

				$result = $this->get_rate_cache_data($rate_id, $request_data);

				if (false === $result) {
					$response = wp_remote_get($api_request_url, array(
						'headers' => array(
							'auth-key' => $api_key,
						),
						'body' => $request_data
					));

					if (is_wp_error($response)) {
						$this->log_error(array(
							'post_content' => $response->get_error_message(),
							'meta_input' => array(
								'error_code' => $response->get_error_code()
							)
						));

						$this->debug_log($response->get_error_message());
						continue;
					}

					$result = json_decode(wp_remote_retrieve_body($response), true);
				}

				if (isset($result['errors'][0]['detail'])) {
					$this->log_error(array(
						'post_content' => $result['errors'][0]['detail'],
						'meta_input' => array(
							'service_code' => $service_item['code'],
							'service_option' => $service_item['option'],
							'shipping_country_code' => $package['destination']['country'],
						)
					));
					continue;
				}


				if (!empty($result['error']['errorMessage'])) {
					$this->log_error(array(
						'post_content' => $result['error']['errorMessage'],
						'meta_input' => array(
							'service_code' => $service_item['code'],
							'service_option' => $service_item['option'],
							'shipping_country_code' => $package['destination']['country']
						)
					));
					continue;
				}



				if (!isset($result['postage_result']['total_cost'])) {
					continue;
				}

				$this->set_rate_cache_data($rate_id, $request_data, $result);
				$line_items[] = array_merge($line_item, array('cost' => $result['postage_result']['total_cost']));
			}

			if (count($packedBoxes) !== count($line_items)) {
				return $this->log_error(array(
					'post_content' => esc_html__('Packbox count and shipping cost count is not same.', 'live-shipping-rates-australia'),
				));
			}

			$total_shipping_cost = array_sum(wp_list_pluck($line_items, 'cost'));

			$title_contain_delivery_time = str_contains($service_item['title'], '{delivery_time}');
			$delivery_time = !empty($result['postage_result']['delivery_time']) ? $result['postage_result']['delivery_time'] : '';

			if (!$title_contain_delivery_time && 'title' == $this->get_setting('show_delivery_time')) {
				$service_item['title'] .= '(' . $delivery_time . ')';
			}

			$service_item['title'] = str_replace('{delivery_time}', $delivery_time, $service_item['title']);

			$shipping_rate_description = str_replace('{delivery_time}', $delivery_time, $service_item['description']);
			if ('description' === $this->get_setting('show_delivery_time')) {
				$shipping_rate_description = $delivery_time;
			}

			$shipping_data = array_merge($extra_request_data, $request_data);


			$shipping_cost = $this->exchange_currency($total_shipping_cost, 'AUD');
			if (false === $shipping_cost) {
				continue;
			}

			$shipping_costs = apply_filters($this->get_hook_name('shipping_costs'), array('shipping_cost' => $shipping_cost), $this, $result);

			$this->debug_log(sprintf(
				/* translators: %s for shipping costs */
				esc_html__('Shipping cost breakdown: %s', 'live-shipping-rates-australia'),
				wp_json_encode($shipping_costs)
			));

			$main_shipping_method->add_rate(array(
				'id' => $rate_id,
				'cost' => array_sum($shipping_costs),
				'label' => $service_item['title'],
				'package' => $package,
				'meta_data' => array(
					'line_items' => $line_items,
					'shipping_company' => $this->get_id(),
					'service_code' => $service_item['code'],
					'service_option' => $service_item['option'],
					'suboption_code' => $service_item['suboption'],
					'description' => $shipping_rate_description,
					'shipping_data' => wp_json_encode($shipping_data),
				)
			));
		}
	}
}
