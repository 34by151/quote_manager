<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class of Sendle shipping company
 */
class Sendle extends Shipping_Company {

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.2
	 * @var string
	 */
	protected $company_id = 'sendle';

	/**
	 * Class name of child shipping method
	 * 
	 * @since 1.0.2
	 * @var string
	 */
	protected $shipping_method_class = Sendle_Shipping::class;

	/**
	 * Account type of sendle
	 * 
	 * @since 1.0.2
	 * @var string
	 */
	private $account_type = 'sandbox';

	/**
	 * Services
	 * 
	 * @since 1.0.2
	 * @var array
	 */
	protected $services = array(
		array(
			'id' => 'standard:standard-pickup',
			'code' => 'standard',
			'option' => 'STANDARD-PICKUP',
			'title' => 'Sendle - Standard Pickup',
		),

		array(
			'id' => 'standard:standard-dropoff',
			'code' => 'standard',
			'option' => 'STANDARD-DROPOFF',
			'title' => 'Sendle - Standard Dropoff',
		),

		array(
			'id' => 'express:express-pickup',
			'code' => 'express',
			'option' => 'EXPRESS-PICKUP',
			'title' => 'Sendle - Express Pickup',
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->account_type = $this->get_setting('account_type');
		add_action($this->get_hook_name('settings_option_html'), array($this, 'insurance_fee'), 10);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'handling_charge'), 20);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_ajax_hook_name('validate_api_connection'), array($this, 'validate_api_connection'));
		add_action($this->get_ajax_hook_name('check_api_status'), array($this, 'check_api_status_on_change_account_type'));
		add_filter('live_shipping_rates_australia/shipping_meta_remove_keys', array($this, 'shipping_meta_remove_keys'));
	}

	/**
	 * Get name of the company
	 * 
	 * @since 1.0.2
	 * @return string
	 */
	public function get_name() {
		return esc_html__('Sendle', 'live-shipping-rates-australia');
	}

	/**
	 * Get models of settings
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	protected function _get_models() {
		return array(
			'api_key' => '',
			'sendle_id' => '',
			'sandbox_api_key' => '',
			'sandbox_sendle_id' => '',
			'account_type' => 'sandbox',
			'services' => $this->services,
		);
	}

	/**
	 * Get models of shipping method
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	public function get_shipping_models() {
		return array(
			'sender_address_line1' => '',
			'sender_address_line2' => '',
			'sender_suburb' => '',
			'sender_postcode' => '',
			'sender_country' => 'AU',
		);
	}

	/**
	 * Remove keys of shipping meta
	 * 
	 * @since 1.0.3
	 * @return array
	 */
	public function shipping_meta_remove_keys($meta_keys) {
		return array_merge($meta_keys, array('shipping_company', 'rate_requested_data', 'product_code', 'product_service'));
	}

	/**
	 * Check if live account 
	 * 
	 * @since 1.0.2
	 * @return boolean
	 */
	public function is_live_account() {
		return 'live' === $this->account_type;
	}

	/**
	 * Get API endpoint
	 * 
	 * @since 1.0.2
	 * @return string
	 */
	public function get_api_endpoint($endpoint) {
		$base_url = 'https://sandbox.sendle.com/';
		if ($this->is_live_account()) {
			$base_url = 'https://api.sendle.com/';
		}

		return trailingslashit($base_url) . $endpoint;
	}

	/**
	 * Get sendle id
	 * 
	 * @since 1.0.2
	 * @return string
	 */
	public function get_sendle_id() {
		if ($this->is_live_account()) {
			return $this->get_setting('sendle_id');
		}

		return $this->get_setting('sandbox_sendle_id');
	}

	/**
	 * Get sendle api key
	 * 
	 * @since 1.0.2
	 * @return string
	 */
	public function get_sendle_api_key() {
		if ($this->is_live_account()) {
			return $this->get_setting('api_key');
		}

		return $this->get_setting('sandbox_api_key');
	}

	/**
	 * Check API connection with server
	 * 
	 * @since 1.0.2
	 * @return boolean
	 */
	public function check_api_connection($sendle_id = null, $api_key = null, $force = false) {
		if (empty($sendle_id)) {
			$sendle_id = $this->get_sendle_id();
		}

		if (empty($sendle_id)) {
			$this->error->add('error', esc_html__('Sendle ID of sendle is missing.', 'live-shipping-rates-australia'));
			return false;
		}

		if (empty($api_key)) {
			$api_key = $this->get_sendle_api_key();
		}

		if (empty($sendle_id)) {
			$this->error->add('error', esc_html__('API key of sendle is missing.', 'live-shipping-rates-australia'));
			return false;
		}

		$transient_key = $this->get_key('sanbox_connected');
		if ($this->is_live_account()) {
			$transient_key = $this->get_key('connected');
		}

		$connection_status = get_transient($transient_key);
		if ($connection_status && false === $force) {
			return $connection_status;
		}

		$response = wp_remote_get($this->get_api_endpoint('api/ping'), array(
			'headers' => array(
				'authorization' => 'Basic ' . base64_encode($sendle_id . ':' . $api_key)
			)
		));

		if (is_wp_error($response)) {
			$this->error = $response;
			return false;
		}

		$result = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($result['error_description'])) {
			$this->error->add('error', $result['error_description']);
			return false;
		}

		if (isset($result['ping'])) {
			set_transient($transient_key, true, 300);
			return true;
		}

		$this->error->add('error', esc_html__('Something went wrong to connect with Australia Post.', 'live-shipping-rates-australia'));
		return false;
	}

	/**
	 * Get connection status of shipping company
	 * 
	 * @since 1.0.2
	 * @return boolean
	 */
	public function is_api_connected() {
		return $this->check_api_connection();
	}

	/**
	 * Check if current company in debugging mode
	 * 
	 * @since 1.0.2
	 * @return boolean
	 */
	public function is_debugging() {
		return $this->get_setting('debugging') == true || !$this->is_live_account();
	}

	/**
	 * Validate API connection
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public function validate_api_connection() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_sendle_api', 'nonce');

		$sendle_id = isset($_POST['sendle_id']) ? trim(sanitize_text_field(wp_unslash($_POST['sendle_id']))) : '';
		if (empty($sendle_id)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter Sendle ID of Sendle.', 'live-shipping-rates-australia')
			));
		}

		$api_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';
		if (empty($api_key)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter API key of Australia Post.', 'live-shipping-rates-australia')
			));
		}

		if (!empty($_POST['account_type'])) {
			$this->account_type = sanitize_text_field(wp_unslash($_POST['account_type']));
		}

		$connection_status = $this->check_api_connection($sendle_id, $api_key, true);
		if (false === $connection_status) {
			wp_send_json_error(array('message' => $this->error->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Successfully validated your Sendle credentials. Please save your settings.', 'live-shipping-rates-australia')
		));
	}

	/**
	 * Check API status if user change account type
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public function check_api_status_on_change_account_type() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_sendle_api', 'nonce');

		$sendle_id = isset($_POST['sendle_id']) ? trim(sanitize_text_field(wp_unslash($_POST['sendle_id']))) : '';
		if (empty($sendle_id)) {
			wp_send_json_error();
		}

		$api_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';
		if (empty($api_key)) {
			wp_send_json_error();
		}

		if (!empty($_POST['account_type'])) {
			$this->account_type = sanitize_text_field(wp_unslash($_POST['account_type']));
		}

		$connection_status = $this->check_api_connection($sendle_id, $api_key, true);
		if (false === $connection_status) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Modify settings
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	public function modify_settings($settings) {
		$settings['services'] = $this->get_services('services');
		return $settings;
	}

	/**
	 * Setting page of current shipping carrier
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public function settings_page() { ?>
		<div class="wrap">
			<h1><?php esc_html_e('Sendle Settings', 'live-shipping-rates-australia') ?></h1>
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
								<label for="sendle_id"><?php esc_html_e('Account Type', 'live-shipping-rates-australia') ?></label>
							</th>
							<td>
								<select v-model="account_type">
									<option value="live"><?php esc_html_e('Live', 'live-shipping-rates-australia') ?></option>
									<option value="sandbox"><?php esc_html_e('Sandbox', 'live-shipping-rates-australia') ?></option>
								</select>
							</td>
						</tr>

						<template v-if="account_type == 'sandbox'">
							<tr>
								<th scope="row" class="titledesc">
									<label for="sandbox_sendle_id"><?php esc_html_e('Sendle ID', 'live-shipping-rates-australia') ?></label>
								</th>
								<td>
									<input v-model="sandbox_sendle_id" id="sandbox_sendle_id" type="text">

									<p class="description" v-if="account_type == 'sandbox'">
										<?php printf(
											/* translators: %1$s for link open, %2$s for link close */
											esc_html__('You can find your sandbox Sendle ID and API key %1$shere%2$s.', 'live-shipping-rates-australia'),
											'<a href="https://sendle-sandbox.herokuapp.com/dashboard/api_settings" target="_blank">',
											'</a>'
										) ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row" class="titledesc">
									<label for="sandbox_api_key"><?php esc_html_e('API Key', 'live-shipping-rates-australia') ?></label>
								</th>
								<td class="forminp forminp-text">
									<input v-model="sandbox_api_key" id="sandbox_api_key" type="text">
								</td>
							</tr>
						</template>

						<template v-if="account_type == 'live'">
							<tr>
								<th scope="row" class="titledesc">
									<label for="sendle_id"><?php esc_html_e('Sendle ID', 'live-shipping-rates-australia') ?></label>
								</th>
								<td>
									<input v-model="sendle_id" id="sendle_id" type="text">
									<p class="description">
										<?php printf(
											/* translators: %1$s for link open, %2$s for link close */
											esc_html__('You can find your live Sendle ID and API key %1$shere%2$s.', 'live-shipping-rates-australia'),
											'<a href="https://app.sendle.com/dashboard/api_settings" target="_blank">',
											'</a>'
										) ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row" class="titledesc">
									<label for="api_key"><?php esc_html_e('API Key', 'live-shipping-rates-australia') ?></label>
								</th>
								<td class="forminp forminp-text">
									<input v-model="api_key" id="api_key" type="text">
								</td>
							</tr>
						</template>

						<tr>
							<th scope="row" class="titledesc"></th>
							<td class="forminp forminp-text" style="padding-top: 0;">
								<input type="hidden" ref="api_nonce" value="<?php echo esc_attr(wp_create_nonce('_nonce_live_shipping_rates_australia/validate_sendle_api')) ?>">
								<button @click.prevent="validate_sendle()" class="button"><?php esc_html_e('Check API Status', 'live-shipping-rates-australia') ?></button>
								<span class="live-shipping-rates-australia-loading-indicator" v-if="api_checking"></span>
								<div :class="get_api_result_class" v-if="!api_checking">{{api_message}}</div>
							</td>
						</tr>

						<?php Utils::get_connection_status_row(); ?>

						<?php Utils::get_measurements_fields(); ?>

						<tr>
							<th>
								<?php esc_html_e('Services', 'live-shipping-rates-australia') ?>
							</th>

							<td class="forminp">
								<?php Utils::get_services_table('services') ?>
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
	 * Add settings field for insurance
	 * 
	 * @since 1.0.2
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
	 * @since 1.0.2
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

				<?php Utils::get_field_note(esc_html__('Exclude GST (tax) from the rates returned by Sendle.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
<?php
	}
}

Main::add_shipping_company(new Sendle());
