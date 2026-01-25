<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class of AramexConnect
 */
class Aramex extends Shipping_Company {

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.4
	 * @var string
	 */
	protected $company_id = 'aramex';

	/**
	 * Class name of child shipping method
	 * 
	 * @since 1.0.4
	 * @var string
	 */
	protected $shipping_method_class = Aramex_Shipping::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action($this->get_hook_name('settings_option_html'), array($this, 'insurance_fee'), 10);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'handling_charge'), 20);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_ajax_hook_name('validate_credentials'), array($this, 'validate_credentials'), 20);
	}

	/**
	 * Get name of the company
	 * 
	 * @since 1.0.4
	 * @return string
	 */
	public function get_name() {
		return esc_html__('Aramex', 'live-shipping-rates-australia');
	}

	/**
	 * Get models of settings
	 * 
	 * @since 1.0.4
	 * @return array
	 */
	protected function _get_models() {
		return array(
			'username' => '',
			'password' => '',
			'api_entity' => '',
			'api_pin' => '',
			'account_number' => '',
			'country_code' => 'AU',
		);
	}

	/**
	 * Get models of shipping method
	 * 
	 * @since 1.0.4
	 * @return array
	 */
	public function get_shipping_models() {
		return array(
			'origin_city' => WC()->countries->get_base_city(),
			'origin_postcode' => WC()->countries->get_base_postcode(),
			'origin_country' => WC()->countries->get_base_country()
		);
	}

	/**
	 * Get helper models of settings
	 * 
	 * @since 1.0.4
	 * @return array
	 */
	protected function _get_helper_models() {
		return array(
			'show_password' => false
		);
	}

	/**
	 * WSDL path of request data
	 * 
	 * @since 1.0.4
	 * @return string
	 */
	protected function get_wsdl_path() {
		return LIVE_SHIPPING_RATES_AUSTRALIA_PATH . 'inc/aramex/rates-calculator.wsdl';
	}

	/**
	 * Get error from api result
	 * 
	 * @since 1.0.4
	 * @return false|string
	 */
	protected function get_api_error($results) {
		if (!isset($results->Notifications->Notification)) {
			return false;
		}

		$first_error = null;
		if (is_array($results->Notifications->Notification)) {
			$first_error = current($results->Notifications->Notification);
		}

		if (is_object($results->Notifications->Notification)) {
			$first_error = $results->Notifications->Notification;
		}

		return isset($first_error->Message) ? $first_error->Message : false;
	}

	/**
	 * Check credentials
	 * 
	 * @since 1.0.4
	 * @return boolean
	 */
	public function check_credentials($credentials = array(), $force = false) {
		$transient_key = $this->get_key('connected');

		$connection_status = get_transient($transient_key);
		if ($connection_status && false === $force) {
			return $connection_status;
		}

		$credentials = wp_parse_args($credentials, array(
			'api_pin' => $this->get_setting('api_pin'),
			'username' => $this->get_setting('username'),
			'password' => $this->get_setting('password'),
			'api_entity' => $this->get_setting('api_entity'),
			'account_number' => $this->get_setting('account_number'),
			'country_code' => $this->get_setting('country_code'),
		));

		$request_params = array(
			'ClientInfo' => array(
				'Version' => 'v1.0',
				'UserName' => $credentials['username'],
				'Password' => $credentials['password'],
				'AccountPin' => $credentials['api_pin'],
				'AccountEntity' => $credentials['api_entity'],
				'AccountNumber' => $credentials['account_number'],
				'AccountCountryCode' => $credentials['country_code'],
			),
			'OriginAddress' => array(
				'City' => 'Sydney',
				'PostCode' => 2000,
				'CountryCode' => 'AU'
			),
			'DestinationAddress' => array(
				'City' => 'Dubai',
				'CountryCode' => 'AE'
			),

			'ShipmentDetails'  => array(
				'PaymentType' => 'P',
				'ProductGroup'   => 'EXP',
				'ProductType' => 'PPX',
				'ActualWeight'  => array(
					'Value' => 5,
					'Unit' => 'KG'
				),

				'ChargeableWeight' => array(
					'Value' => 5,
					'Unit' => 'KG'
				),
				'NumberOfPieces'  => 5
			)
		);

		if (!class_exists('\SoapClient')) {
			$this->error->add('error', esc_html__('We need the SoapClient extension to perform this action. Please install the SoapClient extension in PHP.', 'live-shipping-rates-australia'));
			return false;
		}

		try {
			$soapClient = new \SoapClient($this->get_wsdl_path(), array('trace' => 1));
			$results = $soapClient->CalculateRate($request_params);
			$get_error = $this->get_api_error($results);
		} catch (\Exception $e) {
			$get_error = $e->getMessage();
		}

		if (false !== $get_error) {
			$this->error->add('error', $get_error);
			return false;
		}

		if (isset($results->HasErrors) && $results->HasErrors) {
			$this->error->add('error', esc_html__('Something error occurred.', 'live-shipping-rates-australia'));
			return false;
		}

		set_transient($transient_key, true, 300);
		return true;
	}

	/**
	 * Validate credentials
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function validate_credentials() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_aramex_credentials', 'nonce');

		$username = isset($_POST['username']) ? trim(sanitize_text_field(wp_unslash($_POST['username']))) : '';
		if (empty($username)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter your Aramex username.', 'live-shipping-rates-australia')
			));
		}

		$password = isset($_POST['password']) ? trim(sanitize_text_field(wp_unslash($_POST['password']))) : '';
		if (empty($password)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter your Aramex password.', 'live-shipping-rates-australia')
			));
		}

		$api_entity = isset($_POST['api_entity']) ? trim(sanitize_text_field(wp_unslash($_POST['api_entity']))) : '';
		if (empty($api_entity)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter your Aramex API entity.', 'live-shipping-rates-australia')
			));
		}

		$api_pin = isset($_POST['api_pin']) ? trim(sanitize_text_field(wp_unslash($_POST['api_pin']))) : '';
		if (empty($api_pin)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter your Aramex API pin.', 'live-shipping-rates-australia')
			));
		}

		$account_number = isset($_POST['account_number']) ? trim(sanitize_text_field(wp_unslash($_POST['account_number']))) : '';
		if (empty($account_number)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter your Aramex account number.', 'live-shipping-rates-australia')
			));
		}

		$country_code = isset($_POST['country_code']) ? trim(sanitize_text_field(wp_unslash($_POST['country_code']))) : '';
		if (empty($country_code)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please select the country associated with your Aramex account.', 'live-shipping-rates-australia')
			));
		}

		$connection_status = $this->check_credentials(array(
			'api_pin' => $api_pin,
			'password' => $password,
			'username' => $username,
			'api_entity' => $api_entity,
			'country_code' => $country_code,
			'account_number' => $account_number,
		), true);

		if (false === $connection_status) {
			wp_send_json_error(array('message' => $this->error->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Successfully validated your Aramex credentials. Please save your settings.', 'live-shipping-rates-australia')
		));
	}

	/**
	 * Get connection status of aramex connect
	 * 
	 * @since 1.0.4
	 * @return boolean
	 */
	public function is_api_connected() {
		return $this->check_credentials();
	}

	/**
	 * Setting page of this shipping company
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function settings_page() {?>
		<div class="wrap">
			<h1><?php esc_html_e('Aramex Settings', 'live-shipping-rates-australia') ?></h1>
			<hr class="wp-header-end">

			<?php if (empty( $_GET['screen'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<form id="live-shipping-rates-australia-company-settings" class="wrap-live-shipping-rates-australia-settings" method="post">

					<?php do_action('live_shipping_rates_australia/before_settings_page', $this) ?>

					<?php wp_nonce_field(self::NONCE_KEY_VALUE, '_nonce_live_shipping_rates_australia') ?>
					<input type="hidden" name="<?php echo esc_attr($this->get_settings_key()) ?>" :value="get_settings_data" value="<?php echo esc_attr(wp_json_encode($this->get_settings())) ?>">

					<table class="form-table">
						<?php do_action(self::PREFIX . '/before_option_html_row', $this->get_settings(), $this); ?>

						<tr>
							<th scope="row" class="titledesc">
								<label for="username"><?php esc_html_e('Username', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input v-model="username" id="username" type="text">
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="password"><?php esc_html_e('Password', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="width-auto" v-model="password" id="password" :type="show_password ? 'text' : 'password'">
								<button class="button" v-if="!show_password" @click.prevent="show_password = true"><?php esc_html_e('Show', 'live-shipping-rates-australia') ?></button>
								<button class="button" v-if="show_password" @click.prevent="show_password = false"><?php esc_html_e('Hide', 'live-shipping-rates-australia') ?></button>
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="api_entity"><?php esc_html_e('API Entity', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="width-auto" v-model="api_entity" id="api_entity" type="text">
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="api_pin"><?php esc_html_e('API Pin', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="width-auto" v-model="api_pin" id="api_pin" type="text">
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="account_number"><?php esc_html_e('Account Number', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input class="width-auto" v-model="account_number" id="account_number" type="text">
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="country_code"><?php esc_html_e('Country', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<select v-model="country_code" id="country_code">
									<option value=""><?php esc_html_e('Select a country', 'live-shipping-rates-australia') ?></option>
									<?php foreach (WC()->countries->countries as $country_code => $country_name) : ?>
										<option value="<?php echo esc_attr($country_code) ?>"><?php echo esc_html($country_name) ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc"></th>
							<td class="forminp forminp-text" style="padding-top: 0;">
								<input type="hidden" ref="api_nonce" value="<?php echo esc_attr(wp_create_nonce('_nonce_live_shipping_rates_australia/validate_aramex_credentials')) ?>">
								<button @click.prevent="validate_aramex()" class="button"><?php esc_html_e('Validate Credentials', 'live-shipping-rates-australia') ?></button>
								<span class="live-shipping-rates-australia-loading-indicator" v-if="api_checking"></span>
								<div :class="get_api_result_class" v-if="!api_checking">{{api_message}}</div>
							</td>
						</tr>

						<?php Utils::get_connection_status_row(); ?>

						<?php Utils::get_measurements_fields() ?>


						<?php do_action($this->get_hook_name('settings_option_html'), $this->get_settings(), $this); ?>

						<?php Utils::get_debugging_row(); ?>

						<tr>
							<th style="padding-top:15px">
								<?php esc_html_e('Last Error', 'live-shipping-rates-australia') ?>
							</th>

							<td class="forminp">
								<?php Utils::get_first_error($this); ?>
							</td>
						</tr>

					</table>

					<?php submit_button() ?>
				</form>
			<?php endif; ?>

			<?php do_action('live_shipping_rates_australia/after_settings_page', $this); ?>
		</div>
	<?php
	}

	/** 
	 * Add settings field for insurance
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function insurance_fee() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label><?php esc_html_e('Insurance Fee', 'live-shipping-rates-australia') ?></label>
			</th>
			<td>

				<div class="lock-field-input">
					<input type="text" class="field-type-number">
				</div>

				<select class="width-auto">
					<?php Utils::get_fee_type_options() ?>
				</select>

				<?php Utils::get_field_note(); ?>
			</td>
		</tr>
	<?php
	}

	/** 
	 * Add settings field for handling charge
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function handling_charge() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label><?php esc_html_e('Handling Charge', 'live-shipping-rates-australia') ?></label>
			</th>
			<td>
				<div class="lock-field-input">
					<input type="text" class="field-type-number">
				</div>

				<select class="width-auto">
					<?php Utils::get_fee_type_options() ?>
				</select>

				<?php Utils::get_field_note(); ?>
			</td>
		</tr>
	<?php
	}

	/** 
	 * Add settings field for remove gst
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public function remove_gst_row() { ?>
		<tr>
			<th>
				<?php esc_html_e('Remove GST', 'live-shipping-rates-australia') ?>
			</th>

			<td>
				<label>
					<input type="checkbox" disabled>
					<?php esc_html_e('Yes', 'live-shipping-rates-australia') ?>
				</label>

				<?php Utils::get_field_note(esc_html__('Exclude GST (tax) from the rates returned by AramexConnect.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
<?php
	}
}

Main::add_shipping_company(new Aramex());
