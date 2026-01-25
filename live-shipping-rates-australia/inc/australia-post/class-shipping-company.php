<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class of Ausralia Post Shipping Company
 */
class Australia_Post extends Shipping_Company {

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $company_id = 'australia_post';

	/**
	 * Class name of child shipping method
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $shipping_method_class = Australia_Post_Shipping::class;

	/**
	 * Domestic services
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	protected $domestic_services = array(
		array(
			'id' => 'aus_parcel_regular:aus_service_option_standard',
			'code' => 'AUS_PARCEL_REGULAR',
			'option' => 'AUS_SERVICE_OPTION_STANDARD',
			'title' => 'Australia Post - Parcel Post (Standard Service)',
		),

		array(
			'id' => 'aus_parcel_regular:aus_service_option_signature_on_delivery',
			'code' => 'AUS_PARCEL_REGULAR',
			'option' => 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY',
			'title' => 'Australia Post - Parcel Post (Signature on Delivery)',
		),

		array(
			'id' => 'aus_parcel_express:aus_service_option_standard',
			'code' => 'AUS_PARCEL_EXPRESS',
			'option' => 'AUS_SERVICE_OPTION_STANDARD',
			'title' => 'Australia Post - Express Post (Standard Service)',
		),

		array(
			'id' => 'aus_parcel_express:aus_service_option_signature_on_delivery',
			'code' => 'AUS_PARCEL_EXPRESS',
			'option' => 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY',
			'title' => 'Australia Post - Express Post (Signature on Delivery)',
		)
	);

	/**
	 * Inernational services
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	protected $international_services = array(
		array(
			'id' => 'int_parcel_cor_own_packaging:int_tracking',
			'code' => 'INT_PARCEL_COR_OWN_PACKAGING',
			'option' => 'INT_TRACKING',
			'title' => 'Australia Post International - Courier (Tracking)',
		),

		array(
			'id' => 'int_parcel_cor_own_packaging:int_sms_track_advice',
			'code' => 'INT_PARCEL_COR_OWN_PACKAGING',
			'option' => 'INT_SMS_TRACK_ADVICE',
			'title' => 'Australia Post International - Courier (SMS track advice)',
		),

		array(
			'id' => 'int_parcel_cor_own_packaging:int_extra_cover',
			'code' => 'INT_PARCEL_COR_OWN_PACKAGING',
			'option' => 'INT_EXTRA_COVER',
			'title' => 'Australia Post International - Courier (Extra Cover)',
		),

		array(
			'id' => 'int_parcel_exp_own_packaging:int_tracking',
			'code' => 'INT_PARCEL_EXP_OWN_PACKAGING',
			'option' => 'INT_TRACKING',
			'title' => 'Australia Post International - Express (Tracking)',
		),

		array(
			'id' => 'int_parcel_exp_own_packaging:int_signature_on_delivery',
			'code' => 'INT_PARCEL_EXP_OWN_PACKAGING',
			'option' => 'INT_SIGNATURE_ON_DELIVERY',
			'title' => 'Australia Post International - Express (Signature on delivery)',
		),

		array(
			'id' => 'int_parcel_exp_own_packaging:int_sms_track_advice',
			'code' => 'INT_PARCEL_EXP_OWN_PACKAGING',
			'option' => 'INT_SMS_TRACK_ADVICE',
			'title' => 'Australia Post International - Express (SMS track advice)',
		),

		array(
			'id' => 'int_parcel_exp_own_packaging:int_extra_cover',
			'code' => 'INT_PARCEL_EXP_OWN_PACKAGING',
			'option' => 'INT_EXTRA_COVER',
			'title' => 'Australia Post International - Express (Extra Cover)',
		),

		array(
			'id' => 'int_parcel_std_own_packaging:int_tracking',
			'code' => 'INT_PARCEL_STD_OWN_PACKAGING',
			'option' => 'INT_TRACKING',
			'title' => 'Australia Post International - Standard (Tracking)',
		),

		array(
			'id' => 'int_parcel_std_own_packaging:int_extra_cover',
			'code' => 'INT_PARCEL_STD_OWN_PACKAGING',
			'option' => 'INT_EXTRA_COVER',
			'title' => 'Australia Post International - Standard (Extra Cover)',
		),

		array(
			'id' => 'int_parcel_std_own_packaging:int_signature_on_delivery',
			'code' => 'INT_PARCEL_STD_OWN_PACKAGING',
			'option' => 'INT_SIGNATURE_ON_DELIVERY',
			'title' => 'Australia Post International - Standard (Signature on delivery)',
		),

		array(
			'id' => 'int_parcel_std_own_packaging:int_sms_track_advice',
			'code' => 'INT_PARCEL_STD_OWN_PACKAGING',
			'option' => 'INT_SMS_TRACK_ADVICE',
			'title' => 'Australia Post International - Standard (SMS track advice)',
		)
	);


	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action($this->get_hook_name('settings_option_html'), array($this, 'insurance_fee'), 10);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'handling_charge'), 20);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_ajax_hook_name('validate_api_connection'), array($this, 'validate_api_connection'));
		add_filter('live_shipping_rates_australia/shipping_meta_remove_keys', array($this, 'shipping_meta_remove_keys'));
	}

	/**
	 * Get name of the company
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name() {
		return esc_html__('Australia Post', 'live-shipping-rates-australia');
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
			'show_delivery_time' => '',
			'domestic_services' => $this->domestic_services,
			'international_services' => $this->international_services
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
			'from_postcode' => WC()->countries->get_base_postcode()
		);
	}

	/**
	 * Remove keys of shipping meta
	 * 
	 * @since 1.0.3
	 * @return array
	 */
	public function shipping_meta_remove_keys($meta_keys) {
		return array_merge($meta_keys, array('service_code', 'service_option', 'suboption_code'));
	}

	/**
	 * Modify settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function modify_settings($settings) {
		$settings['domestic_services'] = $this->get_services('domestic_services');
		$settings['international_services'] = $this->get_services('international_services');
		return $settings;
	}

	/**
	 * Check API connection with server
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function check_api_connection($api_key = null, $force = false) {
		if (empty($api_key)) {
			$api_key = $this->get_setting('api_key');
		}

		if (empty($api_key)) {
			$this->error->add('error', esc_html__('API key missing.', 'live-shipping-rates-australia'));
			return false;
		}

		$transient_key = $this->get_key('connected');

		$connection_status = get_transient($transient_key);
		if ($connection_status && false === $force) {
			return $connection_status;
		}

		$response = wp_remote_get('https://digitalapi.auspost.com.au/postage/parcel/domestic/calculate', array(
			'headers' => array(
				'auth-key' => $api_key,
			),

			'body' => array(
				'from_postcode' => '2000',
				'to_postcode' => '2000',
				'length' => '2',
				'width' => '2',
				'height' => '2',
				'weight' => '2',
				'service_code' => 'AUS_PARCEL_REGULAR',
			)
		));

		if (is_wp_error($response)) {
			$this->error = $response;
			return false;
		}

		$results = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($results['error']['errorMessage'])) {
			$this->error->add('error', $results['error']['errorMessage']);
			return false;
		}

		if (isset($results['postage_result']['total_cost'])) {
			set_transient($transient_key, true, 300);
			return true;
		}

		$this->error->add('error', esc_html__('Something went wrong to connect with Australia Post.', 'live-shipping-rates-australia'));
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
	 * Validate API connection
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_api_connection() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_australia_post', 'nonce');

		$api_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';
		if (empty($api_key)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter API key of Australia Post.', 'live-shipping-rates-australia')
			));
		}

		$connection_status = $this->check_api_connection($api_key, true);
		if (false === $connection_status) {
			wp_send_json_error(array('message' => $this->error->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Successfully validated your API key. Please save your settings.', 'live-shipping-rates-australia')
		));
	}

	/**
	 * Setting page of current shipping carrier
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() { ?>
		<div class="wrap">
			<h1><?php esc_html_e('Australia Post Settings', 'live-shipping-rates-australia') ?></h1>
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
								<p class="description">
									<?php printf(
										/* translators: %1$s for link open, %2$s for link close */
										esc_html__('Enter the API Key of Australia Post. Get the API Key from %1$shere%2$s.', 'live-shipping-rates-australia'),
										'<a href="https://developers.auspost.com.au/apis/pacpcs-registration" target="_blank">',
										'</a>'
									) ?>
								</p>

								<div style="margin-top: 5px;"></div>

								<input type="hidden" ref="api_nonce" value="<?php echo esc_attr(wp_create_nonce('_nonce_live_shipping_rates_australia/validate_australia_post')) ?>">
								<button @click.prevent="validate_australia_post()" class="button"><?php esc_html_e('Validate API Key', 'live-shipping-rates-australia') ?></button>
								<span class="live-shipping-rates-australia-loading-indicator" v-if="api_checking"></span>
								<div :class="get_api_result_class" v-if="!api_checking">{{api_message}}</div>
							</td>
						</tr>

						<?php Utils::get_connection_status_row(); ?>

						<?php Utils::get_measurements_fields(); ?>

						<tr>
							<th>
								<?php esc_html_e('Domestic Services', 'live-shipping-rates-australia') ?>
							</th>

							<td class="forminp">
								<?php Utils::get_services_table('domestic_services') ?>
							</td>
						</tr>

						<tr>
							<th>
								<?php esc_html_e('International Services', 'live-shipping-rates-australia') ?>
							</th>

							<td class="forminp">
								<?php Utils::get_services_table('international_services') ?>
							</td>
						</tr>

						<tr>
							<th>
								<label for="show_delivery_time">
									<?php esc_html_e('Show Delivery Time', 'live-shipping-rates-australia') ?>
								</label>
							</th>

							<td>
								<select v-model="show_delivery_time" id="show_delivery_time" class="width-auto">
									<option value=""><?php esc_html_e('Don\'t show', 'live-shipping-rates-australia') ?></option>
									<option value="title"><?php esc_html_e('In title', 'live-shipping-rates-australia') ?></option>
									<option value="description"><?php esc_html_e('At description', 'live-shipping-rates-australia') ?></option>
								</select>

								<p v-if="show_delivery_time == 'title'" class="field-note">
									<?php esc_html_e('The delivery time will be displayed within the title.', 'live-shipping-rates-australia') ?>
								</p>

								<p v-if="show_delivery_time == 'description'" class="field-note" style="max-width: 800px;">
									<?php esc_html_e('The display of the shipping rate description depends on the active theme. If the theme does not support or include a shipping rate description, it will not be displayed.', 'live-shipping-rates-australia') ?>
								</p>

								<p class="field-note">
									<?php printf(
										/* translators: %1$s for delivery time placeholder */
										esc_html__('You can also use %s placeholder to show delivery time within title or description.', 'live-shipping-rates-australia'),
										'<code style="user-select: all">{delivery_time}</code>'
									) ?>
								</p>
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
					<option value="fixed_amount"><?php esc_html_e('Fixed Amount', 'live-shipping-rates-australia') ?></option>
					<option value="percentage"><?php esc_html_e('Percentage', 'live-shipping-rates-australia') ?></option>
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

				<?php Utils::get_field_note(esc_html__('Exclude GST (tax) from the rates returned by Australia Post.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
<?php
	}
}

Main::add_shipping_company(new Australia_Post());
