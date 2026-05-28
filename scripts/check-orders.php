<?php
require_once dirname(__DIR__) . '/wp-load.php';

echo 'dropi-woocomerce-autosync_orders: ' . var_export(get_option('dropi-woocomerce-autosync_orders'), true) . "\n";
echo 'WooCommerce active: ' . (is_plugin_active('woocommerce/woocommerce.php') ? 'yes' : 'no') . "\n";
echo 'Dropi active: ' . (is_plugin_active('wc-dropi-integration/wc-dropi-integration.php') ? 'yes' : 'no') . "\n";

global $wpdb;
$synced = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_is_dropi_order' AND meta_value='Yes'");
$total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status NOT IN ('auto-draft', 'trash')");
echo "Orders synced to Dropi: {$synced} / {$total_orders} total\n";

// List orders with their sync status
$orders = $wpdb->get_results("SELECT p.ID, p.post_status FROM {$wpdb->posts} p WHERE p.post_type='shop_order' AND p.post_status NOT IN ('auto-draft', 'trash') ORDER BY p.ID DESC LIMIT 10");
foreach ($orders as $o) {
    $is_dropi = get_post_meta($o->ID, '_is_dropi_order', true);
    $dropi_id = get_post_meta($o->ID, '_dropi_order_id', true);
    $dropi_token = get_post_meta($o->ID, '_dropi_token', true);
    echo "Order #{$o->ID}: status={$o->post_status} is_dropi={$is_dropi} dropi_id={$dropi_id} token=" . substr($dropi_token, 0, 20) . "...\n";
}

// Check Dropi tokens
$tokens = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dropi_tokens");
echo "\nDropi Tokens:\n";
foreach ($tokens as $t) {
    echo "  id={$t->id} store={$t->store} sync={$t->sync}\n";
}
