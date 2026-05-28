<?php
require_once dirname(__DIR__) . '/wp-load.php';

global $wpdb;

// Check all post types with shop_order
$post_types = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_type LIKE '%order%'");
echo "Post types containing 'order': " . implode(', ', $post_types) . "\n";

$all_statuses = $wpdb->get_col("SELECT DISTINCT post_status FROM {$wpdb->posts} WHERE post_type='shop_order'");
echo "Shop order statuses: " . implode(', ', $all_statuses) . "\n";

$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order'");
echo "Total shop_order posts: {$count}\n";

// Check the fake orders - maybe post_status issue
$orders = $wpdb->get_results("SELECT ID, post_status, post_date FROM {$wpdb->posts} WHERE post_type='shop_order' ORDER BY ID DESC LIMIT 20");
foreach ($orders as $o) {
    $is_dropi = get_post_meta($o->ID, '_is_dropi_order', true);
    echo "Order #{$o->ID}: status={$o->post_status} date={$o->post_date} is_dropi=" . var_export($is_dropi, true) . "\n";
}
