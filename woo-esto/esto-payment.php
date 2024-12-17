<?php
/*
  Plugin Name: Woocommerce ESTO
  Plugin URI:  https://www.esto.ee
  Description: Adds ESTO redirect link to a Woocommerce instance
  Version:     2.25.8
  Author:      Mikk Mihkel Nurges, Rebing OÜ
  Author URI:  www.rebing.ee
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  Text Domain: woo-esto
  WC tested up to: 9.4.3

  Woocommerce ESTO is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.

  Woocommerce ESTO is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.
*/

if (!defined('ABSPATH')) {
	exit;
}

global $esto_plugin_url, $esto_plugin_dir;
$esto_plugin_dir = plugin_dir_path(__FILE__);
$esto_plugin_url = plugin_dir_url(__FILE__);

if (!defined('WOO_ESTO_API_URL_EE')) {
	define('WOO_ESTO_API_URL_EE', 'https://api.esto.ee/');
}

if (!defined('WOO_ESTO_API_URL_LT')) {
	define('WOO_ESTO_API_URL_LT', 'https://api.estopay.lt/');
}

if (!defined('WOO_ESTO_API_URL_LV')) {
	define('WOO_ESTO_API_URL_LV', 'https://api.esto.lv/');
}

if (!function_exists('is_plugin_active_for_network')) {
	require_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

if (
	in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
	|| is_plugin_active_for_network('woocommerce/woocommerce.php')
	|| class_exists('WooCommerce')
) {
	add_action('plugins_loaded', 'init_woocommerce_esto_payment');

	add_action('init', function () {
		load_plugin_textdomain('woo-esto', false, dirname(plugin_basename(__FILE__)) . '/assets/i18n');
	});

	if (!class_exists('WC_Esto_Payment')) {
		require_once $esto_plugin_dir . 'includes/Payment.php';
	}

	if (!class_exists('WC_Esto_Calculator')) {
		require_once $esto_plugin_dir . 'includes/Calculator.php';
	}

	global $esto;
	$esto = new WC_Esto_Calculator();

	add_shortcode('esto_monthly_payment', [$esto, 'display_calculator']);
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'esto_add_action_links');
	add_filter('woocommerce_cancel_unpaid_order', 'esto_prevent_cancelling_orders_early', 10, 2);
	add_action('woocommerce_blocks_loaded', 'esto_gateway_blocks');
}

/**
 * Registers payment blocks for Woocommerce blocks integration.
 *
 * @return void
 */
function esto_gateway_blocks()
{
	require_once __DIR__ . '/includes/block-gateways/class-esto-payment-block.php';
	require_once __DIR__ . '/includes/block-gateways/class-esto-x-payment-block.php';
	require_once __DIR__ . '/includes/block-gateways/class-pay-later-payment-block.php';
	require_once __DIR__ . '/includes/block-gateways/class-esto-pay-payment-block.php';
	require_once __DIR__ . '/includes/block-gateways/class-esto-card-payment-block.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			$payment_method_registry->register(new WC_Esto_Payment_Block);
			$payment_method_registry->register(new WC_Esto_X_Payment_Block);
			$payment_method_registry->register(new WC_Esto_Pay_Later_Payment_Block);
			$payment_method_registry->register(new WC_Esto_Pay_Payment_Block);
			$payment_method_registry->register(new WC_Esto_Card_Payment_Block);
		}
	);
}

/**
 * Adds settings link to the plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links with settings link.
 */
function esto_add_action_links($links)
{
	$plugin_links = [
		'<a href="' . admin_url('admin.php?page=esto-calculator-settings') . '">' . __('Settings', 'woo-esto') . '</a>',
	];
	return array_merge($links, $plugin_links);
}

/**
 * Retrieves the API URL based on the settings.
 *
 * @return string|false API URL if found, or false if not found.
 */
function esto_get_api_url_from_options()
{
	if (defined('WOO_ESTO_API_URL_DEV')) {
		return WOO_ESTO_API_URL_DEV;
	}

	$payment_settings = get_option('woocommerce_esto_settings', null);

	if ($payment_settings && !empty($payment_settings['endpoint'])) {
		return $payment_settings['endpoint'];
	}

	return false;
}

/**
 * Returns the API URL based on the selected country.
 *
 * @param string $country Country code (optional).
 * @return string The API URL for the specified country.
 */
function esto_get_api_url($country = '')
{
	if (!$country) {
		$country = esto_get_country();
	}

	$country = strtoupper($country);

	if ($country === 'LT' && esto_api_config_is_active_for_country('lt')) {
		return WOO_ESTO_API_URL_LT;
	} elseif ($country === 'LV' && esto_api_config_is_active_for_country('lv')) {
		return WOO_ESTO_API_URL_LV;
	} elseif ($country === 'EE' && esto_api_config_is_active_for_country('ee')) {
		return WOO_ESTO_API_URL_EE;
	} else {
		$selected_endpoint_url = esto_get_api_url_from_options();
		return $selected_endpoint_url ?: WOO_ESTO_API_URL_EE;
	}
}

/**
 * Checks if API configuration is active for the given country.
 *
 * @param string $country The country code.
 * @return bool True if API configuration is active, false otherwise.
 */
function esto_api_config_is_active_for_country($country)
{
	$payment_settings = get_option('woocommerce_esto_settings', null);

	if (
		$payment_settings
		&& !empty($payment_settings['use_secondary_endpoint_' . $country])
		&& $payment_settings['use_secondary_endpoint_' . $country] === 'yes'
		&& !empty($payment_settings['shop_id_' . $country])
		&& !empty($payment_settings['secret_key_' . $country])
	) {
		return true;
	}

	return false;
}

/**
 * Gets the country based on customer details or request parameters.
 *
 * @return string The country code.
 */
function esto_get_country()
{
	if (isset($_REQUEST['esto_api_country_code'])) {
		if (in_array($_REQUEST['esto_api_country_code'], ['ee', 'lv', 'lt'])) {
			return $_REQUEST['esto_api_country_code'];
		}
		return '';
	}

	$country = '';

	if (function_exists('WC')) {
		$customer = WC()->customer;

		if ($customer) {
			if (method_exists(WC()->customer, 'get_billing_country')) {
				$country = WC()->customer->get_billing_country();
			} else {
				$country = WC()->customer->get_country();
			}
		}
	}

	return strtolower($country);
}

/**
 * Retrieves the specified field from the API settings for the active country.
 *
 * @param string $field The field to retrieve (e.g., 'shop_id' or 'secret_key').
 * @return mixed The value of the field, or false if not found.
 */
function esto_get_api_field($field)
{
	$country = esto_get_country();
	$payment_settings = get_option('woocommerce_esto_settings', null);
	$field_value = false;

	if ($payment_settings && $country && esto_api_config_is_active_for_country($country)) {
		$field_value = $payment_settings[$field . '_' . $country];
	} else {
		$field_value = $payment_settings[$field] ?? false;
	}

	return apply_filters('esto_get_api_field', $field_value, $field);
}

/**
 * Logs messages to the WooCommerce log or fallback to error_log if WooCommerce logger is not available.
 *
 * @param string $message The message to log.
 * @param string $level Log level (e.g., 'info', 'error').
 * @return void
 */
function woo_esto_log($message, $level = 'info')
{
	if (function_exists('wc_get_logger')) {
		$logger = wc_get_logger();
		if (method_exists($logger, 'log')) {
			$logger->log($level, $message, ['source' => 'woo-esto']);
			return;
		}
	}

	error_log($message);
}

/**
 * Prevents orders with ESTO payment methods from being canceled too early.
 *
 * @param bool $can_cancel Whether the order can be canceled.
 * @param WC_Order $order The WooCommerce order object.
 * @return bool Whether the order can be canceled.
 */
function esto_prevent_cancelling_orders_early($can_cancel, $order)
{
	$payment_method = $order->get_payment_method();

	if ($payment_method === 'esto_pay') {
		$order_date = $order->get_date_modified();
		if ($order_date && ($order_date->getTimestamp() + DAY_IN_SECONDS) > time()) {
			$can_cancel = false;
		}
	} elseif (in_array($payment_method, ['esto', 'esto_x', 'pay_later'])) {
		$order_date = $order->get_date_modified();
		if ($order_date && ($order_date->getTimestamp() + 3 * DAY_IN_SECONDS) > time()) {
			$can_cancel = false;
		}
	}

	return $can_cancel;
}

/**
 * Retrieves the list of countries for WooCommerce.
 *
 * @return array List of countries.
 */
function esto_get_countries()
{
	$countries = apply_filters('woocommerce_countries', include WC()->plugin_path() . '/i18n/countries.php');
	if (apply_filters('woocommerce_sort_countries', true) && function_exists('wc_asort_by_locale')) {
		wc_asort_by_locale($countries);
	}
	return $countries;
}

/**
 * Removes 'utm_nooverride' from the return URL for WooCommerce Google Analytics Integration compatibility.
 *
 * @param string $return_url The return URL.
 * @return string Modified return URL.
 */
function esto_remove_utm_nooverride($return_url)
{
	return remove_query_arg('utm_nooverride', $return_url);
}

if (!empty($_REQUEST['esto_auto_callback'])) {
	add_filter('woocommerce_ga_disable_tracking', '__return_true');
}

add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
});

/**
 * Filter to override the payment gateway title for ESTO Pay.
 *
 * This filter ensures the payment method title is fixed to "Pay in the bank"
 * regardless of the admin settings or database values. Needed for supporting different languages
 *
 * @param string $title
 * @param string $gateway_id
 * @return string
 */
add_filter('woocommerce_gateway_title', function ($title, $gateway_id) {
    if ($gateway_id === 'esto_pay') {
        return __('Pay in the bank', 'woo-esto');
    }
    return $title;
}, 10, 2);