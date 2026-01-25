<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Codiepress Suggested Plugin Class
 */
final class Suggested_Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('init', array($this, 'add_conditional_shipping_payments_notice_field'));
		add_filter('woocommerce_generate_codiepress_missing_conditional_shipping_payments_html', array($this, 'codiepress_missing_conditional_shipping_payments_output'), 10);
	}

	/**
	 * Add settings field at shipping methods
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_conditional_shipping_payments_notice_field() {
		if (class_exists('\Conditional_Shipping_And_Payment\Main')) {
			return;
		}

		$methods = WC()->shipping()->load_shipping_methods();
		foreach ($methods as $method) {
			add_filter('woocommerce_shipping_instance_form_fields_' . $method->id, array($this, 'codiepress_missing_conditional_shipping_payments'), 100000);
		}
	}

	/**
	 * Add new field below shipping method settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function codiepress_missing_conditional_shipping_payments($settings) {
		$settings['codiepress_missing_conditional_shipping_payments'] = array(
			'title' => '',
			'default' => '', //Don't remove this one. Otherwise system will show error
			'type' => 'codiepress_missing_conditional_shipping_payments',
		);

		return $settings;
	}

	/**
	 * Output new field below the shipping method settings
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public function codiepress_missing_conditional_shipping_payments_output() {
		if (file_exists(WP_PLUGIN_DIR . '/conditional-shipping-and-payments-for-woocommerce/conditional-shipping-and-payments.php')) {
			$notice_title = __('Activate now', 'live-shipping-rates-australia');
			$notice_url = wp_nonce_url('plugins.php?action=activate&plugin=conditional-shipping-and-payments-for-woocommerce/conditional-shipping-and-payments.php&plugin_status=all&paged=1', 'activate-plugin_conditional-shipping-and-payments-for-woocommerce/conditional-shipping-and-payments.php');
		} else {
			$notice_title = __('Install now', 'live-shipping-rates-australia');
			$notice_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=conditional-shipping-and-payments-for-woocommerce'), 'install-plugin_conditional-shipping-and-payments-for-woocommerce');
		}

		ob_start(); ?>
		<tr>
			<td colspan="2" style="padding-inline: 0;">
				<div style="color:red">
					<?php
					printf(
						/* translators: %s: open link, %s: close link */
						esc_html__('Do you want to hide this shipping method based on the condition? The %1$sConditional Shipping and Payments for WooCommerce%2$s plugin will help you do it.', 'live-shipping-rates-australia'),
						'<a target="_blank" href="https://wordpress.org/plugins/conditional-shipping-and-payments-for-woocommerce/">',
						'</a>'
					);
					?>
					<a target="_blank" href="<?php echo esc_url($notice_url) ?>"><?php echo esc_html($notice_title) ?></a>.
				</div>
			</td>
		</tr>
<?php
		return ob_get_clean();
	}
}

new Suggested_Plugin();
