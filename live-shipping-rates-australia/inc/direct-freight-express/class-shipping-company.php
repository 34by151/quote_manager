<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class of Direct Freight Express Shipping Company
 */
class Direct_Freight_Express extends Shipping_Company {

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $company_id = 'direct_freight_express';

	/**
	 * Class name of child shipping method
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $shipping_method_class = Direct_Freight_Express_Shipping::class;

	/**
	 * Hold product options
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	public $product_options = array(
		'rate_type'
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action($this->get_hook_name('settings_option_html'), array($this, 'insurance_fee'), 10);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'handling_charge'), 20);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'custom_fuel_levy_charge'), 5);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_ajax_hook_name('validate_api_key'), array($this, 'validate_api_key'));
	}

	/**
	 * Get name of the company
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name() {
		return esc_html__('Direct Freight Express', 'live-shipping-rates-australia');
	}

	/**
	 * Get models of settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	protected function _get_models() {
		return array(
			'api_key' => '',
			'account_number' => '',
			'default_rate_type' => 'ITEM',
		);
	}

	/**
	 * Get models of shipping method
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_shipping_models() {
		return array(
			'suburb_from' => WC()->countries->get_base_city(),
			'postcode_from' => WC()->countries->get_base_postcode()
		);
	}

	/**
	 * Get product rate types
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_rate_types() {
		return apply_filters($this->get_hook_name('rate_types'), array(
			'ITEM' => esc_html__('Item', 'live-shipping-rates-australia'),
			'PALLET' => esc_html__('Pallet', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Check API connection with server
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function check_api_connection($api_key = null, $account_number = null, $force = false) {
		if (empty($api_key)) {
			$api_key = $this->get_setting('api_key');
		}

		if (empty($account_number)) {
			$account_number = $this->get_setting('account_number');
		}

		if (empty($api_key) || empty($account_number)) {
			$this->error->add('error', esc_html__('API Key and Account missing.', 'live-shipping-rates-australia'));
			return false;
		}

		$transient_key = $this->get_key('connected');

		$connection_status = get_transient($transient_key);
		if ($connection_status && false === $force) {
			return $connection_status;
		}

		$response = wp_remote_post('https://webservices.directfreight.com.au/Dispatch/api/GetConsignmentPrice/', array(
			'headers' => array(
				'Authorisation' => $api_key,
				'AccountNumber' => $account_number,
				'Content-Type' => 'application/json'
			),

			'body' => wp_json_encode(array(
				'SuburbFrom' => 'MELBOURNE',
				'PostcodeFrom' => '3000',
				'SuburbTo' => 'SYDNEY',
				'PostcodeTo' => '2000',
				'ConsignmentLineItems' => array(array(
					'RateType' => 'ITEM',
					'Items' => 1,
					'Kgs' => 1,
					'Length' => 12,
					'Width' => 25,
					'Height' => 20,
				)),
			))
		));

		if (is_wp_error($response)) {
			$this->error = $response;
			return false;
		}

		$shipping_prices = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($shipping_prices['Message'])) {
			$this->error->add('error', $shipping_prices['Message']);
			return false;
		}

		if (!isset($shipping_prices['ResponseCode'])) {
			$this->error->add('error', esc_html__('Unknown error.', 'live-shipping-rates-australia'));
			return false;
		}

		$response_code = sanitize_text_field($shipping_prices['ResponseCode']);
		if (300 == $response_code) {
			set_transient($transient_key, true, 300);
			return true;
		}

		$this->error->add('error', $shipping_prices['ResponseMessage']);
		return false;
	}

	/**
	 * Get connection status of shipping company
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_api_connected() {
		return $this->check_api_connection();
	}

	/**
	 * Validate API key and account number
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_api_key() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_direct_freight_express', 'nonce');

		$api_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';
		$account_number = isset($_POST['account_no']) ? trim(sanitize_text_field(wp_unslash($_POST['account_no']))) : '';

		if (empty($api_key)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter API key of Direct Freight Express.', 'live-shipping-rates-australia')
			));
		}

		if (empty($account_number)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter account number of Direct Freight Express.', 'live-shipping-rates-australia')
			));
		}

		$validate_api = $this->check_api_connection($api_key, $account_number, true);
		if (false === $validate_api) {
			wp_send_json_error(array('message' => $this->error->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Successfully validated your API key and Account Number. Please save your settings.', 'live-shipping-rates-australia')
		));
	}

	/**
	 * Add product option.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_product_options($product_object) {
		$rate_type_option_name = $this->get_key('rate_type');
		woocommerce_wp_select(array(
			'id' => $rate_type_option_name,
			'label' => __('Rate Type (DFE)', 'live-shipping-rates-australia'),
			'value' => $product_object->get_meta($rate_type_option_name),
			'options' => $this->get_rate_types(),
			'desc_tip' => true,
			'description' => esc_html__('Product rate type of Direct Freight Express.', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Add variable product option.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_variable_product_options($variation, $variation_data) {
		$rate_type_option_name = $this->get_key('rate_type');
		woocommerce_wp_select(array(
			'id' => $rate_type_option_name . $variation->ID,
			'label' => __('Rate Type (DFE)', 'live-shipping-rates-australia'),
			'value' => $variation->$rate_type_option_name,
			'options' => array_merge(
				array('inherit' => esc_html__('Same as parent', 'live-shipping-rates-australia')),
				$this->get_rate_types()
			),
			'desc_tip' => true,
			'wrapper_class' => 'form-row form-row-full',
			'description' => esc_html__('Product rate type of Direct Freight Express.', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Setting page of direct freight express shipping
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() { ?>
		<div class="wrap">
			<h1><?php esc_html_e('Direct Freight Express Settings', 'live-shipping-rates-australia') ?></h1>
			<hr class="wp-header-end">

			<?php if (empty($_GET['screen'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<form id="live-shipping-rates-australia-company-settings" class="wrap-live-shipping-rates-australia-settings" method="post">

					<?php do_action('live_shipping_rates_australia/before_settings_page', $this) ?>
					<?php wp_nonce_field(self::NONCE_KEY_VALUE, '_nonce_live_shipping_rates_australia') ?>
					<input type="hidden" name="<?php echo esc_attr($this->get_settings_key()) ?>" :value="get_settings_data" value="<?php echo esc_attr(wp_json_encode($this->get_settings())) ?>">

					<table class="form-table">

						<?php do_action(self::PREFIX . '/before_option_html_row', $this->get_settings(), $this); ?>

						<tr>
							<th scope="row" class="titledesc">
								<label for="api_key"><?php esc_html_e('API Key', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input v-model="api_key" id="api_key" type="text">
								<p class="description"><?php esc_html_e('Enter the API key of Direct Freight Express.', 'live-shipping-rates-australia') ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="account_number"><?php esc_html_e('Account Number', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input v-model="account_number" id="account_number" type="text">
								<p class="description">
									<?php printf(
										/* translators: %1$s for link open, %2$s for link close */
										esc_html__('Enter the account number of Direct Freight Express. Get your account no from %1$shere%2$s.', 'live-shipping-rates-australia'),
										'<a href="https://www.directfreight.com.au/dispatch/AccountDirectDebit.aspx" target="_blank">',
										'</a>'
									) ?>
								</p>

								<div style="margin-top: 5px;"></div>
								<input type="hidden" ref="api_nonce" value="<?php echo esc_attr(wp_create_nonce('_nonce_live_shipping_rates_australia/validate_direct_freight_express')) ?>">

								<button @click.prevent="validate_direct_freight_express()" class="button"><?php esc_html_e('Validate Account', 'live-shipping-rates-australia') ?></button>
								<span class="live-shipping-rates-australia-loading-indicator" v-if="api_checking"></span>
								<div :class="get_api_result_class" v-if="!api_checking">{{api_message}}</div>
							</td>
						</tr>

						<?php Utils::get_connection_status_row(); ?>

						<?php Utils::get_measurements_fields() ?>

						<tr>
							<th scope="row" class="titledesc">
								<label for="default_rate_type">
									<?php esc_html_e('Default Rate Type', 'live-shipping-rates-australia') ?>
								</label>
							</th>
							<td class="forminp">
								<select v-model="default_rate_type" id="default_rate_type" class="width-auto">
									<?php foreach ($this->get_rate_types() as $rate_key => $rate_label) : ?>
										<option value="<?php echo esc_attr($rate_key) ?>"><?php echo esc_html($rate_label) ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Default rate type of product. You can also set the rate type on the product page.', 'live-shipping-rates-australia') ?></p>
							</td>
						</tr>

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
	 * Add settings field for custom fuel levy charge
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function custom_fuel_levy_charge() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label><?php esc_html_e('Custom Fuel Levy Charge', 'live-shipping-rates-australia') ?></label>
			</th>
			<td>
				<div class="lock-field-input">
					<input class="field-type-number" type="text">
				</div>

				<select class="width-auto">
					<?php Utils::get_fee_type_options() ?>
				</select>

				<?php Utils::get_field_note(esc_html__('Leave blank to apply fuel levy charge from Direct Freight Express website.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
	<?php
	}

	/** 
	 * Add settings field for insurance
	 * 
	 * @since 1.0.0
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
	 * @since 1.0.0
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

				<?php Utils::get_field_note(esc_html__('Exclude GST (10%) from the rates returned by Direct Freight Express.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
<?php
	}
}

Main::add_shipping_company(new Direct_Freight_Express());
