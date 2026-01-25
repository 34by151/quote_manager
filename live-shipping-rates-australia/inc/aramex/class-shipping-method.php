<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shipping method class of AramexConnect
 */
final class Aramex_Shipping extends Aramex {

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
			<th><?php esc_html_e('City', 'live-shipping-rates-australia') ?></th>
			<td>
				<input v-model="origin_city" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Please enter the city where your parcel will be shipped from.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Postcode', 'live-shipping-rates-australia')
				?></th>
			<td>
				<input v-model="origin_postcode" type="text" required>
				<p class="field-note">
					<?php esc_html_e('Enter the postcode of the location from which you want to ship your parcel.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e('Country', 'live-shipping-rates-australia') ?></th>
			<td>
				<select v-model="origin_country" id="origin_country">
					<option value=""><?php esc_html_e('Select a country', 'live-shipping-rates-australia') ?></option>
					<?php foreach (WC()->countries->countries as $country_code => $country_name) : ?>
						<option value="<?php echo esc_attr($country_code) ?>"><?php echo esc_html($country_name) ?></option>
					<?php endforeach; ?>
				</select>
				<p class="field-note">
					<?php esc_html_e('Enter the country from where your parcel will be shipped.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>
<?php
	}

	/**
	 * Check if current shipping rate available
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available($package) {
		if (!class_exists('\SoapClient')) {
			$this->log_error(array(
				'post_content' => esc_html__('We need the SoapClient extension to perform this action. Please install the SoapClient extension in PHP.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'soap_client'
				)
			));

			return false;
		}

		$username = $this->get_setting('username');
		if (empty($username)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter your Aramex username.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$password = $this->get_setting('password');
		if (empty($password)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter your Aramex password.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$api_entity = $this->get_setting('api_entity');
		if (empty($api_entity)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter your Aramex API entity.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$api_pin = $this->get_setting('api_pin');
		if (empty($api_pin)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter your Aramex API pin.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$account_number = $this->get_setting('account_number');
		if (empty($account_number)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter your Aramex account number.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$country_code = $this->get_setting('country_code');
		if (empty($country_code)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please select the country associated with your Aramex account.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$origin_city = $this->get_option('origin_city');
		if (empty($origin_city)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter the city where your parcel will be shipped from on the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$origin_postcode = $this->get_option('origin_postcode');
		if (empty($origin_postcode)) {
			$this->log_error(array(
				'post_content' => esc_html__('Please enter the postcode where your parcel will be shipped from on the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$origin_country = $this->get_option('origin_country');
		if (empty($origin_country)) {
			$this->log_error(array(
				'post_content' => esc_html__('Enter the country from where your parcel will be shipped on the shipping method settings.', 'live-shipping-rates-australia'),
				'meta_input' => array(
					'error_code' => 'shipping_method_settings'
				)
			));

			return false;
		}

		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();

		if (empty($shipping_address['city'])) {
			$this->debug_log(esc_html__('Please enter your city on the checkout page.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($shipping_address['country'])) {
			$this->debug_log(esc_html__('Please select your country on the checkout page.', 'live-shipping-rates-australia'));
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
		$measurements = Utils::get_cart_total_dimensions_and_weight($package['contents'], $this);

		$shipping_address = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : array();
		$request_data = $requested_safe_data = array(
			'ClientInfo' => array(
				'Version' => 'v1.0',
				'Password' => $this->get_setting('password'),
				'UserName' => $this->get_setting('username'),
				'AccountEntity' => $this->get_setting('api_entity'),
				'AccountNumber' => $this->get_setting('account_number'),
				'AccountPin' => $this->get_setting('api_pin'),
				'AccountCountryCode' => $this->get_setting('country_code'),
			),

			'OriginAddress' => array(
				'City' => $this->get_option('origin_city'),
				'PostCode' => $this->get_option('origin_postcode'),
				'CountryCode' => $this->get_option('origin_country'),
			),

			'DestinationAddress' => array(
				'City' => $shipping_address['city'],
				'PostCode' => $shipping_address['postcode'],
				'CountryCode' => $shipping_address['country']
			),

			'ShipmentDetails'  => array(
				'PaymentType' => 'P',
				'ProductGroup'   => 'EXP',
				'ProductType' => 'PPX',
				'ActualWeight'  => array(
					'Value' => $measurements['weight'],
					'Unit' => 'KG'
				),
				'ChargeableWeight' => array(
					'Value' => $measurements['weight'],
					'Unit' => 'KG'
				),
				'NumberOfPieces'  => 1
			)
		);

		unset($requested_safe_data['ClientInfo']);
		$rate_id = $this->get_key_from_array(array($this->main_shipping_method->get_rate_id()));
		$results = $this->get_rate_cache_data($rate_id, $requested_safe_data);

		if (false === $results) {
			try {
				$soapClient = new \SoapClient($this->get_wsdl_path(), array('trace' => 1));
				$results = $soapClient->CalculateRate($request_data);
				$get_error = $this->get_api_error($results);
			} catch (\Exception $e) {
				$get_error = $e->getMessage();
			}

			$get_error = $this->get_api_error($results);
			if (false !== $get_error) {
				return $this->log_error(array('post_content' => $get_error));
			}
		}


		if (!isset($results->TotalAmount)) {
			return;
		}

		$this->set_rate_cache_data($rate_id, $requested_safe_data, $results);

		$shipping_cost = $this->exchange_currency($results->TotalAmount->Value, $results->TotalAmount->CurrencyCode);
		if (false === $shipping_cost) {
			return;
		}

		$shipping_costs = apply_filters($this->get_hook_name('shipping_costs'), array('shipping_cost' => $shipping_cost), $this, $results);

		$this->debug_log(sprintf(
			/* translators: %s for shipping costs */
			esc_html__('Shipping cost breakdown: %s', 'live-shipping-rates-australia'),
			wp_json_encode($shipping_costs)
		));

		$main_shipping_method->add_rate(array(
			'cost' => array_sum($shipping_costs),
			'id' => $rate_id,
			'label' => $main_shipping_method->title,
			'package' => $package,
			'meta_data' => array(
				'shipping_company' => $this->get_id(),
				'shipping_data' => wp_json_encode($requested_safe_data),
			)
		));
	}
}
