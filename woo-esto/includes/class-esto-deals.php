<?php

/**
 * Main class for managing ESTO Deals plugin functionality.
 */
class Esto_Deals
{
    /**
     * Constructor to initialize the plugin.
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Load required dependencies for the plugin.
     *
     * @return void
     */
    private function load_dependencies(): void
    {
        require_once plugin_dir_path(__FILE__) . 'class-deals-setup.php';
        require_once plugin_dir_path(__FILE__) . 'class-deals-tracking.php';
    }

    /**
     * Register hooks and filters for the plugin.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        $setup = new Deals_Setup();
        $tracking = new Deals_Tracking();

        add_action('admin_menu', [$setup, 'register_admin_menu']);
        add_action('admin_init', [$setup, 'register_settings']);
        add_action('admin_footer', [$setup, 'conditional_js']);
        add_action('wp_footer', [$tracking, 'enqueue_tracking_script']);
        add_action('woocommerce_thankyou', [$tracking, 'handle_order_tracking']);
        add_action('woocommerce_checkout_create_order', [$tracking, 'add_tracking_meta_to_order'], 20, 1);
        add_action('admin_notices', [$setup, 'display_admin_notices']);
    }
}
