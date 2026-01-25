(function ($) {

	const Live_Shipping_Rates_Australia_Company = {
		components: {
			...wp.hooks.applyFilters('live_shipping_rates_australia_components', {})
		},
		data() {
			return {
				...live_shipping_rates_australia_admin.company_setting_models,
				...live_shipping_rates_australia_admin.company_settings_helper_models
			}
		},

		computed: {
			get_settings_data() {
				const data = JSON.parse(JSON.stringify(this.$data));
				for (const key in live_shipping_rates_australia_admin.company_settings_helper_models) {
					delete data[key];
				}

				return JSON.stringify(data)
			},

			get_api_result_class() {
				return {
					'live-shipping-rates-australia-api-result': true,
					'live-shipping-rates-australia-api-error': this.api_error,
				}
			}
		},

		watch: {
			account_type() {
				if ('sendle' != this.company_id) {
					return;
				}

				this.validate_sendle('check_api_status')
			}
		},

		methods: {
			on_service_order_change(event) {
				const model_key = $(event.from).data('model');
				if (!model_key) {
					return;
				}

				let item = this[model_key].splice(event.oldIndex, 1)[0];
				this[model_key].splice(event.newIndex, 0, item);
			},

			validate_api_connection(formData) {
				this.api_checking = true;

				fetch(live_shipping_rates_australia_admin.ajax_url, {
					method: 'POST',
					body: formData
				}).then((response) => response.json()).then((result) => {
					console.log(result)
					this.api_checking = false;
					if (result.success === false) {
						this.api_error = true;
						this.api_connected = false;
						this.api_message = result.data?.message;
					}

					if (result.success === true) {
						this.api_error = false;
						this.api_connected = true;
						this.api_message = result.data?.message;
					}
				})
			},

			validate_sendle(action_end_point = 'validate_api_connection') {
				const formData = new FormData();
				formData.append('account_type', this.account_type)
				
				formData.append('api_key', this.sandbox_api_key)
				formData.append('sendle_id', this.sandbox_sendle_id)
				
				if ('live' == this.account_type) {
					formData.append('api_key', this.api_key)
					formData.append('sendle_id', this.sendle_id)
				}
				
				formData.append('nonce', this.$refs.api_nonce.value)
				formData.append('action', 'live_shipping_rates_australia/sendle/' + action_end_point)

				this.validate_api_connection(formData);
			},

			validate_australia_post() {
				const formData = new FormData();
				formData.append('api_key', this.api_key)
				formData.append('nonce', this.$refs.api_nonce.value)
				formData.append('action', 'live_shipping_rates_australia/australia_post/validate_api_connection')

				this.validate_api_connection(formData);
			},

			validate_direct_freight_express() {
				const formData = new FormData();
				formData.append('nonce', this.$refs.api_nonce.value)
				formData.append('api_key', this.api_key)
				formData.append('account_no', this.account_number)
				formData.append('action', 'live_shipping_rates_australia/direct_freight_express/validate_api_key')

				this.validate_api_connection(formData);
			},

			validate_aramex() {
				const formData = new FormData();
				formData.append('nonce', this.$refs.api_nonce.value)
				formData.append('username', this.username)
				formData.append('password', this.password)
				formData.append('api_entity', this.api_entity)
				formData.append('api_pin', this.api_pin)
				formData.append('country_code', this.country_code)
				formData.append('account_number', this.account_number)
				formData.append('action', 'live_shipping_rates_australia/aramex/validate_credentials')

				this.validate_api_connection(formData);
			},

			validate_aramex_connect() {
				const formData = new FormData();
				formData.append('nonce', this.$refs.api_nonce.value)
				formData.append('client_id', this.client_id)
				formData.append('client_secret', this.client_secret)
				formData.append('action', 'live_shipping_rates_australia/aramex_connect/validate_credentials')

				this.validate_api_connection(formData);
			},
		}
	}

	if ($('#live-shipping-rates-australia-company-settings').length) {
		const Shipping_Company_App = Vue.createApp(Live_Shipping_Rates_Australia_Company).use(sortablejs)
		Shipping_Company_App.mount('#live-shipping-rates-australia-company-settings')
	}

	const Shipping_Method_Settings = {
		data() {
			return {
				shipping_company: '',
				...live_shipping_rates_australia_admin.shipping_models
			}
		},

		computed: {
			get_shipping_settings() {
				return JSON.stringify(this.$data);
			},

		},
	}


	function initialize_conditional_settings_field() {
		if (!$('#live-shipping-rates-australia-shipping-settings').length) {
			return;
		}

		const Main_App = Vue.createApp(Shipping_Method_Settings).mount('#live-shipping-rates-australia-shipping-settings')

		const settings = $('#live-shipping-rates-australia-shipping-settings').data('settings');
		if (typeof settings === 'object') {
			for (const key in settings) {
				Main_App[key] = settings[key]
			}
		}
	}

	initialize_conditional_settings_field();

})(jQuery)