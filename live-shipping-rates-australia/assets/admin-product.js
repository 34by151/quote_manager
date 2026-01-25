(function ($) {

	$('#woocommerce-product-data').on('change', 'select.aramex-connect-package-type', function () {
		const variation_id = $(this).data('variation-id') || '';
		const satchel_size_input = $('#live_shipping_rates_australia_aramex_connect_satchel_size' + variation_id);
		const field_wrapper = satchel_size_input.closest('.form-field');

		let package_type = $(this).val();
		if ('inherit' == $(this).val()) {
			package_type = $('#live_shipping_rates_australia_aramex_connect_package_type').val()
		}

		field_wrapper.hide();
		if ('S' == package_type) {
			field_wrapper.show()
		}
	})

	$('#woocommerce-product-data').find('select.aramex-connect-package-type').trigger('change')

	$('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
		$('#woocommerce-product-data').find('select.aramex-connect-package-type').trigger('change')
	});

})(jQuery)