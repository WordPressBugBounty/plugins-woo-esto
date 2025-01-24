<?php

/**
 * Class responsible for setting up the ESTO Deals plugin settings and admin menu.
 */
class Deals_Setup
{
    /**
     * Register the admin menu for the plugin.
     *
     * @return void
     */
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('ESTO Deals', 'woo-esto'),
            __('ESTO Deals', 'woo-esto'),
            'manage_options',
            'esto-deals',
            [$this, 'render_settings_page'],
            'dashicons-cart',
            60
        );
    }

    /**
     * Render the settings page for the plugin.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('ESTO Deals', 'woo-esto'); ?></h1>
            <p><?php echo esc_html__('Enable ESTO Deals tracking to monitor completed purchases from Deals. Tracking triggers only when an order is paid and no personal data is shared.', 'woo-esto'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('esto_deals_settings_group');
        do_settings_sections('esto-deals');
        submit_button();
        ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings and fields for the plugin.
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting('esto_deals_settings_group', 'enable_deals_tracking', [
            'sanitize_callback' => [$this, 'handle_tracking_change']
        ]);
        register_setting('esto_deals_settings_group', 'everflow_advertiser_id');

        add_settings_section('esto_deals_settings_section', __('Deals Tracking Settings', 'woo-esto'), null, 'esto-deals');

        add_settings_field('enable_deals_tracking', __('Enable Deals Tracking', 'woo-esto'), function (): void {
            $value = get_option('enable_deals_tracking', 0);
            echo '<input type="checkbox" id="enable_deals_tracking" name="enable_deals_tracking" value="1" ' . checked(1, $value, false) . ' />';
        }, 'esto-deals', 'esto_deals_settings_section');

        add_settings_field('everflow_advertiser_id', __('Everflow Advertiser ID', 'woo-esto'), function (): void {
            $value = get_option('everflow_advertiser_id', '');
            echo '<input type="number" id="everflow_advertiser_id" name="everflow_advertiser_id" value="' . esc_attr($value) . '" />';
        }, 'esto-deals', 'esto_deals_settings_section');
    }

    /**
     * Add conditional JavaScript to the admin footer for dynamically showing/hiding fields.
     *
     * @return void
     */
    public function conditional_js(): void
    {
        if (isset($_GET['page']) && $_GET['page'] === 'esto-deals') {
            ?>
            <script type="text/javascript">
                (function ($) {
                    function toggleAdvertiserIdField() {
                        $('#everflow_advertiser_id').closest('tr').toggle($('#enable_deals_tracking').is(':checked'));
                    }
                    $(document).ready(function () {
                        toggleAdvertiserIdField();
                        $('#enable_deals_tracking').on('change', toggleAdvertiserIdField);
                    });
                })(jQuery);
            </script>
            <?php
        }
    }

    /**
     * Display admin notices for the plugin.
     *
     * @return void
     */
    public function display_admin_notices(): void
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            $tracking_enabled = get_option('enable_deals_tracking');
            $notice_class = $tracking_enabled ? 'notice-success' : 'notice-warning';
            $message = $tracking_enabled
                ? __('Settings saved. Tracking is enabled.', 'woo-esto')
                : __('Settings saved. Tracking has been disabled.', 'woo-esto');

            echo "<div class='notice {$notice_class} is-dismissible'><p>" . esc_html($message) . "</p></div>";
        }
    }

    /**
     * Handle changes to the tracking option.
     *
     * @param mixed $value The new value being set.
     * @return mixed The sanitized value.
     */
    public function handle_tracking_change(mixed $value): mixed
    {
        $logger = wc_get_logger();
        $context = ['source' => 'esto-deals'];

        $advertiser_id = get_option('everflow_advertiser_id', '');
        $webshop_url = get_site_url();
        $activity = $value ? 'enable' : 'disable';

        // Log activity
        $logger->info("Tracking {$activity}d with Advertiser ID: {$advertiser_id}, Webshop URL: {$webshop_url}", $context);

        return $value;
    }
}
