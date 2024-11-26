<?php

declare(strict_types=1);

/**
 * WC_Esto_X_Payment class
 *
 * Handles ESTO X payment methods for WooCommerce. ESTO X allows users to split payments into equal parts
 * with no extra charges. This class provides settings for configuring the payment method, including options
 * for showing a payment calculator during checkout.
 */
class WC_Esto_X_Payment extends WC_Esto_Payment 
{
    public bool $show_calculator;
    public string $schedule_type;

    /**
     * Constructor for the ESTO X payment method.
     * Initializes the method and sets up WooCommerce hooks and filters.
     */
    public function __construct() {
        $this->id = 'esto_x';
        $this->method_title = __( 'Esto X', 'woo-esto' );
        $this->method_description = __( 'ESTO X is an alternative ESTO payment method. For more information and activation please contact ESTO Partner Support.', 'woo-esto' );
        $this->schedule_type = 'ESTO_X';
        $this->show_calculator = $this->get_option('show_calculator', 'no') === 'yes';

        parent::__construct();

        $this->admin_page_title = __( 'ESTO X payment gateway', 'woo-esto' );
        $this->min_amount = (float) $this->get_option('min_amount', 0.1);
        $this->max_amount = (float) $this->get_option('max_amount', 10000);

        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }



	/**
	 * Initializes form fields for admin settings.
	 * 
	 * This method defines all the settings fields that will be displayed on the WooCommerce
	 * payment settings page for this gateway.
	 */
    public function init_form_fields(): void {
        parent::init_form_fields();

        $this->form_fields = [
            'enabled' => [
                'title' => __( 'Enable/Disable', 'woo-esto' ),
                'type' => 'checkbox',
                'label' => __( 'ESTO X is a campaign of ESTO. Contact ESTO support for additional information.', 'woo-esto' ),
                'default' => 'no',
            ],
            'title' => [
                'title' => __( 'Title', 'woo-esto' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woo-esto' ),
                'default' => __( 'Pay in 3 equal parts. At no extra charge.', 'woo-esto' ),
            ],
            'description' => [
                'title' => __( 'Description', 'woo-esto' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'woo-esto' ),
                'default' => __( 'ESTO 3 - pay without additional cost within 3 months! Just pay later.', 'woo-esto' ),
            ],
            'show_calculator' => [
                'title' => __( 'Show calculator', 'woo-esto' ),
                'type' => 'checkbox',
                'label' => __( 'Show calculator in payment method\'s description area in checkout.', 'woo-esto' ),
                'default' => 'no',
            ],
        ]
        + $this->description_logos
        + [
            'show_logo' => $this->form_fields['show_logo'],
            'logo' => $this->form_fields['logo'],
        ]
        + $this->language_specific_logos
        + [
            'min_amount' => $this->form_fields['min_amount'],
            'max_amount' => $this->form_fields['max_amount'],
            'only_specific_countries' => $this->form_fields['only_specific_countries'],
            'disabled_countries_for_this_method' => $this->form_fields['disabled_countries_for_this_method'],
            'set_on_hold_status' => $this->form_fields['set_on_hold_status'],
            'order_prefix' => $this->form_fields['order_prefix'],
        ];

        $this->form_fields['min_amount']['default'] = 0.1;
        $this->form_fields['max_amount']['default'] = 10000;
    }

	/**
	 * Displays the payment fields during the checkout.
	 * If the "show_calculator" option is enabled, a payment calculator will be displayed.
	 */
    public function payment_fields(): void {
        if ($this->show_calculator) {
            self::display_calculator();
        }

        parent::payment_fields();
    }

	/**
	 * Displays the ESTO X payment calculator in the checkout.
	 * 
	 * The calculator divides the total cart amount into 3 equal payments and displays
	 * the breakdown of each payment along with the date on which the payment is due.
	 */
    public static function display_calculator(): void {
        $segments = 3;
        $period = 3;
        $frequency = $period / $segments;

        $multiplier = 100;

        $total = WC()->cart->get_total('raw');
        if (!is_numeric($total)) {
            $total = WC()->cart->cart_contents_total;
        }

        $total *= $multiplier;

        $segment_amount = $total / $segments;
        $modulo = $total % $segments;
        $segment_amount = ( $total - $modulo ) / $segments;
        $cents = $modulo;

        ?>
        <div class="esto-x-calc">
            <div class="esto-x-calc__title">
                <?php echo sprintf( __( '%d interest-free payments over %d months', 'woo-esto' ), $segments, $period ); ?>
            </div>
            <div class="esto-x-calc__segments">
                <?php for ($i = 1; $i <= $segments; $i++) : ?>
                    <div class="esto-x-calc__segment" data-segment="<?php echo $i; ?>/<?php echo $segments; ?>">
                        <div class="esto-x-calc__img-wrap">
                            <img src="<?php echo self::$plugin_url . 'assets/images/' . $i . '_3-slice-big.svg'; ?>" width="56" height="56">
                        </div>
                        <div class="esto-x-calc__amount">
                            <?php
                            $amount = $segment_amount;
                            if ($cents > 0) {
                                $amount++;
                                $cents--;
                            }
                            $amount /= $multiplier;
                            echo function_exists('wc_price') ? wc_price($amount) : ( $amount . '€' );
                            ?>
                        </div>
                        <div class="esto-x-calc__date">
                            <?php echo date_i18n('M', strtotime('first day of +' . $i * $frequency . ' months')); ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
    }
    
	/**
	 * Enqueues the required CSS for displaying the calculator on the checkout page.
	 */
    public function enqueue(): void {
        if (is_checkout() && $this->enabled === 'yes' && $this->show_calculator) {
            wp_enqueue_style(
                'woo-esto-checkout-esto-x-css',
                plugins_url('assets/css/checkout-esto-x.css', dirname(__FILE__)),
                [],
                filemtime(dirname(__FILE__, 2) . '/assets/css/checkout-esto-x.css')
            );
        }
    }
}
