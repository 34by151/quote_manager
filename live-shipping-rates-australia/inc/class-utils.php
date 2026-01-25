<?php

namespace Live_Shipping_Rates_Australia;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utilities class
 */
class Utils {

	/**
	 * Check if pro version installed
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function is_pro_installed() {
		return file_exists(dirname(LIVE_SHIPPING_RATES_AUSTRALIA_PATH) . DIRECTORY_SEPARATOR . 'live-shipping-rates-australia-pro/live-shipping-rates-australia-pro.php');
	}

	/**
	 * Check if pro plugin activated
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function is_pro_activated() {
		return class_exists('\Live_Shipping_Rates_Australia_Pro\Main');
	}

	/**
	 * Check if pro plugin activated the license
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function license_activated() {
		return function_exists('live_shipping_rates_australia_fs') && live_shipping_rates_australia_fs()->can_use_premium_code();
	}

	/**
	 * JSON string to array
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function json_string_to_array($json_string) {
		$data = json_decode(stripslashes($json_string), true);
		if (!is_array($data)) {
			$data = array();
		}

		return $data;
	}

	/**
	 * Get fee type options
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function get_fee_type_options() { ?>
		<option value="fixed_amount"><?php esc_html_e('Fixed Amount', 'live-shipping-rates-australia') ?></option>
		<option value="percentage"><?php esc_html_e('Percentage', 'live-shipping-rates-australia') ?></option>
	<?php
	}

	/**
	 * Get instance of current class
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_field_note($prepend = '', $append = '') {
		echo '<div class="field-note">';
		if (!empty($prepend)) {
			echo wp_kses_post($prepend) . ' ';
		}

		if (!self::is_pro_installed()) {
			printf(
				/* translators: %1$s for link open, %2$s for link close */
				esc_html__('Get the %1$s pro version%2$s to unlock this option.', 'live-shipping-rates-australia'),
				'<a href="https://codiepress.com/plugins/live-shipping-rates-australia-for-woocommerce/?utm_campaign=live+shipping+rates+australia&utm_source=settings&utm_medium=get+pro" target="_blank">',
				'</a>',
			);
		}

		if (self::is_pro_installed() && !self::is_pro_activated()) {
			esc_html_e('Activate the "Live Shipping Rates Australia Pro" plugin to unlock this option.', 'live-shipping-rates-australia');
		}

		if (self::is_pro_activated() && !self::license_activated()) {
			esc_html_e('Activate the license of "Live Shipping Rates Australia Pro" plugin to unlock this option.', 'live-shipping-rates-australia');
		}

		if (!empty($prepend)) {
			echo ' ' . wp_kses_post($append);
		}

		echo '</div>';
	}

	/**
	 * Get product measurements
	 * 
	 * @since 1.0.0
	 * @param $_product cart item
	 * @param $child_shipping_method Company Child shipping method
	 * @return array
	 */
	public static function get_product_measurements($_product, $child_shipping_method) {
		$common_data = array(
			'width' => floatval($_product->get_width()),
			'length' => floatval($_product->get_length()),
			'weight' => floatval($_product->get_weight()),
			'height' => floatval($_product->get_height()),
		);

		if (empty($common_data['weight'])) {
			$common_data['weight'] = floatval($child_shipping_method->get_setting('weight', 0.001));
		}

		if (empty($common_data['length'])) {
			$common_data['length'] = floatval($child_shipping_method->get_setting('length', 0.01));
		}

		if (empty($common_data['width'])) {
			$common_data['width'] = floatval($child_shipping_method->get_setting('width', 0.01));;
		}

		if (empty($common_data['height'])) {
			$common_data['height'] = floatval($child_shipping_method->get_setting('height', 0.01));
		}

		return $common_data;
	}

	/**
	 * Get total dimension and weight
	 * 
	 * @since 1.0.4
	 * @return array
	 */
	public static function get_cart_total_dimensions_and_weight($cart_items, $child_method) {
		$total_weight = $max_length = $max_width = $max_height = 0;

		foreach ($cart_items as $cart_item) {
			$product_measurements = self::get_product_measurements($cart_item['data'], $child_method);

			// Get dimensions and weight for the product
			$length = $product_measurements['length'];
			$width = $product_measurements['width'];
			$height = $product_measurements['height'];
			$weight = $product_measurements['weight'];

			// Get quantity
			$quantity = $cart_item['quantity'];

			// Update total weight
			$total_weight += $weight * $quantity;

			// Update maximum dimensions for combined parcel
			$max_length = max($max_length, $length);
			$max_width = max($max_width, $width);
			$max_height += $height * $quantity;
		}

		return array(
			'length' => wc_get_dimension($max_length, 'cm'),
			'width' => wc_get_dimension($max_width, 'cm'),
			'height' => wc_get_dimension($max_height, 'cm'),
			'weight' => wc_get_weight($total_weight, 'kg'),
		);
	}

	/**
	 * Get first error from error log
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function get_first_error($shipping_company) {
		$error_logs = new \WP_Query(array(
			'post_type' => Main::ERROR_POST_TYPE,
			'posts_per_page' => 1,
			'post_status' => 'any',
			'orderby' => 'ID',
			'meta_query' => array(
				array(
					'key' => 'shipping_company_id',
					'value' => $shipping_company->get_id(),
				)
			)
		));

		if ($error_logs->post_count > 0) {
			$first_error = $error_logs->posts[0];

			echo '<dl class="live-shipping-rates-australia-error-item">';
			$error_code = $first_error->error_code;
			if (!empty($error_code)) {
				echo '<dt>' . esc_html__('Code', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . wp_kses_post($error_code) . '</dd>';
			}

			echo '<dt>' . esc_html__('Date & Time', 'live-shipping-rates-australia') . '</dt>';
			echo '<dd>';
			echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($first_error->post_date_gmt)));
			$created_timestamp = strtotime(wp_date('Y-m-d H:i:s', strtotime($first_error->post_date_gmt)));
			$readable_diff_time = strtotime(wp_date('Y-m-d H:i:s', strtotime('-3days')));
			if ($created_timestamp > $readable_diff_time) {
				echo ' (' . wp_kses_post(human_time_diff($created_timestamp, current_time('timestamp')) . ' ago)');
			}

			echo '</dd>';

			echo '<dt>' . esc_html__('Description', 'live-shipping-rates-australia') . '</dt>';
			echo '<dd>' . wp_kses_post($first_error->post_content) . '</dd>';

			$shipping_city = $first_error->shipping_city;
			if (!empty($shipping_city)) {
				echo '<dt>' . esc_html__('Shipping City', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . esc_html($shipping_city) . '</dd>';
			}

			$shipping_postcode = $first_error->shipping_postcode;
			if (!empty($shipping_postcode)) {
				echo '<dt>' . esc_html__('Shipping Postcode', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . esc_html($shipping_postcode) . '</dd>';
			}

			$country_code = $first_error->shipping_country_code;
			if (!empty($country_code)) {
				echo '<dt>' . esc_html__('Shipping Country', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . esc_html(WC()->countries->countries[$country_code]) . '</dd>';
			}

			$service_code = $first_error->service_code;
			if (!empty($service_code)) {
				echo '<dt>' . esc_html__('Service Code', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . esc_html($service_code) . '</dd>';
			}

			$service_option = $first_error->service_option;
			if (!empty($service_code)) {
				echo '<dt>' . esc_html__('Service Option', 'live-shipping-rates-australia') . '</dt>';
				echo '<dd>' . esc_html($service_option) . '</dd>';
			}

			echo '</dl>';

			if (!self::is_pro_installed()) {
				echo '<a href="https://codiepress.com/plugins/live-shipping-rates-australia-for-woocommerce/?utm_campaign=live+shipping+rates+australia&utm_source=settings&utm_medium=error_log">' . esc_html__('Get the pro version to see all the errors', 'live-shipping-rates-australia') . '</a>';
			} else {
				if (!self::is_pro_activated()) {
					$notice_url = wp_nonce_url('plugins.php?action=activate&plugin=live-shipping-rates-australia-pro/live-shipping-rates-australia-pro.php&plugin_status=all&paged=1', 'activate-plugin_live_shipping_rates_australia-pro/live-shipping-rates-australia-pro.php');
					echo '<a href="' . esc_url($notice_url) . '">' . esc_html__('Activate the pro version to see all errors', 'live-shipping-rates-australia') . '</a>';
				} else {
					if (!self::license_activated()) {
						echo '<a href="#">' . esc_html__('Please activate your license to see all errors.', 'live-shipping-rates-australia') . '</a>';
					} else {
						echo '<a href="' . esc_url(add_query_arg('screen', 'errors')) . '">' . esc_html__('See all errors', 'live-shipping-rates-australia') . '</a>';
					}
				}
			}
		} else {
			esc_html_e('No error found.', 'live-shipping-rates-australia');
		}
	}

	/**
	 * Get connection status row
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function get_connection_status_row() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label for="account_number"><?php esc_html_e('Connection Status', 'live-shipping-rates-australia') ?></label>
			</th>
			<td class="forminp">
				<div v-if="api_checking"><?php esc_html_e('Checking...', 'live-shipping-rates-australia') ?></div>
				<template v-else>
					<div v-if="!api_connected" class="connection-status"><?php esc_html_e('Not Connected', 'live-shipping-rates-australia') ?></div>
					<div v-if="api_connected" class="connection-status connection-status-connected"><?php esc_html_e('Connected', 'live-shipping-rates-australia') ?></div>
				</template>
			</td>
		</tr>
	<?php
	}

	/**
	 * Get measurements fields of shipping company
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_measurements_fields() { ?>
		<tr>
			<th scope="row" class="titledesc">
				<label for="default_weight">
					<?php
					/* translators: %s: store weight unit */
					printf(esc_html__('Default Weight (%s)', 'live-shipping-rates-australia'), esc_html(get_option('woocommerce_weight_unit')))
					?>
				</label>
			</th>
			<td class="forminp">
				<input id="default_weight" class="field-type-number" type="text" v-model="weight" placeholder="<?php esc_attr_e('Weight', 'live-shipping-rates-australia') ?>">
				<p class="description"><?php esc_html_e('We will use this value if the cart product item does not have weight.', 'live-shipping-rates-australia') ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row" class="titledesc">
				<label for="account_number">
					<?php
					/* translators: %s: store dimension unit */
					printf(esc_html__('Default Dimension (%s)', 'live-shipping-rates-australia'), esc_html(get_option('woocommerce_dimension_unit')));
					?>
				</label>
			</th>
			<td class="forminp">
				<div class="dimension-field-group">
					<input class="field-type-number" type="text" v-model="length" placeholder="<?php esc_attr_e('Length', 'live-shipping-rates-australia') ?>" title="<?php esc_attr_e('Length', 'live-shipping-rates-australia') ?>">
					<input class="field-type-number" type="text" v-model="width" placeholder="<?php esc_attr_e('Width', 'live-shipping-rates-australia') ?>" title="<?php esc_attr_e('Width', 'live-shipping-rates-australia') ?>">
					<input class="field-type-number" type="text" v-model="height" placeholder="<?php esc_attr_e('Height', 'live-shipping-rates-australia') ?>" title="<?php esc_attr_e('Height', 'live-shipping-rates-australia') ?>">
				</div>
				<p class="description"><?php esc_html_e('We will use above values if the cart product item does not have length, width, or height.', 'live-shipping-rates-australia') ?></p>
			</td>
		</tr>
	<?php
	}

	/**
	 * Get services table
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function get_services_table($model) { ?>
		<table class="live-shipping-rates-australia-shipping-service-table">
			<thead>
				<tr>
					<th></th>
					<th class="column-code"><?php esc_html_e('Code & Option', 'live-shipping-rates-australia') ?></th>
					<th class="column-title"><?php esc_html_e('Title & Description', 'live-shipping-rates-australia') ?></th>
					<th class="column-enabled"><?php esc_html_e('Active', 'live-shipping-rates-australia') ?></th>
				</tr>
			</thead>

			<tbody v-sortable="{options: {handle: '.icon-move'}}" @end="on_service_order_change" data-model="<?php echo esc_attr($model) ?>">
				<tr v-for="service_item in <?php echo esc_attr($model) ?>" :key="service_item.code">
					<td class="icon-move"></td>
					<td class="column-code">
						<strong>Code:</strong> {{service_item.code}} <br>
						<strong>Option:</strong> {{service_item.option}}
					</td>
					<td>
						<input type="text" v-model="service_item.title">
						<textarea v-model="service_item.description" placeholder="<?php esc_attr_e('Write a description of this shipping service.', 'live-shipping-rates-australia') ?>"></textarea>
					</td>
					<td class="column-enabled"><input type="checkbox" v-model="service_item.active"></td>
				</tr>
			</tbody>

			<tfoot>
				<tr>
					<th colspan="4">
						<?php esc_html_e('Drag and drop the item to change the display order and clicking on the Save changes button.', 'live-shipping-rates-australia') ?>
					</th>
				</tr>
			</tfoot>
		</table>
	<?php
	}

	/**
	 * Get Debugging row
	 * 
	 * @since 1.0.2
	 * @return void
	 */
	public static function get_debugging_row() { ?>
		<tr>
			<th>
				<?php esc_html_e('Debugging', 'live-shipping-rates-australia') ?>
			</th>

			<td>
				<label>
					<input type="checkbox" v-model="debugging">
					<?php esc_html_e('Yes', 'live-shipping-rates-australia') ?>
				</label>

				<p class="field-note field-note-warning">
					<?php esc_html_e('The output is for testing purposes only and will be visible exclusively to the admin and/or store manager.', 'live-shipping-rates-australia') ?>
				</p>
			</td>
		</tr>
<?php
	}

	/**
	 * Get order dl data
	 * 
	 * @since 1.0.3
	 * @return string
	 */
	public static function get_order_dl_data($main_data, $keys) {
		$list_item = array();
		foreach ($keys as $key => $label) {
			if (isset($main_data[$key])) {
				$list_item[] = sprintf('<dt>%s</dt><dd>%s</dd>', $label, $main_data[$key]);
			}
		}

		return implode('', $list_item);
	}
}
