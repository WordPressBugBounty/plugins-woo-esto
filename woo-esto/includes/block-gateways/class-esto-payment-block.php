<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Esto_Payment_Block extends AbstractPaymentMethodType {

	public $name = 'esto';
	public $icon, $logo;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );

		$this->logo = $this->get_setting( 'logo' );

		$current_language = apply_filters( 'wpml_current_language', false );
		if ( ! $current_language ) {
			$current_language = substr( get_locale(), 0, 2 );
		}
		if ( $current_language ) {
			$logo_url_key = 'logo_' . $current_language;
			$language_logo_src = $this->get_setting( $logo_url_key );
			if ( $language_logo_src ) {
				$this->logo = $language_logo_src;
			}
		}

		if ( $this->get_setting( 'show_logo' ) !== 'no' ) {
			$this->icon = $this->logo ? $this->logo : plugins_url( 'assets/images/icons/logo-' . $this->name . '.svg', dirname( __FILE__, 2 ) );
			$this->icon = apply_filters( 'woocommerce_' . $this->name . '_icon', $this->icon );
		}
	}

	public function is_active() {
		return $this->get_setting( 'enabled' ) === 'yes';
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wc-' . $this->name . '-block-support',
			plugins_url( 'assets/js/block-gateways/block-' . $this->name . '.js', dirname( __FILE__, 2 ) ),
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			],
			filemtime( dirname( __FILE__, 3 ) . '/assets/js/block-gateways/block-' . $this->name . '.js' ),
			true
		);

		return ['wc-' . $this->name . '-block-support'];
	}

	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'icon'        => $this->icon,
		];
	}
}
