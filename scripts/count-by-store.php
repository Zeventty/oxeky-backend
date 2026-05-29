<?php
require_once dirname(__DIR__) . '/wp-load.php';

global $wpdb;

$stores = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_dropi_token_store'");
echo "Stores found: " . implode(', ', $stores) . "\n";

foreach ($stores as $store) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p 
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
         WHERE pm.meta_key = '_dropi_token_store' 
         AND pm.meta_value = %s 
         AND p.post_type = 'product' 
         AND p.post_status = 'publish'",
        $store
    ));
    echo "  {$store}: {$count} products\n";
}
