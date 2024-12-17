<?php

if (!class_exists('EstoRequest')) {
    require_once('Request.php');
}
if (!class_exists('WC_Esto_Payment')) {
    require_once('Payment.php');
}

class WC_Esto_Calculator
{
    private static $plugin_url;
    private static $plugin_dir;
    private static $plugin_title = 'ESTO Product Calculator';
    private static $plugin_slug = 'esto-calculator-settings';
    private static $esto_option_key = 'esto-calculator-settings';
    private $esto_calc_settings;
    private $shopId;
    private static $is_current_billing_country_disabled = false;

    const MIN_PRICE_DEFAULT = 30;
    const MAX_PRICE_DEFAULT = 10000;

    public function __construct()
    {
        global $esto_plugin_dir, $esto_plugin_url;

        self::$plugin_url = $esto_plugin_url;
        self::$plugin_dir = $esto_plugin_dir;

        // Ensure $this->esto_calc_settings is initialized as an array
        $this->esto_calc_settings = get_option(self::$esto_option_key, []);

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_script']);

        if ($this->get_setting('enable_calc')) {
            add_action('wp_head', [$this, 'wp_head']);
            add_action('woocommerce_single_product_summary', [$this, 'display_calculator'], 8);

            if (apply_filters('woo_esto_show_monthly_payment_on_archive_pages', $this->get_setting('show_monthly_payment_on_archive_pages'))) {
                add_action('woocommerce_after_shop_loop_item_title', [$this, 'display_calculator_for_archive'], 15);
            }
        }
    }


    public static function is_current_billing_country_disabled()
    {
        $payment_settings = get_option('woocommerce_esto_settings', null);
        if ($payment_settings && isset($payment_settings['disabled_countries'])) {
            $disabled_countries = $payment_settings['disabled_countries'];

            if (!empty($disabled_countries)) {
                $customer = WC()->customer;
                if ($customer) {
                    $country = method_exists(WC()->customer, 'get_billing_country')
                        ? WC()->customer->get_billing_country()
                        : WC()->customer->get_country();

                    if (in_array($country, $disabled_countries)) {
                        self::$is_current_billing_country_disabled = true;
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function display_calculator_for_archive()
    {
        if (!is_single()) {
            $this->display_calculator();
        }
    }

    /**
     * Displays the Esto calculator on the product page.
     * Allow Advanced Dynamic Pricing for WooCommerce to override the price.
     *
     * @return void
     */
    public function display_calculator(): void
    {
        if (self::$is_current_billing_country_disabled || self::is_current_billing_country_disabled()) {
            return;
        }

        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        $this->shopId = esto_get_api_field('shop_id');

        // Fetch the price: prioritize sale price if applicable
        $price = $product->is_on_sale()
            ? $product->get_sale_price()
            : $product->get_regular_price();

        $price = wc_get_price_to_display($product, ['price' => $price]);

        if (!is_numeric($price) || $price <= 0) {
            return; // Prevent further calculations if price is invalid
        }

        $estoMonthlyPayment = false;
        $period_months = null;

        $min_price = $this->get_setting('minimum_price') ?: self::MIN_PRICE_DEFAULT;
        $max_price = $this->get_setting('maximum_price') ?: self::MAX_PRICE_DEFAULT;

        if ($price >= $min_price && $price <= $max_price) {
            $show_esto_3 = $this->get_setting('show_esto_3') ?? false;

            if ($show_esto_3) {
                $estoMonthlyPayment = wc_price($price / 3);
            } else {
                $res = $this->get_product_price_from_api($product);

                if ($res && isset($res->monthly_payment)) {
                    $estoMonthlyPayment = wc_price($res->monthly_payment);
                    $period_months = $res->period_months ?? null;
                }
            }

            if ($estoMonthlyPayment) {
                $current_language = apply_filters('wpml_current_language', false);
                $calc_text = $this->get_setting($current_language ? 'calc_text_' . $current_language : 'calc_text');

                if (is_single()) {
                    $logoSrc = null;
                    $logo_width = 110;
                    $logo_height = 0;

                    $esto_calc_logo = $this->get_setting('esto_calc_logo');
                    if (!empty($esto_calc_logo)) {
                        $image_attributes = wp_get_attachment_image_src($esto_calc_logo, 'full');
                        if ($image_attributes) {
                            [$logoSrc, $logo_width, $logo_height] = $image_attributes;
                        }
                    }

                    if (!$current_language) {
                        $current_language = substr(get_locale(), 0, 2);
                    }

                    $calculator_logo_url = $this->get_setting('calculator_logo_url_' . $current_language);
                    if ($calculator_logo_url) {
                        $logo_attachment = wp_get_attachment_image_src($calculator_logo_url, 'full');
                        if ($logo_attachment) {
                            [$logoSrc, $logo_width, $logo_height] = $logo_attachment;
                        }
                    }

                    require_once self::$plugin_dir . 'assets/view/calculator.php';
                } else {
                    include self::$plugin_dir . 'assets/view/calculator-archive.php';
                }
            }
        }
    }

    public function get_product_price_from_api($product)
    {
        $product_id = $product->get_id();
        $country = esto_get_country();
        $transient_name = "woo_esto_product_{$product_id}_monthly_payment_{$country}";

        $price = wc_get_price_to_display($product);
        $res = get_transient($transient_name);

        if (!$res) {
            $res = $this->restApi('payments', [
                'amount' => $price,
                'shop_id' => $this->shopId,
            ]);
            if ($res) {
                set_transient($transient_name, $res, WEEK_IN_SECONDS);
            }
        }

        return $res;
    }

    /**
     * Enqueue calculator styles.
     * Using jquery to add custom styles to the page.
     *
     * @return void
     */
    public function wp_head()
    {
        wp_enqueue_script('jquery');
        ?>
        <script>
            (function ($) {
                const styles = `
                .monthly_payment {
                    font-size: 12px;
                }
                .products .product .esto_calculator {
                    margin-bottom: 16px;
                }
            `;
                const styleSheet = document.createElement('style');
                styleSheet.type = 'text/css';
                styleSheet.innerText = styles;
                document.head.appendChild(styleSheet);
            })(jQuery);
        </script>
        <?php
    }

    public function admin_menu()
    {
        $wc_page = 'woocommerce';
        add_submenu_page($wc_page, self::$plugin_title, self::$plugin_title, "install_plugins", self::$plugin_slug, [$this, "calculator_setting_page"]);
    }

    public function admin_script()
    {
        if (is_admin()) {
            wp_enqueue_media();
            wp_enqueue_style('esto-admin', self::$plugin_url . "assets/css/admin.css");
        }
    }

    public function esto_image_uploader($optionName)
    {
        $srcName = $this->get_setting($optionName);
        $default_image = 'https://via.placeholder.com/115x115';

        if (!empty($srcName)) {
            $image_attributes = wp_get_attachment_image_src($srcName, 'full');
            $src = $image_attributes[0];
            $value = $srcName;
        } else {
            $src = $default_image;
            $value = '';
        }

        $this->esto_calc_settings['logo_src'] = $src;
        $this->esto_calc_settings['logo_value'] = $value;
    }

    public function calculator_setting_page()
    {
        if (isset($_POST[self::$plugin_slug]) && check_admin_referer('esto_calculator_settings')) {
            $this->saveSetting();
        }

        $this->esto_image_uploader('esto_calc_logo');
        include_once self::$plugin_dir . "assets/view/calculator-settings.php";
    }

    public function saveSetting()
    {
        $saveData = [];
        foreach ($_POST as $key => $value) {
            if ($key === self::$plugin_slug || $key === "btn-esto-submit") continue;
            $saveData[$key] = $value;
        }

        $this->esto_calc_settings = $saveData;
        update_option(self::$esto_option_key, $saveData);
    }

    public function get_setting($key)
    {
        return $this->esto_calc_settings[$key] ?? null;
    }

    public function restApi($service, $data = [], $method = 'GET')
    {
        $url = esto_get_api_url() . "v2/calculate/{$service}";

        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);

        $data = json_encode($data);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        return json_decode($response);
    }
}
