<?php

/**
 * Class WC_Esto_Card_Payment
 *
 * Handles ESTO Pay card payments (Visa and Mastercard) in WooCommerce.
 */
class WC_Esto_Card_Payment extends WC_Esto_Payment
{
	/**
	 * @var string The payment schedule type.
	 */
	protected $schedule_type;

	/**
	 * @var string The key for the payment method.
	 */
	protected $payment_method_key;

	/**
	 * WC_Esto_Card_Payment constructor.
	 */
	public function __construct()
	{
		$this->id = 'esto_card';
		$this->method_title = __('Card payment (ESTO Pay)', 'woo-esto');
		$this->method_description = __('ESTO Pay card payments are Visa and Mastercard credit/debit card payments. Contact ESTO Partner Support for additional information and activation.', 'woo-esto');
		$this->schedule_type = 'ESTO_PAY';

		parent::__construct();

		$this->admin_page_title = __('Card payment (ESTO Pay)', 'woo-esto');

		if ($this->enabled === 'yes') {
			$method = $this->get_card_method();
			$this->payment_method_key = isset($method->key) ? $method->key : false;
		}
	}

	/**
	 * Initialize form fields for the payment gateway.
	 */
	public function init_form_fields(): void
	{
		parent::init_form_fields();

		$this->form_fields = [
			'enabled' => [
				'title'   => __('Enable/Disable', 'woo-esto'),
				'type'    => 'checkbox',
				'label'   => __('ESTO Pay card payments are Visa and Mastercard credit/debit card payments. Contact ESTO Partner Support for additional information and activation.', 'woo-esto'),
				'default' => 'no',
			],
			'title' => [
				'title'       => __('Title', 'woo-esto'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woo-esto'),
				'default'     => __('Pay by card (Visa/Mastercard)', 'woo-esto'),
			],
			'description' => [
				'title'       => __('Description', 'woo-esto'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'woo-esto'),
				'default'     => __('Payment is made using a secure payment solution.', 'woo-esto'),
			],
			'show_logo' => [
				'title'   => __('Show logo', 'woo-esto'),
				'type'    => 'checkbox',
				'label'   => __('Show Visa/Mastercard logo in checkout', 'woo-esto'),
				'default' => 'yes',
			],
		] + [
			'order_prefix' => $this->form_fields['order_prefix'],
		];
	}

	/**
	 * Check if the payment method is available for use.
	 *
	 * @return bool True if available, false otherwise.
	 */
	public function is_available(): bool
	{
		if ($this->enabled !== 'yes') {
			return false;
		}

		$method = $this->get_card_method();
		return !empty($method);
	}

	/**
	 * Get the active endpoint country for the API.
	 *
	 * @return string The active country code (ee, lv, lt).
	 */
	public function get_active_endpoint_country(): string
	{
		$payment_settings = get_option('woocommerce_esto_settings', null);
		$api_url = $payment_settings && !empty($payment_settings['endpoint']) ? $payment_settings['endpoint'] : WOO_ESTO_API_URL_EE;

		switch ($api_url) {
			case WOO_ESTO_API_URL_LV:
				return 'lv';
			case WOO_ESTO_API_URL_LT:
				return 'lt';
			default:
				return 'ee';
		}
	}

	/**
	 * Get card payment methods from the ESTO API.
	 *
	 * @return array The array of card methods.
	 */
	public function get_card_methods_from_api(): array
	{
		$endpoint_country = $this->get_active_endpoint_country();
		$url = esc_url(esto_get_api_url_from_options() . "v2/purchase/payment-methods?country_code=" . strtoupper($endpoint_country));

		if ($this->connection_mode === 'test') {
			$url .= "&test_mode=1";
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json, application/x-www-form-urlencoded"]);
		curl_setopt($curl, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		$resp = curl_exec($curl);
		curl_close($curl);

		$data = json_decode($resp);
		$card_methods = [];

		if (is_array($data)) {
			foreach ($data as $row) {
				if (isset($row->type) && $row->type === 'CARD') {
					$card_methods[] = $row;
				}
			}
		}

		return $card_methods;
	}

	/**
	 * Get a single card method (currently only one is returned).
	 *
	 * @return object|false The card method object or false if not available.
	 */
	public function get_card_method()
	{
		$transient_name = 'woo_esto_card_methods';
		$methods = get_transient($transient_name);

		if (!$methods) {
			$methods = $this->get_card_methods_from_api();
			set_transient($transient_name, $methods, HOUR_IN_SECONDS);
		}

		return !empty($methods) ? reset($methods) : false;
	}
}
