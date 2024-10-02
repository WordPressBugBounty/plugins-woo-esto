<?php

class WC_Esto_X_Payment_Block extends WC_Esto_Payment_Block {

	public $name = 'esto_x';

	public function get_payment_method_data() {
		$show_calculator = ( $this->get_setting( 'show_calculator' ) === 'yes' && ! empty( WC()->cart ) );
		$calculator = '';
		if ( $show_calculator ) {
			ob_start();
			WC_Esto_X_Payment::display_calculator();
			$calculator = ob_get_clean();
		}

		return [
			'title'           => $this->get_setting( 'title' ),
			'description'     => $this->get_setting( 'description' ),
			'icon'            => $this->icon,
			'calculator'      => $calculator,
			'show_calculator' => $show_calculator,
		];
	}
}
