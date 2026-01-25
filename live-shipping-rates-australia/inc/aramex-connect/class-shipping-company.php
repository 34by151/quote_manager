<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class of AramexConnect
 */
class Aramex_Connect extends Shipping_Company {

	/**
	 * Hold the company slug of shipping company
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $company_id = 'aramex_connect';

	/**
	 * Class name of child shipping method
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $shipping_method_class = Aramex_Connect_Shipping::class;

	/**
	 * Hold product options
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	public $product_options = array(
		'package_type',
		'satchel_size',
	);

	/**
	 * Domestic services
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	protected $domestic_services = array(
		array(
			'id' => 'priority:priority',
			'code' => 'PRIORITY',
			'option' => 'PRIORITY',
			'title' => 'Aramex Connect - Priority',
		),

		array(
			'id' => 'delopt:atl',
			'code' => 'DELOPT',
			'option' => 'ATL',
			'title' => 'Aramex Connect - Leave at Door',
		),

		array(
			'id' => 'delopt:stn',
			'code' => 'DELOPT',
			'option' => 'STN',
			'title' => 'Aramex Connect - Standard Service',
		),

		array(
			'id' => 'delopt:sgr',
			'code' => 'DELOPT',
			'option' => 'SGR',
			'title' => 'Aramex Connect - Signature Required',
		)
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action($this->get_hook_name('settings_option_html'), array($this, 'combine_cart_items'), 1);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'aramex_service'), 8);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'insurance_fee'), 10);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'handling_charge'), 20);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_hook_name('settings_option_html'), array($this, 'remove_gst_row'), 50);
		add_action($this->get_ajax_hook_name('validate_credentials'), array($this, 'validate_credentials'), 20);
	}

	/**
	 * Get name of the company
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name() {
		return esc_html__('AramexConnect', 'live-shipping-rates-australia');
	}

	/**
	 * Get package types
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_package_types() {
		return apply_filters($this->get_hook_name('package_types'), array(
			'P' => esc_html__('Parcel', 'live-shipping-rates-australia'),
			'S' => esc_html__('Satchel', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Get satchel sizes
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function get_satchel_sizes() {
		return apply_filters($this->get_hook_name('satchel_sizes'), array(
			'300gm' => esc_html__('300gm', 'live-shipping-rates-australia'),
			'A2' => esc_html__('A2 (42.0 x 59.4cm)', 'live-shipping-rates-australia'),
			'A3' => esc_html__('A3 (29.7 x 42.0cm)', 'live-shipping-rates-australia'),
			'A4' => esc_html__('A4 (21.0 x 29.7cm)', 'live-shipping-rates-australia'),
			'A5' => esc_html__('A5 (14.8 x 21.0cm)', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Get models of settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	protected function _get_models() {
		return array(
			'client_id' => '',
			'client_secret' => '',
			'default_package_type' => 'P',
			'default_satchel_size' => '300gm',
			'domestic_services' => $this->domestic_services,
		);
	}

	/**
	 * Modify settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function modify_settings($settings) {
		$settings['domestic_services'] = $this->get_services('domestic_services');
		return $settings;
	}

	/**
	 * Get connection status of aramex connect
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_api_connected() {
		return $this->get_access_token() !== false;
	}

	/**
	 * Get access token from transient. If not found then get token from aramex
	 * 
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_access_token($client_id = null, $client_secret = null, $force = false) {
		if (is_null($client_id)) {
			$client_id = $this->get_setting('client_id');
		}

		if (is_null($client_secret)) {
			$client_secret = $this->get_setting('client_secret');
		}

		if (empty($client_id) || empty($client_secret)) {
			$this->error->add('error', esc_html__('Client ID and Secret missing.', 'live-shipping-rates-australia'));
			return false;
		}

		$transient_key = $this->get_key('token');

		$token = get_transient($transient_key);
		if (false !== $token && false === $force) {
			return $token;
		}

		$response = wp_remote_post('https://identity.aramexconnect.com.au/connect/token', array(
			'body' => array(
				'scope' => 'ac-api-au',
				'grant_type' => 'client_credentials',
				'client_id' => $client_id,
				'client_secret' => $client_secret,
			)
		));

		if (is_wp_error($response)) {
			$this->error = $response;
			return false;
		}

		$results = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($results['error'])) {
			$this->error->add('error', $results['error']);
			return false;
		}

		if (!isset($results['access_token'])) {
			$this->error->add('error', esc_html__('We failed to retrive access token. Please check your credentials and try again', 'live-shipping-rates-australia'));
			return false;
		}

		set_transient($transient_key, $results['access_token'], $results['expires_in'] - 100);
		return $results['access_token'];
	}


	/**
	 * Validate aramex connect credentials
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_credentials() {
		check_ajax_referer('_nonce_live_shipping_rates_australia/validate_aramex_connect', 'nonce');

		$client_id = isset($_POST['client_id']) ? trim(sanitize_text_field(wp_unslash($_POST['client_id']))) : '';
		$client_secret = isset($_POST['client_secret']) ? trim(sanitize_text_field(wp_unslash($_POST['client_secret']))) : '';

		if (empty($client_id)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter Client ID of AramexConnect.', 'live-shipping-rates-australia')
			));
		}

		if (empty($client_secret)) {
			wp_send_json_error(array(
				'message' => esc_html__('Please enter Client Secret of AramexConnect.', 'live-shipping-rates-australia')
			));
		}

		$token = $this->get_access_token($client_id, $client_secret, true);
		if (false === $token) {
			wp_send_json_error(array('message' => $this->error->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Successfully validated your AramexConnect credentials. Please save your settings.', 'live-shipping-rates-australia')
		));
	}

	/**
	 * Add product option.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_product_options($product_object) {
		$package_type_meta_key = $this->get_key('package_type');
		woocommerce_wp_select(array(
			'id' => $package_type_meta_key,
			'label' => __('Package Type (AC)', 'live-shipping-rates-australia'),
			'value' => $product_object->get_meta($package_type_meta_key),
			'options' => $this->get_package_types(),
			'desc_tip' => true,
			'class' => 'aramex-connect-package-type short',
			'description' => esc_html__('Package type of Aramex Connect.', 'live-shipping-rates-australia'),
		));

		$satchel_size_meta_key = $this->get_key('satchel_size');
		woocommerce_wp_select(array(
			'id' => $satchel_size_meta_key,
			'label' => __('Satchel Size', 'live-shipping-rates-australia'),
			'value' => $product_object->get_meta($satchel_size_meta_key),
			'options' => $this->get_satchel_sizes(),
			'desc_tip' => true,
			'description' => esc_html__('Satchel Size of Aramex Connect.', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Add variable product option.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_variable_product_options($variation, $variation_data) {
		$package_type_meta_key = $this->get_key('package_type');

		woocommerce_wp_select(array(
			'id' => $package_type_meta_key . $variation->ID,
			'label' => __('Package Type (AC)', 'live-shipping-rates-australia'),
			'value' => $variation->$package_type_meta_key,
			'options' => array_merge(
				array('inherit' => esc_html__('Same as parent', 'live-shipping-rates-australia')),
				$this->get_package_types()
			),
			'desc_tip' => true,
			'wrapper_class' => 'form-row form-row-full',
			'description' => esc_html__('Package type of Aramex Connect.', 'live-shipping-rates-australia'),
			'class' => 'aramex-connect-package-type',
			'custom_attributes' => array(
				'data-variation-id' => $variation->ID
			),
		));

		$satchel_size_meta_key = $this->get_key('satchel_size');
		woocommerce_wp_select(array(
			'id' => $satchel_size_meta_key . $variation->ID,
			'label' => __('Satchel Size', 'live-shipping-rates-australia'),
			'value' => $variation->$satchel_size_meta_key,
			'options' => array_merge(
				array('inherit' => esc_html__('Same as parent', 'live-shipping-rates-australia')),
				$this->get_satchel_sizes()
			),
			'desc_tip' => true,
			'wrapper_class' => 'form-row form-row-full',
			'description' => esc_html__('Satchel Size of Aramex Connect.', 'live-shipping-rates-australia'),
		));
	}

	/**
	 * Setting page of this shipping company
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() { ?>
		<div class="wrap">
			<h1><?php esc_html_e('AramexConnect Settings', 'live-shipping-rates-australia') ?></h1>
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
								<label for="client_id"><?php esc_html_e('Client ID', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input v-model="client_id" id="client_id" type="text">
								<p class="description">
									<?php printf(
										/* translators: %1$s for link open, %2$s for link close */
										esc_html__('Enter the Client ID of Aramex Connect. You can collect Client ID from %1$shere%2$s.', 'live-shipping-rates-australia'),
										'<a href="https://aramexconnect.com.au/#/admin/api-keys/list" target="_blank">',
										'</a>'
									) ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="client_secret"><?php esc_html_e('Client Secret', 'live-shipping-rates-australia') ?></label>
							</th>
							<td class="forminp forminp-text">
								<input v-model="client_secret" id="client_secret" type="text">
								<p class="description">
									<?php printf(
										/* translators: %1$s for link open, %2$s for link close */
										esc_html__('Enter the Client Secret of Aramex Connect. You can collect Client Secret from %1$shere%2$s.', 'live-shipping-rates-australia'),
										'<a href="https://aramexconnect.com.au/#/admin/api-keys/list" target="_blank">',
										'</a>'
									) ?>
								</p>

								<div style="margin-top: 5px;"></div>
								<input type="hidden" ref="api_nonce" value="<?php echo esc_attr(wp_create_nonce('_nonce_live_shipping_rates_australia/validate_aramex_connect')) ?>">
								<button @click.prevent="validate_aramex_connect()" class="button"><?php esc_html_e('Validate Account', 'live-shipping-rates-australia') ?></button>
								<span class="live-shipping-rates-australia-loading-indicator" v-if="api_checking"></span>
								<div :class="get_api_result_class" v-if="!api_checking">{{api_message}}</div>
							</td>
						</tr>

						<?php Utils::get_connection_status_row(); ?>

						<?php Utils::get_measurements_fields() ?>

						<tr>
							<th scope="row" class="titledesc">
								<label for="default_package_type">
									<?php esc_html_e('Default Package Type', 'live-shipping-rates-australia') ?>
								</label>
							</th>
							<td class="forminp">
								<select v-model="default_package_type" id="default_package_type" class="width-auto">
									<?php foreach ($this->get_package_types() as $package_key => $package_label) : ?>
										<option value="<?php echo esc_attr($package_key) ?>"><?php echo esc_html($package_label) ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Default package type of product. You can also set the package type on the product page.', 'live-shipping-rates-australia') ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row" class="titledesc">
								<label for="default_satchel_size">
									<?php esc_html_e('Default Satchel Size', 'live-shipping-rates-australia') ?>
								</label>
							</th>
							<td class="forminp">
								<select v-model="default_satchel_size" id="default_satchel_size" class="width-auto">
									<?php foreach ($this->get_satchel_sizes() as $satchel_size_key => $satchel_size_label) : ?>
										<option value="<?php echo esc_attr($satchel_size_key) ?>"><?php echo esc_html($satchel_size_label) ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Default satchel size of product. You can also set the satchel size on the product page.', 'live-shipping-rates-australia') ?></p>
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
	 * Add settings field for combined product
	 * 
	 * @since 1.0.6
	 * @return void
	 */
	public function combine_cart_items() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label><?php esc_html_e('Combine Cart Items', 'live-shipping-rates-australia') ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" disabled>
					<?php esc_html_e('Yes', 'live-shipping-rates-australia') ?>
				</label>

				<?php Utils::get_field_note(esc_html__('It will calculate the total value of all cart items and return the shipping rate.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
	<?php
	}

	/** 
	 * Add settings field for services
	 * 
	 * @since 1.0.6
	 * @return void
	 */
	public function aramex_service() { ?>
		<tr>
			<th>
				<?php esc_html_e('Services', 'live-shipping-rates-australia') ?>
			</th>

			<td class="forminp">
				<?php Utils::get_services_table('domestic_services') ?>
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

				<?php Utils::get_field_note(esc_html__('Exclude GST (tax) from the rates returned by AramexConnect.', 'live-shipping-rates-australia')); ?>
			</td>
		</tr>
<?php
	}
}

Main::add_shipping_company(new Aramex_Connect());
