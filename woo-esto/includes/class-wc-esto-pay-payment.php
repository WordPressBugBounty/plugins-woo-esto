<?php

/**
 * ESTO Pay Payment Gateway class.
 *
 * This class handles ESTO Pay integration for WooCommerce.
 *
 * @package Woo_Esto_Pay
 */

class WC_Esto_Pay_Payment extends WC_Esto_Payment
{
    /**
     * Class properties.
     * These need to match the visibility in the parent class WC_Esto_Payment.
     */
    public $schedule_type;
    public $id;
    public $method_title;
    public $method_description;
    public $admin_page_title;
    public $min_amount;
    public $max_amount;

    /**
     * Constructor for the payment gateway.
     */
    function __construct()
    {
        $this->id = 'esto_pay';
        $this->method_title = __('ESTO Pay', 'woo-esto');
        $this->method_description = __('Payment is made using a secure payment solution called KEVIN (UAB “KEVIN EU”), which is licensed by the Bank of Lithuania.', 'woo-esto');
        $this->schedule_type = 'ESTO_PAY';

        parent::__construct();

        $this->admin_page_title = __('ESTO Pay payment gateway', 'woo-esto');
        $this->min_amount = 0.1;
        $this->max_amount = 999999;

        // Display logos even without description
        $this->has_fields = $this->get_option('show_bank_logos') !== 'no';

        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Initialize the form fields for the payment gateway.
     */
    function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woo-esto'),
                'type' => 'checkbox',
                'label' => __('ESTO Pay is a direct payment method for credit cards, banklinks, etc. Contact ESTO support for additional information.', 'woo-esto'),
                'default' => 'no',
            ],
            'show_logo' => [
                'title' => __('Show Logo', 'woo-esto'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'logo' => [
                'title' => __('Logo', 'woo-esto'),
                'type' => 'text',
            ],
            'show_bank_logos' => [
                'title' => __('Show bank logos', 'woo-esto'),
                'type' => 'checkbox',
                'label' => __('This option enables showing country dropdown and bank logos', 'woo-esto'),
                'default' => 'yes',
            ],
            'bank_logos_layout' => [
                'title' => __('Bank logos layout', 'woo-esto'),
                'type' => 'select',
                'options' => [
                    'columns-1' => __('1 column', 'woo-esto'),
                    'row' => __('Row', 'woo-esto'),
                    'columns-2' => __('2 columns', 'woo-esto'),
                    'columns-3' => __('3 columns', 'woo-esto'),
                    'columns-4' => __('4 columns', 'woo-esto'),
                ],
                'default' => 'columns-2',
            ],
            'disable_bank_preselect_redirect' => [
                'title' => __('Disable preselected bank redirect', 'woo-esto'),
                'type' => 'checkbox',
                'label' => __('Disables redirection to the bank selected in checkout on Esto webpage', 'woo-esto'),
                'default' => 'no',
            ],
            'countries' => [
                'title' => __('Countries', 'woo-esto'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => WC()->countries ? WC()->countries->get_countries() : esto_get_countries(),
                'default' => [],
                'description' => __('Specify countries for ESTO Pay method.', 'woo-esto'),
                'desc_tip' => true,
            ],
            'set_on_hold_status' => $this->form_fields['set_on_hold_status'] ?? [],
            'order_prefix' => $this->form_fields['order_prefix'] ?? [],
        ];
    }

    /**
     * Enqueue scripts and styles for the payment gateway.
     */
    public function enqueue()
    {
        if (is_checkout()) {
            wp_enqueue_style(
                'woo-esto-checkout-css',
                plugins_url('assets/css/checkout.css', dirname(__FILE__)),
                [],
                filemtime(dirname(__FILE__, 2) . '/assets/css/checkout.css')
            );

            wp_enqueue_script(
                'woo-esto-checkout-js',
                plugins_url('assets/js/checkout.js', dirname(__FILE__)),
                ['jquery'],
                filemtime(dirname(__FILE__, 2) . '/assets/js/checkout.js'),
                true
            );
        }
    }

    /**
     * Display the payment fields for the gateway.
     */
    public function payment_fields()
    {
        $description = __('Payment is made using a secure payment solution called KEVIN (UAB “KEVIN EU”), which is licensed by the Bank of Lithuania.', 'woo-esto');
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        $this->print_bank_logos_html();
    }

    /**
     * Display the bank logos dropdown and layout.
     */
    public function print_bank_logos_html() {
        if ( $this->get_option( 'show_bank_logos' ) == 'no' ) {
            return;
        }

        // Get connection mode
        $test_mode = false; // by default
        $payment_settings = get_option('woocommerce_esto_settings', null);
        if ($payment_settings && !empty($payment_settings['connection_mode']) && $payment_settings['connection_mode'] == 'test') $test_mode = true;

        /** @var array $country_keys */
        $country_keys = $this->get_option( 'countries', ['EE', 'LV', 'LT']);

        $wc_countries = WC()->countries->get_countries();
        $countries = [];

        foreach ($country_keys as $country_key)
        {
            $countries[strtolower($country_key)] = __( $wc_countries[$country_key], 'woocommerce' );
        }

        $logos              = WC()->session->get( 'esto_logos' );
        $check_country_keys = WC()->session->get( 'esto_country_keys');
        $check_logos_time   = ( (int) WC()->session->get( 'esto_logos_time_' . esto_get_country(), time() ) + 600 );

        if ($check_logos_time < time() || empty($logos) || ($check_country_keys !== implode('-', $country_keys)))  {

            $logos = [];

            foreach ( $countries as $key => $val ) {

                $url  = esto_get_api_url() . "v2/purchase/payment-methods?country_code=" . strtoupper( $key );
                if ($test_mode) $url .= "&test_mode=1";
                $curl = curl_init( $url );
                curl_setopt( $curl, CURLOPT_URL, $url );
                curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

                curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json, application/x-www-form-urlencoded"
                ) );

                curl_setopt( $curl, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key );
                curl_setopt( $curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );

                curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false );
                curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );

                $resp = curl_exec( $curl );
                curl_close( $curl );

                $data = json_decode( $resp );

                if ( ! empty( $data ) ) {

                    foreach ( $data as $row ) {

                        if ( $row->type != 'BANKLINK' ) {
                            continue;
                        }

                        if ( isset( $logos[ $key ] ) === false ) {
                            $logos[ $key ] = [];
                        }

                        $logos[ $key ][] = $row;
                    }
                }
            }

            WC()->session->set('esto_logos', $logos);
            WC()->session->set('esto_country_keys', implode('-', $country_keys));
            WC()->session->set('esto_logos_time_' . esto_get_country(), time());

        }

        switch (esto_get_api_url()) {
            case WOO_ESTO_API_URL_LT:
                $default_country = 'lt';
                break;
            case WOO_ESTO_API_URL_LV:
                $default_country = 'lv';
                break;
            default:
                $default_country = 'ee';
        }

        $layout = $this->get_option( 'bank_logos_layout' );

        if ( method_exists( WC()->customer, 'get_billing_country' ) ) {
            $current_country = WC()->customer->get_billing_country();
        }
        else {
            $current_country = WC()->customer->get_country();
        }

        $current_country = strtolower( $current_country );

        if ( isset( $logos[ $current_country ] ) ) {
            $default_country = $current_country;
        }

        ?>
        <select class="esto-pay-countries">
            <?php foreach ( $countries as $country_code => $country_name ) : ?>
                <option value="<?= $country_code ?>"<?php selected( $default_country, $country_code, true ) ?>><?= $country_name ?></option>
            <?php endforeach; ?>
        </select>

        <div class="esto-pay-logos esto-pay-logos-layout-<?= $layout ?>">
            <input type="hidden" name="esto_pay_bank_selection" value="">
            <?php foreach ( $logos as $country_key => $country_logos ) :
                $style = $country_key != $default_country ? ' style="display: none;"' : '';
                ?>
                <div class="esto-pay-logos__country esto-pay-logos__country--<?= $country_key ?>"<?= $style ?>>
                    <?php foreach ( $country_logos as $logo ) : ?>
                        <div class="esto-pay-logo esto-pay-logo__<?= strtolower($logo->name) ?>" data-bank-id="<?= $logo->key ?>">
                            <img src="<?= apply_filters( 'woo_esto_banklink_logo', $logo->logo_url, $logo->key, $country_key ) ?>">
                        </div>
                              <?php endforeach; ?>
                </div>
                  <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Validate the fields on the checkout page.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if ($this->get_option('disable_bank_preselect_redirect') !== 'yes' && empty($_REQUEST['esto_pay_bank_selection']) && empty($_POST['esto_pay_bank_selection'])) {
            wc_add_notice(__('Please select a bank', 'woo-esto'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Helper function to check if test mode is enabled.
     *
     * @return bool
     */
    private function is_test_mode_enabled()
    {
        $payment_settings = get_option('woocommerce_esto_settings', null);
        return $payment_settings && !empty($payment_settings['connection_mode']) && $payment_settings['connection_mode'] === 'test';
    }

    /**
     * Helper function to fetch bank logos from ESTO API.
     *
     * @param array $countries List of country keys.
     * @param bool  $test_mode Test mode flag.
     * @return array
     */
    private function fetch_bank_logos(array $countries, bool $test_mode)
    {
        $logos = WC()->session->get('esto_logos');
        $country_keys = implode('-', array_keys($countries));
        $check_logos_time = ((int) WC()->session->get('esto_logos_time_' . esto_get_country(), time()) + 600);

        if ($check_logos_time < time() || empty($logos) || WC()->session->get('esto_country_keys') !== $country_keys) {
            $logos = [];

            foreach ($countries as $key => $val) {
                $url = esto_get_api_url() . "v2/purchase/payment-methods?country_code=" . strtoupper($key) . ($test_mode ? "&test_mode=1" : "");
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->shop_id . ':' . $this->secret_key),
                    ],
                ]);

                if (is_wp_error($response)) {
                    continue;
                }

                $data = json_decode(wp_remote_retrieve_body($response));

                if (!empty($data)) {
                    foreach ($data as $row) {
                        if ($row->type === 'BANKLINK') {
                            $logos[$key][] = $row;
                        }
                    }
                }
            }

            WC()->session->set('esto_logos', $logos);
            WC()->session->set('esto_country_keys', $country_keys);
            WC()->session->set('esto_logos_time_' . esto_get_country(), time());
        }

        return $logos;
    }
}
