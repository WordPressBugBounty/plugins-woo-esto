<?php

class WC_Esto_Pay_Payment_Block extends WC_Esto_Payment_Block {
	public $gateway;
	public $name = 'esto_pay';

	public function get_payment_method_data() {
		$show_bank_logos = ( $this->get_setting( 'show_bank_logos' ) != 'no' && ! empty( WC()->cart ) );
		$bank_logos = '';
		if ( $show_bank_logos ) {
			ob_start();
			$all_payment_methods = WC()->payment_gateways()->payment_gateways();
			if ( isset( $all_payment_methods['esto_pay'] ) ) {
				$all_payment_methods['esto_pay']->print_bank_logos_html();
			}
			$bank_logos = ob_get_clean();
		}

		return [
			'title'           => $this->get_setting( 'title' ),
			'description'     => __('Payment is made using a secure payment solution called KEVIN (UAB “KEVIN EU”), which is licensed by the Bank of Lithuania.', 'woo-esto'),
			'icon'            => $this->icon,
			'bank_logos'      => $bank_logos,
			'show_bank_logos' => $show_bank_logos,
		];
	}
}
