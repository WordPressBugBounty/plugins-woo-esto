<?php

/**
 * Class responsible for tracking and managing WooCommerce orders with ESTO Deals.
 */
class Deals_Tracking
{
    /**
     * Enqueue tracking script for frontend tracking.
     *
     * @return void
     */
    public function enqueue_tracking_script(): void
    {
        if (get_option('enable_deals_tracking')) {
            ?>
            <script type="text/javascript" src="https://www.epbdf8trk.com/scripts/sdk/everflow.js"></script>
            <script type="text/javascript">
                EF.click({
                    offer_id: EF.urlParameter('oid'),
                    affiliate_id: EF.urlParameter('affid'),
                    uid: EF.urlParameter('uid'),
                    source_id: EF.urlParameter('source_id'),
                    transaction_id: EF.urlParameter('_ef_transaction_id'),
                });
            </script>
            <?php
        }
    }

    /**
     * Add tracking meta data to WooCommerce orders.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return void
     */
    public function add_tracking_meta_to_order(WC_Order $order): void
    {
        $tracking_enabled = get_option('enable_deals_tracking');
        $advertiser_id = get_option('everflow_advertiser_id');

        if ($tracking_enabled && $advertiser_id) {
            $eftid = isset($_COOKIE['ef_tid_c_a_' . $advertiser_id]) ? sanitize_text_field($_COOKIE['ef_tid_c_a_' . $advertiser_id]) : '';
            $order->update_meta_data('eftid', $eftid);
            $order->save();
        }
    }

    /**
     * Handle order tracking and send tracking data to Everflow.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public function handle_order_tracking(int $order_id): void
    {
        $tracking_enabled = get_option('enable_deals_tracking');
        $advertiser_id = get_option('everflow_advertiser_id');

        if (!$tracking_enabled || !$advertiser_id) {
            return; // Exit if tracking is disabled or advertiser ID is not set
        }

        // Get the order
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log_message("Order not found for order_id: {$order_id}", 'error');
            return;
        }

        // Check if the order has already been tracked
        if ($order->get_meta('_is_tracking_done')) {
            $this->log_message("Tracking already completed for order_id: {$order_id}", 'info');
            return;
        }

        // Ensure the order is paid
        if (!$order->is_paid()) {
            $this->log_message("Order not paid yet. Skipping tracking for order_id: {$order_id}", 'warning');
            return;
        }

        // Prepare tracking data
        $efOrder = [
            'oid' => $order_id,
            'amt' => $order->get_total(),
            'cc' => implode(',', $order->get_coupon_codes() ?: []),
            'items' => [],
        ];

        // Process line items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $efOrder['items'][] = [
                'ps' => $product->get_name() . (!empty($product->get_sku()) ? ' (' . $product->get_sku() . ')' : ''),
                'qty' => $item->get_quantity(),
                'p' => $order->get_line_total($item, true, true),
            ];
        }

        // Output Everflow tracking script
        echo '<script type="text/javascript" src="https://www.epbdf8trk.com/scripts/sdk/everflow.js"></script>';
        echo '<script type="text/javascript">
            EF.conversion({
                aid: ' . intval($advertiser_id) . ',
                amount: ' . ($order->get_total() - $order->get_shipping_total()) . ',
                order_id: "' . esc_js($order_id) . '",
                order: ' . json_encode($efOrder) . ',
            });
        </script>';

        // Log the tracking data
        $this->log_message('Tracking data sent: ' . print_r($efOrder, true), 'info');

        // Mark the order as tracked
        $order->update_meta_data('_is_tracking_done', true);
        $order->save();
    }

    /**
     * Log a message to WooCommerce logs.
     *
     * @param string $message The log message.
     * @param string $level The log level (info, warning, error).
     * @return void
     */
    private function log_message(string $message, string $level = 'info'): void
    {
        $logger = wc_get_logger();
        $context = ['source' => 'esto-deals'];
        $logger->$level($message, $context);
    }
}
