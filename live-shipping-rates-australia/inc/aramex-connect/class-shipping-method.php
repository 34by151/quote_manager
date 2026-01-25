<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

use DVDoug\BoxPacker\Rotation;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\Test\TestBox;
use DVDoug\BoxPacker\Test\TestItem;

/**
 * Shipping method class of AramexConnect
 */
final class Aramex_Connect_Shipping extends Aramex_Connect {

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
	 * Order meta box
	 * 
	 * @since 1.0.3
	 * @return void
	 */
	function order_metabox($order_shipping) {
		$shipping_data = Utils::json_string_to_array($order_shipping->get_meta('shipping_data'));

		echo '<h3 class="meta-box-title meta-box-title-bordered">' . esc_html__('Use the following information to create a shipment manually.', 'live-shipping-rates-australia') . '</h3>';

		if (isset($shipping_data['To']['Address']) && is_array($shipping_data['To']['Address'])) {
			$addresses = $shipping_data['To']['Address'];

			if (!empty($addresses['Country'])) {
				$country_code = $addresses['Country'];
				$addresses['Country'] = sprintf('%s (%s)', WC()->countries->countries[$country_code], $country_code);
			}

			$receiver_details = Utils::get_order_dl_data($addresses, array(
				'StreetAddress' => esc_html__('Street Address', 'live-shipping-rates-australia'),
				'Locality' => esc_html__('Locality', 'live-shipping-rates-australia'),
				'StateOrProvince' => esc_html__('State / Province', 'live-shipping-rates-australia'),
				'PostalCode' => esc_html__('Postcode', 'live-shipping-rates-australia'),
				'Country' => esc_html__('Country', 'live-shipping-rates-australia'),
			));

			echo '<h4 class="shipping-rate-data-title">' . esc_html__('Receiver details', 'live-shipping-rates-australia') . '</h4>';
			echo '<dl class="shipping-rate-data">' . wp_kses_post($receiver_details) . '</dl>';
		}

		if (isset($shipping_data['Services']) && is_array($shipping_data['Services']) && count($shipping_data['Services']) > 0) {
			$service = current($shipping_data['Services']);

			$service_details = Utils::get_order_dl_data($service, array(
				'ServiceCode' => esc_html__('Service Code', 'live-shipping-rates-australia'),
				'ServiceItemCode' => esc_html__('Service Item Code', 'live-shipping-rates-australia'),
			));

			echo '<h4 class="shipping-rate-data-title">' . esc_html__('Service information', 'live-shipping-rates-australia') . '</h4>';
			echo '<dl class="shipping-rate-data">' . wp_kses_post($service_details) . '</dl>';
		}

		$line_items = $order_shipping->get_meta('line_items');
		if (is_array($line_items) && count($line_items) > 0) {
			$shipping_data['Items'] = $line_items;
		}

		if (isset($shipping_data['Items']) && is_array($shipping_data['Items'])) {
			$line_items_html = [];

			foreach ($shipping_data['Items'] as $line_item) {
				$line_item_html = '<dl class="shipping-rate-data">';

				$package_type = $line_item['PackageType'];
				if ('P' == $package_type) {
					$package_types = $this->get_package_types();
					if (isset($package_types[$package_type])) {
						$line_item['package_type'] = $package_types[$package_type];
					}
				}

				if ('S' == $package_type) {
					$satchel_size = $line_item['SatchelSize'];
					$satchel_sizes = $this->get_satchel_sizes();

					if (isset($satchel_sizes[$satchel_size])) {
						$line_item['satchel_size'] = $satchel_sizes[$satchel_size];
					}
				}

				$line_item_html .= Utils::get_order_dl_data($line_item, array(
					'package_type' => esc_html__('Package Type', 'live-shipping-rates-australia'),
					'satchel_size' => esc_html__('Satchel Size', 'live-shipping-rates-australia'),
					'Quantity' => esc_html__('Quantity', 'live-shipping-rates-australia'),
					'WeightDead' => esc_html__('Weight', 'live-shipping-rates-australia'),
					'Length' => esc_html__('Length', 'live-shipping-rates-australia'),
					'Width' => esc_html__('Width', 'live-shipping-rates-australia'),
					'Height' => esc_html__('Height', 'live-shipping-rates-australia'),
				));

				$line_item_html .= '</dl><hr>';

				$line_items_html[] = $line_item_html;
			}

			echo '<h4 class="shipping-rate-data-title">' . esc_html__('Line items', 'live-shipping-rates-australia') . '</h4>';
			echo '<hr>';
			echo wp_kses_post(implode($line_items_html));
		}
	}

	/**
	 * Get package items for getting quote
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_package_items($cart_items) {
		$supported_package_types = array_keys($this->get_package_types());
		$suppoted_satchel_sizes = array_keys($this->get_satchel_sizes());

		$package_items = array();

		foreach ($cart_items as $cart_item) {
			$_product = $cart_item['data'];
			if (!$_product->needs_shipping()) {
				continue;
			}

			$product_measurements = Utils::get_product_measurements($_product, $this);

			$package_type_meta_key = $this->get_key('package_type');
			$package_type = $_product->get_meta($package_type_meta_key);

			if ($_product->is_type('variation') && 'inherit' === $package_type) {
				$package_type = get_post_meta($_product->get_parent_id(), $package_type_meta_key, true);
			}

			if (!in_array($package_type, $supported_package_types)) {
				$package_type = $this->get_setting('default_package_type', 'P');
			}

			$satchel_size = '';
			if ('S' === $package_type) {
				$satchel_size_meta_key = $this->get_key('satchel_size');
				$satchel_size = $_product->get_meta($satchel_size_meta_key);

				if ($_product->is_type('variation') && 'inherit' === $satchel_size) {
					$satchel_size = get_post_meta($_product->get_parent_id(), $satchel_size_meta_key, true);
				}

				if (!in_array($satchel_size, $suppoted_satchel_sizes)) {
					$satchel_size = $this->get_setting('default_satchel_size', '300gm');
				}
			}

			$product_name = $cart_item['data']->get_name();
			if (empty($product_name)) {
				$product_name = get_the_title($cart_item['product_id']);
			}

			$line_item = apply_filters($this->get_hook_name('line_item'), array(
				'PackageType' => $package_type,
				'SatchelSize' => $satchel_size,
				'Quantity' => $cart_item['quantity'],
				'product_name' => $product_name,
				'WeightDead' => wc_get_weight($product_measurements['weight'], 'kg'),
				'Length' => wc_get_dimension($product_measurements['length'], 'cm'),
				'Width' => wc_get_dimension($product_measurements['width'], 'cm'),
				'Height' => wc_get_dimension($product_measurements['height'], 'cm')
			), $_product, $product_measurements);

			$package_items[] = $line_item;
		}

		return apply_filters($this->get_hook_name('consignment_line_items'), $package_items, $this);
	}

	/**
	 * Check if current shipping rate available
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available($package) {
		$settings = $this->get_settings();
		if (empty($settings['client_id'])) {
			$this->log_error(array(
				'post_content' => esc_html__('The Client ID is missing in AramexConnect settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_client_id'
				)
			));

			return false;
		}

		if (empty($settings['client_secret'])) {
			$this->log_error(array(
				'post_content' => esc_html__('The Client secret is missing in AramexConnect settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'missing_client_secret'
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
			$this->debug_log(esc_html__('AramexConnect is currently available only in Australia.', 'live-shipping-rates-australia'));
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
		$shipping_services = array_filter($this->get_services('domestic_services'), function ($item) {
			return $item['active'] === true;
		});

		if (count($shipping_services) === 0) {
			return $this->debug_log(esc_html__('Please activate at least one service in AramexConnect settings.', 'live-shipping-rates-australia'));
		}

		$items = $this->get_package_items($package['contents']);
		if (count($items) === 0) {
			return $this->debug_log(esc_html__('There is no validated cart item in the cart.', 'live-shipping-rates-australia'));
		}

		$customer_name = WC()->customer->get_display_name();
		if (empty($customer_name)) {
			$customer_name = 'Fake Name';
		}

		$customer_email = WC()->customer->get_email();
		if (empty($customer_email)) {
			$customer_email = 'fake@email.com';
		}

		$customer_phone = WC()->customer->get_shipping_phone();
		if (empty($customer_phone)) {
			$customer_phone = '7464646464';
		}

		//START BOX ITEM
		$parcel_items = array();
		foreach ($items as $item_key => $item_data) {
			if ($item_data['PackageType'] == 'P') {
				$parcel_items[] = $item_data;
				unset($items[$item_key]);
			}
		}

		$allow_quantities = [];
		$max_length = $max_width = $max_height = $total_height = 1;
		foreach ($parcel_items as $parcel_item) {
			$length = $parcel_item['Length'];
			$width = $parcel_item['Width'];
			$height = $parcel_item['Height'];
			$weight = $parcel_item['WeightDead'];

			$allow_quantities[] = max(1, floor(25 / $parcel_item['WeightDead']));

			$max_length = max($max_length, $length);
			$max_width = max($max_width, $width);
			$max_height = max($max_height, $height);

			$total_height += ($height * $parcel_item['Quantity']);
		}

		$parcel_item_data_mm = array(
			'height' => ($max_height * min($allow_quantities)) * 10,
			'length' => min(wc_get_dimension($max_length, 'mm', 'cm'), 2000),
			'width' => min(wc_get_dimension($max_width, 'mm', 'cm'), 2000)
		);

		if (($total_height * 10) <= $parcel_item_data_mm['height']) {
			$parcel_item_data_mm['height'] = $total_height * 10;
		}

		if ($parcel_item_data_mm['height'] > 2000) {
			$parcel_item_data_mm['height'] = 2000;
		}

		if ($parcel_item_data_mm['length'] > 2000) {
			$parcel_item_data_mm['length'] = 2000;
		}

		if ($parcel_item_data_mm['width'] > 2000) {
			$parcel_item_data_mm['width'] = 2000;
		}

		$packer = new Packer();
		$packer->addBox(
			new TestBox(
				'Default Box', //reference: 
				$parcel_item_data_mm['width'], //outerWidth
				$parcel_item_data_mm['length'], //outerLength
				$parcel_item_data_mm['height'], //outerDepth
				10, //emptyWeight
				$parcel_item_data_mm['width'], //innerWidth
				$parcel_item_data_mm['length'], //innerLength
				$parcel_item_data_mm['height'], //innerDepth
				25000, //maxWeight
			)
		);

		foreach ($parcel_items as $parcel_item) {
			$packer->addItem(
				new TestItem(
					//description: $parcel_item['product_name'],
					'box-item', //description: 
					wc_get_dimension($parcel_item['Width'], 'mm', 'cm'), //width: 
					wc_get_dimension($parcel_item['Length'], 'mm', 'cm'), //length
					wc_get_dimension($parcel_item['Height'], 'mm', 'cm'), //depth
					wc_get_weight($parcel_item['WeightDead'], 'g', 'kg'), //weight
					Rotation::BestFit //allowedRotation
				),
				$parcel_item['Quantity']
			);
		}

		try {
			$packedBoxes = $packer->pack();
		} catch (\Exception $e) {
			return $this->log_error(array('post_content' => $e->getMessage()));
		}

		$box_items = array();
		foreach ($packedBoxes as $key => $packedBox) {
			$boxType = $packedBox->box;
			$items[] = array(
				'PackageType' => 'P',
				'SatchelSize' => '',
				'Quantity' => 1,
				'WeightDead' => wc_get_weight($packedBox->getWeight(), 'kg', 'g'),
				'Length' => wc_get_dimension($boxType->getOuterLength(), 'cm', 'mm'),
				'Width' => wc_get_dimension($boxType->getOuterWidth(), 'cm', 'mm'),
				'Height' => wc_get_dimension($boxType->getOuterDepth(), 'cm', 'mm'),
			);
		}

		$items = array_values($items + $box_items);

		$request_data = array(
			'To' => array(
				'Email' => $customer_email,
				'ContactName' => $customer_name,
				'PhoneNumber' => $customer_phone,
				'Address' => array(
					'StreetAddress' => $package['destination']['address'],
					'Locality' => $package['destination']['city'],
					'StateOrProvince' => $package['destination']['state'],
					'PostalCode' => $package['destination']['postcode'],
					'Country' => $package['destination']['country'],
				)
			),

			//'Items' =>  $items
		);


		foreach ($shipping_services as $service_item) {
			unset($request_data['Services']);
			$request_data['Services'][] = array(
				'ServiceCode' => $service_item['code'],
				'ServiceItemCode' =>  $service_item['option'],
			);

			$rate_id = $this->get_key_from_array(array(
				$this->main_shipping_method->get_rate_id(),
				$service_item['code'],
				$service_item['option'],
				$service_item['suboption'],
			));

			$line_items = array();

			foreach ($items as $item) {
				$request_data['Items'] = array($item);
				$results = $this->get_rate_cache_data($rate_id, $request_data);

				if (false === $results) {
					$response = wp_remote_post('https://api.aramexconnect.com.au/api/consignments/quote', array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $this->get_access_token(),
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
						continue;
					}

					$results = json_decode(wp_remote_retrieve_body($response), true);
				}

				if (!empty($results[0]['message'])) {
					$this->log_error(array(
						'post_content' => $results[0]['message'],
						'meta_input' => array(
							'error_code' => $results[0]['code']
						)
					));

					continue;
				}

				if (!isset($results['data']['price']) || !isset($results['data']['total'])) {
					$this->log_error(array('post_content' => esc_html__('The request failed to retrieve price data from the server.', 'live-shipping-rates-australia')));
					continue;
				}

				$item['cost'] = $results['data']['price'];
				$line_items[] = $item;

				$this->set_rate_cache_data($rate_id, $request_data, $results);
			}

			if (count($packedBoxes) !== count($line_items)) {
				return $this->log_error(array(
					'post_content' => esc_html__('Packbox count and shipping cost count is not same.', 'live-shipping-rates-australia'),
				));
			}


			$total_shipping_cost = array_sum(wp_list_pluck($line_items, 'cost'));

			$shipping_cost = $this->exchange_currency($total_shipping_cost, 'AUD');
			if (false === $shipping_cost) {
				continue;
			}

			$shipping_costs = apply_filters($this->get_hook_name('shipping_costs'), array('shipping_cost' => $shipping_cost), $this, $results['data']);

			$this->debug_log(sprintf(
				/* translators: %s for shipping costs */
				esc_html__('Shipping cost breakdown: %s', 'live-shipping-rates-australia'),
				wp_json_encode($shipping_costs)
			));

			$main_shipping_method->add_rate(array(
				'cost' => array_sum($shipping_costs),
				'id' => $rate_id,
				'label' => $service_item['title'],
				'package' => $package,
				'meta_data' => array(
					'line_items' => $line_items,
					'shipping_company' => $this->get_id(),
					'description' => $service_item['description'],
					'shipping_data' => wp_json_encode($request_data),
				)
			));
		}
	}
}
