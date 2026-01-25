<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin class of the plugin
 */
final class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('add_meta_boxes', array($this, 'order_meta_boxes'));
		add_action('admin_enqueue_scripts', array($this, 'register_scripts'), 1);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 100);
		add_action('admin_enqueue_scripts', array($this, 'product_enqueue_scripts'));
		add_filter('woocommerce_generate_live_shipping_rates_australia_settings_html', array($this, 'add_shipping_method_settings'), 100, 4);

		add_action('woocommerce_process_product_meta', array($this, 'handle_save_product_data'));
		add_action('woocommerce_product_options_shipping_product_data', array($this, 'add_product_options'));

		add_action('woocommerce_save_product_variation', [$this, 'save_variable_product']);
		add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variable_product_options'), 10, 3);

		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('woocommerce_order_item_get_formatted_meta_data', array($this, 'remove_shipping_meta_data'));
		add_action('live_shipping_rates_australia/before_settings_page', array($this, 'show_missing_method_notice'));
	}

	/**
	 * Get global product options
	 * 
	 * @since 1.1.1
	 * @return array
	 */
	public function get_global_product_options() {
		return apply_filters('live_shipping_rates_australia/global_product_options', array(
			'lsra_quantity_in_product' => array(
				'priority' => 10,
				'callback' => array($this, 'quantity_in_product_product_option')
			)
		));
	}

	/**
	 * Register meta boxes for order
	 * 
	 * @since 1.0.3
	 * @return void
	 */
	public function order_meta_boxes() {
		$order_screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

		add_meta_box(
			'live-shipping-rates-australia-order-metabox',
			__('Live Shipping Rates Australia', 'live-shipping-rates-australia'),
			array($this, 'order_metabox'),
			$order_screen,
			'side',
			'high'
		);
	}

	/**
	 * Order meta box
	 * 
	 * @since 1.0.3
	 * @return void
	 */
	public function order_metabox($order) {
		if (!is_a($order, '\Automattic\WooCommerce\Admin\Overrides\Order')) {
			return;
		}

		$shipping_methods = $order->get_shipping_methods();

		if (!is_array($shipping_methods) || count($shipping_methods) == 0) {
			return;
		}

		$order_shipping = current($shipping_methods);
		if ('live_shipping_rates_australia' !== $order_shipping->get_method_id()) {
			echo '<p class="other-company-notice">' . esc_html__('This order does not include the Live Shipping Rates Australia shipping method.', 'live-shipping-rates-australia') . '</p>';
			return;
		}

		$company_id = $order_shipping->get_meta('shipping_company');

		$shipping_companies = Main::get_shipping_companies();
		if (!isset($shipping_companies[$company_id])) {
			echo '<p class="other-company-notice">' . esc_html__('The shipping company could not be found in our system.', 'live-shipping-rates-australia') . '</p>';
			return;
		}

		echo '<div class="meta-info">';
		echo '<small>' . esc_html__('Shipping Company:', 'live-shipping-rates-australia') . '</small>';
		echo esc_html($shipping_companies[$company_id]->get_name());
		echo '</div>';

		$shipping_method = new Shipping_Method($order_shipping->get_instance_id());
		$child_method = $shipping_companies[$company_id]->get_child_shipping_method($shipping_method);

		if (method_exists($child_method, 'order_metabox')) {
			$child_method->order_metabox($order_shipping, $order->get_id());
		}
	}

	/**
	 * Register and scripts
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function register_scripts() {
		if (defined('CODIEPRESS_DEVELOPMENT')) {
			wp_register_script('live-shipping-rates-australia-vue', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/vue.js', [], '3.5.13', true);
		} else {
			wp_register_script('live-shipping-rates-australia-vue', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/vue.min.js', [], '3.5.13', true);
		}
	}

	/**
	 * Enqueue script on backend
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		$is_targetted_page = false;
		if (str_contains(get_current_screen()->id, 'page_codiepress_') || 'toplevel_page_live-shipping-rates-australia' === get_current_screen()->id) {
			$is_targetted_page = true;
		}

		if ('woocommerce_page_wc-settings' == get_current_screen()->id && isset($_GET['tab']) && 'shipping' == $_GET['tab']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_targetted_page = true;
		}

		if ('woocommerce_page_wc-orders' == get_current_screen()->id) {
			$is_targetted_page = true;
		}

		if (false === $is_targetted_page) {
			return;
		}

		$shipping_companies = Main::get_shipping_companies();
		$company_id = !empty($_GET['page']) ? sanitize_key($_GET['page']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$company_id = str_replace('codiepress_', '', $company_id);

		if ('live-shipping-rates-australia' === $company_id) {
			$company_id = key($shipping_companies);
		}

		$setting_models = $helper_models = array();
		if (isset($shipping_companies[$company_id])) {
			$setting_models = $shipping_companies[$company_id]->get_settings();
			$helper_models = $shipping_companies[$company_id]->get_helper_models();
		}

		$shipping_models = [];
		foreach (Main::get_shipping_companies() as $company) {
			$shipping_models = array_merge($shipping_models, $company->get_shipping_models());
		}

		wp_register_script('live-shipping-rates-australia-sortable', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/sortable.min.js', [], '1.15.6', true);
		wp_register_script('live-shipping-rates-australia-vue-sortable', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/vue-sortable.js', ['live-shipping-rates-australia-vue', 'live-shipping-rates-australia-sortable'], '1.0.7', true);

		wp_enqueue_style('live-shipping-rates-australia', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/admin.css', [], LIVE_SHIPPING_RATES_AUSTRALIA_VERSION);

		wp_enqueue_script('live-shipping-rates-australia', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/admin.js', ['jquery', 'live-shipping-rates-australia-vue', 'live-shipping-rates-australia-vue-sortable'], LIVE_SHIPPING_RATES_AUSTRALIA_VERSION, true);
		wp_localize_script('live-shipping-rates-australia', 'live_shipping_rates_australia_admin', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'shipping_models' => $shipping_models,
			'company_setting_models' => $setting_models,
			'company_settings_helper_models' => $helper_models,
		));
	}


	/**
	 * Enqueue script on product page
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function product_enqueue_scripts() {
		if ('product' !== get_current_screen()->id) {
			return;
		}

		wp_enqueue_script('live-shipping-rates-australia-admin-product', LIVE_SHIPPING_RATES_AUSTRALIA_URI . 'assets/admin-product.js', ['jquery'], LIVE_SHIPPING_RATES_AUSTRALIA_VERSION, true);
	}

	/**
	 * Add shipping method options for each company
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_shipping_method_settings($html, $field_id, $args, $object) {
		$settings = Utils::json_string_to_array($object->get_option($field_id));

		ob_start(); ?>
		<tbody id="live-shipping-rates-australia-shipping-settings" data-settings="<?php echo esc_attr(wp_json_encode($settings)) ?>">
			<tr style="display: none!important;">
				<td colspan="2"><input type="hidden" name="<?php echo esc_attr($object->get_field_key($field_id)) ?>" :value="get_shipping_settings"></td>
			</tr>

			<tr>
				<th><?php esc_html_e('Shipping Company', 'live-shipping-rates-australia') ?></th>
				<td>
					<fieldset>
						<select v-model="shipping_company" class="regular-input">
							<option value=""><?php esc_html_e('Select a shipping company', 'live-shipping-rates-australia'); ?></option>
							<?php foreach (Main::get_shipping_companies() as $key => $company) {
								printf('<option value="%s">%s</option>', esc_attr($key), esc_html($company->get_name()));
							} ?>
						</select>

						<div class="field-note field-note-warning" v-if="!shipping_company.length"><?php esc_html_e('You have not selected the above field. You must choose a shipping company to enable this shipping method.', 'live-shipping-rates-australia'); ?></div>
					</fieldset>
				</td>
			</tr>

			<?php
			foreach (Main::get_shipping_companies() as $company) {
				$child_shipping_method = $company->get_child_shipping_method($object);
				if (!method_exists($child_shipping_method, 'options_html')) {
					continue;
				} ?>
				<template v-if="shipping_company === '<?php echo esc_attr($company->get_id()) ?>'">
					<?php $child_shipping_method->options_html(); ?>
				</template>
			<?php } ?>

		</tbody>
	<?php
		return ob_get_clean();
	}

	/**
	 * Handle save product data
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_save_product_data($product_id) {
		if (!isset($_POST['_nonce_live_shipping_rates_australia_product'])) {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce_live_shipping_rates_australia_product'])), 'nonce_live_shipping_rates_australia_product')) {
			return;
		}

		foreach (Main::get_shipping_companies() as $shipping_carrier) {
			if (property_exists($shipping_carrier, 'product_options') && is_array($shipping_carrier->product_options)) {
				foreach ($shipping_carrier->product_options as $option_name_item) {
					$option_name = $shipping_carrier->get_key($option_name_item);
					if (isset($_POST[$option_name])) {
						update_post_meta($product_id, $option_name, sanitize_text_field(wp_unslash($_POST[$option_name])));
					}
				}
			}
		}

		$product_option_meta_keys = array_keys($this->get_global_product_options());
		foreach ($product_option_meta_keys as $meta_key) {
			if (isset($_POST[$meta_key])) {
				update_post_meta($product_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$meta_key])));
			}
		}
	}

	/**
	 * Handle save variable product data
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function save_variable_product($variation_id) {
		$_nonce_key = 'nonce_live_shipping_rates_australia_product_' . $variation_id;
		if (!isset($_POST[$_nonce_key])) {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$_nonce_key])), '_nonce_live_shipping_rates_australia_product_' . $variation_id)) {
			return;
		}

		$product_options = array();
		foreach (Main::get_shipping_companies() as $shipping_carrier) {
			if (property_exists($shipping_carrier, 'product_options')) {
				foreach ($shipping_carrier->product_options as $meta_key_slug) {
					$meta_key = $shipping_carrier->get_key($meta_key_slug);
					$product_options[$meta_key] = $meta_key . $variation_id;
				}
			}
		}
	}

	/**
	 * Add section for settings of the product
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_product_options() {
		global $product_object;

		wp_nonce_field('nonce_live_shipping_rates_australia_product', '_nonce_live_shipping_rates_australia_product', false);

		foreach (Main::get_shipping_companies() as $shipping_carrier) {
			if (method_exists($shipping_carrier, 'add_product_options')) {
				echo '<div class="options_group">';
				$shipping_carrier->add_product_options($product_object);
				echo '</div>';
			}
		}

		$product_options = $this->get_global_product_options();
		foreach ($product_options as $meta_key => $product_option) {
			if (isset($product_option['callback']) && is_callable($product_option['callback'])) {
				call_user_func($product_option['callback'], $meta_key, $product_object, $product_option);
			}
		}
	}

	/**
	 * Add section for settings of the product
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_variable_product_options($loop, $variation_data, $variation) {
		wp_nonce_field('_nonce_live_shipping_rates_australia_product_' . $variation->ID, 'nonce_live_shipping_rates_australia_product_' . $variation->ID, false);
		foreach (Main::get_shipping_companies() as $shipping_carrier) {
			if (method_exists($shipping_carrier, 'add_variable_product_options')) {
				$shipping_carrier->add_variable_product_options($variation, $variation_data);
			}
		}
	}

	/**
	 * Add admin menu for live shipping rates plugin
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function register_admin_menu() {
		$main_menu_id = 'live-shipping-rates-australia';

		$shipping_companies = Main::get_shipping_companies();
		$first_company = array_shift($shipping_companies);

		add_menu_page(
			__('Live Shipping Rates Australia', 'live-shipping-rates-australia'),
			__('LSRA', 'live-shipping-rates-australia'),
			'manage_woocommerce',
			$main_menu_id,
			array($first_company, 'settings_page'),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPCEtLSBVcGxvYWRlZCB0bzogU1ZHIFJlcG8sIHd3dy5zdmdyZXBvLmNvbSwgR2VuZXJhdG9yOiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIGZpbGw9IiMwMDAwMDAiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgDQoJIHdpZHRoPSI4MDBweCIgaGVpZ2h0PSI4MDBweCIgdmlld0JveD0iMCAwIDYxMiA2MTIiIHhtbDpzcGFjZT0icHJlc2VydmUiPg0KPGc+DQoJPHBhdGggZD0iTTU0Ny42MjQsOTQuODEyQzQ5Ny4wNTMsMzUuODc1LDQyMy41MDgsMi4wNzYsMzQ1LjgzNiwyLjA3NmMtNzcuNzU3LDAtMTUxLjMxLDMzLjgwOC0yMDEuNzU0LDkyLjcwMw0KCQljLTQxLjU4MSw0OC4yMDQtNjQuNDg1LDEwOS44MzEtNjQuNDg1LDE3My41MzhjMCwxMS41NTQsMC43NzYsMjMuMDM0LDIuMjU3LDM0LjM4M2gzNS4yOTNjLTEuMDY4LTcuMDg5LTEuOTIxLTE0LjIyMS0yLjMyNy0yMS40MjQNCgkJaDgxLjc4MmMwLjE5Myw3LjI2NywwLjcxOCwxNC4zNDMsMS4yMywyMS40MjRoMjYuMTA0Yy0wLjU3MS03LjA3OC0xLjEwNS0xNC4xNzgtMS4zMjMtMjEuNDI0aDExMC4xOTh2NjEuMTIxDQoJCWM1LjgzNywyLjU3MSwxMS4yMzIsNi4xNywxNS45MDEsMTAuNzgybDEwLjA4MSw5Ljk3NnYtODEuODhoMTEwLjIwNmMtMS4wOTYsMzYuNDg3LTYuNzgzLDcxLjA0Ni0xNS45MjUsMTAxLjYwNg0KCQljLTI3LjQ2Ny03LjAyMS01Ni41OTMtMTEuMDYyLTg2LjgzMi0xMi4zNTZsMjkuMDU5LDI4Ljc1MmMxNi45MTgsMS44OTcsMzMuNDc4LDQuNjYxLDQ5LjIzNCw4LjUzMw0KCQljLTMuODkyLDkuOTM0LTguMjM0LDE5LjE3OS0xMi44NjQsMjcuODU5YzcuMzQ1LDguOTU3LDExLjU4NiwyMC4yNDksMTEuOTc0LDMxLjg2NGMxMC4xODUtMTUuMjg1LDE5LjE2Ni0zMi45MjQsMjYuNjI4LTUyLjU4Mw0KCQljMTQuMzIsNC42OTYsMjcuNTY5LDEwLjIyNSwzOS42OTIsMTYuNTEyYy0xOS4zNDEsMTkuNDc5LTQxLjc4NSwzNS4xOS02Ni4yMzMsNDYuNjE1djM3Ljc2OQ0KCQljMzkuNjUzLTE1LjY4Myw3NS4zOTgtNDAuODU4LDEwMy44NjctNzMuOTgyQzU4OS4xMjksMzkzLjcxOCw2MTIsMzMyLjA5MSw2MTIsMjY4LjMxNkM2MTIsMjA0LjU0OSw1ODkuMTI5LDE0Mi45MjMsNTQ3LjYyNCw5NC44MTINCgkJeiBNMTk2LjYwMiwyNTUuMjkxaC04MS43NzhjMi42ODEtNDcuNDAxLDE5Ljg4OS05Mi43ODYsNDkuMzU0LTEzMC4wODhjMTQuODIxLDguMTQzLDMxLjIzMSwxNS4xMjcsNDguOTUsMjAuOTg1DQoJCUMyMDMuNDk5LDE3OC44OTYsMTk3LjY1MywyMTUuODAzLDE5Ni42MDIsMjU1LjI5MXogTTIyMS4zMzIsMTIxLjY0NWMtMTQuMjY5LTQuNzA5LTI3LjQ5Mi0xMC4yMzktMzkuNjExLTE2LjUxMw0KCQljMjIuMjktMjIuNTA4LDQ4LjczLTQwLjAxNCw3Ny42NTUtNTEuNjU5QzI0NC4zODMsNzEuODQxLDIzMS40NzQsOTQuOTE3LDIyMS4zMzIsMTIxLjY0NXogTTMzMi44MTEsMjU1LjI5MUgyMjIuNjEzDQoJCWMxLjA5Ni0zNi40NzYsNi43OC03MS4wMjUsMTUuOTE3LTEwMS41NzhjMjkuODAzLDcuNTY3LDYxLjQwNywxMS44MjUsOTQuMjgsMTIuNzE1VjI1NS4yOTFMMzMyLjgxMSwyNTUuMjkxeiBNMzMyLjgxMSwxMzkuOTQ5DQoJCWMtMjkuNjA2LTAuODQ1LTU4Ljc1Ni00LjU2Ni04NS43NDQtMTEuMTYxQzI2NC41OCw4NC4wNjksMjkwLjA3OSw1MS4xLDMxOS4wODgsMzguM2M0LjU1MS0wLjUyNyw5LjEyMi0wLjk0NywxMy43MjItMS4yMDUNCgkJTDMzMi44MTEsMTM5Ljk0OUwzMzIuODExLDEzOS45NDl6IE01NzYuNzcyLDI1NS4yOTFINDk1LjAxYy0xLjA1LTM5LjQ4LTYuODk0LTc2LjM4My0xNi41MjEtMTA5LjA4Nw0KCQljMTcuNzc0LTUuODgsMzQuMjA0LTEyLjg2Nyw0OS4wMDgtMjAuOTk3QzU1Ni45MDcsMTYyLjQzNyw1NzQuMDk3LDIwNy44MzEsNTc2Ljc3MiwyNTUuMjkxeiBNMzU4Ljc5NCwzNy4wOTENCgkJYzQuNTk3LDAuMjU2LDkuMTYzLDAuNjc3LDEzLjcxMSwxLjIwNGMyOS4wMjIsMTIuNzk1LDU0LjUyOCw0NS43NzUsNzIuMDQ3LDkwLjUxYy0yNi45ODIsNi41OS01Ni4xMzgsMTAuMzA4LTg1Ljc1OCwxMS4xNDdWMzcuMDkxDQoJCXogTTM1OC43OTQsMjU1LjI5MXYtODguODYyYzMyLjg3OS0wLjg4NSw2NC40OTMtNS4xMzgsOTQuMjk0LTEyLjY5N2M5LjEzNCwzMC41NDksMTQuODE2LDY1LjA5MSwxNS45MTIsMTAxLjU1OUwzNTguNzk0LDI1NS4yOTENCgkJTDM1OC43OTQsMjU1LjI5MXogTTQzMi4yMzgsNTMuNDc3YzI4LjkxOCwxMS42NDYsNTUuMzcxLDI5LjE1NCw3Ny43MDEsNTEuNjY3Yy0xMi4xMSw2LjI1Ny0yNS4zNDgsMTEuNzg2LTM5LjY1NCwxNi41MTINCgkJQzQ2MC4xNDMsOTQuOTI0LDQ0Ny4yMzEsNzEuODQ3LDQzMi4yMzgsNTMuNDc3eiBNNTI3LjUyLDQxMS40MDljLTE0LjgyNy04LjE3Mi0zMS4yNzMtMTUuMTYyLTQ5LjA0My0yMS4wMDENCgkJYzkuNjM0LTMyLjcxNiwxNS40ODItNjkuNjM1LDE2LjUzMy0xMDkuMTM0aDgxLjc2NkM1NzQuMTEzLDMyOC43NDYsNTU2LjkzNCwzNzQuMTUyLDUyNy41Miw0MTEuNDA5eiBNNDE3Ljc0NCw0ODQuMDE4VjQ2OS4yNw0KCQljMC03LjE5LTIuODc1LTE0LjA4MS03Ljk4Ni0xOS4xMzhsLTc5LjMxMi03OC40N2MtNS4wNC00Ljk4Ny0xMS44NDQtNy43ODQtMTguOTM0LTcuNzg0aC00MC4wMTZWMzQ4Ljg3DQoJCWMwLTExLjE1MS05LjA0LTIwLjE5LTIwLjE5MS0yMC4xOUgyMC4xOUM5LjAzOSwzMjguNjc5LDAsMzM3LjcxOSwwLDM0OC44N3YxMzUuMTQ3SDQxNy43NDR6IE0yOTUuMjU1LDM5MC4zMjgNCgkJYzAtMS43NDksMS40MTQtMy4xNjMsMy4xNjMtMy4xNjNoMTQuNjA0YzAuODA4LDAsMS42MTUsMC4zMzcsMi4yMjEsMC44NzVsNjIuMTIsNTkuMjkzYzIuMDg2LDEuOTUyLDAuNjczLDUuNDUxLTIuMTUzLDUuNDUxDQoJCWgtNzYuNzkyYy0xLjc1LDAtMy4xNjMtMS40MTMtMy4xNjMtMy4xNjNWMzkwLjMyOEwyOTUuMjU1LDM5MC4zMjh6IE00MTcuNzQ0LDQ5NS4xNzR2NDQuNjk5YzAsMTEuMTUxLTkuMDQsMjAuMTkxLTIwLjE5LDIwLjE5MQ0KCQloLTIxLjYwNGMtMy45MDMtMjYuNzg3LTI2Ljk4OC00Ny4zODEtNTQuODUyLTQ3LjM4MWMtMjcuNzk1LDAtNTAuODgsMjAuNTk1LTU0Ljc4NCw0Ny4zODFoLTEwOS41DQoJCWMtMy45MDMtMjYuNzg3LTI2Ljk4OS00Ny4zODEtNTQuNzg0LTQ3LjM4MXMtNTAuODgsMjAuNTk1LTU0Ljc4NCw0Ny4zODFIMjAuMTljLTExLjE1MSwwLTIwLjE5LTkuMDQtMjAuMTktMjAuMTkxdi00NC42OTlINDE3Ljc0NA0KCQl6IE0zMjEuMTI5LDUyNi4xN2MtMjMuMTI4LDAtNDEuODc3LDE4Ljc0OS00MS44NzcsNDEuODc3czE4Ljc0OSw0MS44NzcsNDEuODc3LDQxLjg3N2MyMy4xMjcsMCw0MS44NzctMTguNzQ5LDQxLjg3Ny00MS44NzcNCgkJUzM0NC4yNTYsNTI2LjE3LDMyMS4xMjksNTI2LjE3eiBNMzIxLjEyOSw1ODguOTg1Yy0xMS41NjQsMC0yMC45MzgtOS4zNzUtMjAuOTM4LTIwLjkzOHM5LjM3NC0yMC45MzgsMjAuOTM4LTIwLjkzOA0KCQljMTEuNTYzLDAsMjAuOTM4LDkuMzc0LDIwLjkzOCwyMC45MzhTMzMyLjY5Miw1ODguOTg1LDMyMS4xMjksNTg4Ljk4NXogTTEwMi4wMjIsNTI2LjE3Yy0yMy4xMjcsMC00MS44NzYsMTguNzQ5LTQxLjg3Niw0MS44NzcNCgkJczE4Ljc0OSw0MS44NzcsNDEuODc2LDQxLjg3N2MyMy4xMjgsMCw0MS44NzctMTguNzQ5LDQxLjg3Ny00MS44NzdTMTI1LjE1LDUyNi4xNywxMDIuMDIyLDUyNi4xN3ogTTEwMi4wMjIsNTg4Ljk4NQ0KCQljLTExLjU2MywwLTIwLjkzOC05LjM3NS0yMC45MzgtMjAuOTM4czkuMzc1LTIwLjkzOCwyMC45MzgtMjAuOTM4YzExLjU2NCwwLDIwLjkzOCw5LjM3NCwyMC45MzgsMjAuOTM4DQoJCVMxMTMuNTg2LDU4OC45ODUsMTAyLjAyMiw1ODguOTg1eiIvPg0KPC9nPg0KPC9zdmc+',
			'55.56'
		);

		add_submenu_page($main_menu_id, $first_company->get_name(), $first_company->get_name(), 'manage_woocommerce', 'live-shipping-rates-australia', array($first_company, 'settings_page'));
		foreach ($shipping_companies as $company_id => $company) {
			add_submenu_page($main_menu_id, $company->get_name(), $company->get_name(), 'manage_woocommerce', 'codiepress_' . $company_id, array($company, 'settings_page'));
		}
	}

	/**
	 * Adjust shipping method meta data
	 * 
	 * @since 1.0.3
	 * @return array
	 */
	public function remove_shipping_meta_data($meta_data) {
		$meta_keys = apply_filters('live_shipping_rates_australia/shipping_meta_remove_keys', ['shipping_data', 'description']);
		foreach ($meta_data as $key_id => $shipping_meta) {
			if (in_array($shipping_meta->key, $meta_keys)) {
				unset($meta_data[$key_id]);
			}
		}

		return $meta_data;
	}

	/**
	 * Show notice if plugin shiping method does not exists
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function show_missing_method_notice() {
		$shipping_zones = \WC_Shipping_Zones::get_zones();

		$has_our_method = false;
		foreach ($shipping_zones as $zone) {
			if (!isset($zone['shipping_methods']) || !is_array($zone['shipping_methods'])) {
				continue;
			}

			foreach ($zone['shipping_methods'] as $method) {
				if ('live_shipping_rates_australia' == $method->id) {
					$has_our_method = true;
					continue;
				}
			}
		}

		if ($has_our_method) {
			return;
		}

		$global_zone = new \WC_Shipping_Zone(0);
		$shipping_methods = $global_zone->get_shipping_methods();

		foreach ($shipping_methods as $method) {
			if ('live_shipping_rates_australia' == $method->id) {
				$has_our_method = true;
				continue;
			}
		}

		if ($has_our_method) {
			return;
		}

		echo '<div class="missing-shipping-method-notice">';
		esc_html_e('You have not added the "Live Shipping Rates Australia" shipping method to any shipping zone. Please go to the "Shipping Zones" settings and add the "Live Shipping Rates Australia" shipping method to a targeted zone.', 'live-shipping-rates-australia');
		echo '</div>';
	}


	/**
	 * Quantity in product option for product item
	 * 
	 * @since 1.0.4
	 * @return void
	 */
	public function quantity_in_product_product_option($field_id, $product_object) { ?>
		<div class="options_group">
			<p class="form-field lsra_quantity_in_product_field ">
				<label for="lsra_quantity_in_product"><?php esc_html_e('Quantity in Package', 'live-shipping-rates-australia') ?></label>
				<input type="number" class="short" disabled>
			</p>

			<p class="form-field" style="margin-top: -17px;">
				<?php
				if (!Utils::is_pro_installed()) {
					printf(
						/* translators: %1$s for link open, %2$s for link close */
						esc_html__('Get the %1$s pro version%2$s to unlock this option.', 'live-shipping-rates-australia'),
						'<a href="https://codiepress.com/plugins/live-shipping-rates-australia-for-woocommerce/?utm_campaign=live+shipping+rates+australia&utm_source=product&utm_medium=quantity+in+package" target="_blank">',
						'</a>',
					);
				}

				if (Utils::is_pro_installed() && !Utils::is_pro_activated()) {
					esc_html_e('Activate the "Live Shipping Rates Australia Pro" plugin to unlock this option.', 'live-shipping-rates-australia');
				}

				if (Utils::is_pro_activated() && !Utils::license_activated()) {
					esc_html_e('Activate the license of "Live Shipping Rates Australia Pro" plugin to unlock this option.', 'live-shipping-rates-australia');
				} ?>
			</p>
		</div>
<?php
	}
}
