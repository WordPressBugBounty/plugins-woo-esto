<?php

/**
 * WC_Pay_Later_Payment class to handle the Pay Later payment method.
 */
class WC_Pay_Later_Payment extends WC_Esto_Payment
{

	protected $schedule_type;

	/**
	 * WC_Pay_Later_Payment constructor.
	 */
	public function __construct()
	{
		// Set the necessary properties
		$this->id               = 'pay_later';
		$this->method_title      = __('Pay Later', 'woo-esto');
		$this->method_description = __('Pay Later is an alternative ESTO payment method. For more information and activation please contact ESTO Partner Support.', 'woo-esto');
		$this->schedule_type     = 'PAY_LATER';

		parent::__construct();

		$this->admin_page_title  = __('ESTO Pay Later payment gateway', 'woo-esto');
		$this->min_amount        = $this->get_option('min_amount', 0.1);
		$this->max_amount        = $this->get_option('max_amount', 10000);
	}

	/**
	 * Initialize form fields for the payment method.
	 */
	public function init_form_fields(): void
	{
		parent::init_form_fields();

		$this->form_fields = [
			'enabled' => [
				'title'   => __('Enable/Disable', 'woo-esto'),
				'type'    => 'checkbox',
				'label'   => __('Pay Later is a campaign of ESTO. Contact ESTO support for additional information.', 'woo-esto'),
				'default' => 'no',
			],
			'title' => [
				'title'       => __('Title', 'woo-esto'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woo-esto'),
				'default'     => __('Buy now and pay later!', 'woo-esto'),
			],
			'description' => [
				'title'         => __('Description', 'woo-esto'),
				'type'          => 'textarea',
				'description'   => __('This controls the description which the user sees during checkout.', 'woo-esto'),
				'default'       => __('Just confirm your order with 3 clicks and take your time! Pay later, within 30 days, without any extra fees!', 'woo-esto'),
			],
		]
			+ $this->description_logos
			+ [
				'show_logo'                          => $this->form_fields['show_logo'],
				'logo'                               => $this->form_fields['logo'],
			]
			+ $this->language_specific_logos
			+ [
				'min_amount'                         => $this->form_fields['min_amount'],
				'max_amount'                         => $this->form_fields['max_amount'],
				'disabled_countries_for_this_method' => $this->form_fields['disabled_countries_for_this_method'],
				'set_on_hold_status'                 => $this->form_fields['set_on_hold_status'],
				'order_prefix'                       => $this->form_fields['order_prefix'],
			];

		// Set default values for min and max amounts.
		$this->form_fields['min_amount']['default'] = 0.1;
		$this->form_fields['max_amount']['default'] = 10000;
	}
}
