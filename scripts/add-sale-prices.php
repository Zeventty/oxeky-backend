<?php
/**
 * Adds sale prices to existing Dropi products.
 * Current price → sale price, higher regular price → normal price.
 * Run: php scripts/add-sale-prices.php
 */

require_once dirname(__DIR__) . '/wp-load.php';

if (!function_exists('wc_get_products')) {
    die("WooCommerce not available\n");
}

$stores = array('oxeky-ve' => 'Venezuela', 'oxeky-pe' => 'Peru');

foreach ($stores as $store_value => $store_label) {
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish',
        'meta_key' => '_dropi_token_store',
        'meta_value' => $store_value,
    ));

    echo "\n=== {$store_label}: found " . count($products) . " products ===\n";

    $count = 0;
    foreach ($products as $product) {
        if ($count >= 15) break;

        $current_price = $product->get_price();
        if (empty($current_price) || floatval($current_price) <= 0) {
            continue;
        }

        $current_price = floatval($current_price);

        // Random increase: 15% to 60% higher
        $multiplier = 1 + (rand(15, 60) / 100);
        $regular_price = round($current_price * $multiplier, 2);

        $product->set_regular_price($regular_price);
        $product->set_sale_price($current_price);
        $product->set_date_on_sale_from('');
        $product->set_date_on_sale_to('');

        $product->save();

        echo "  #{$product->get_id()} {$product->get_name()}: {$current_price} → regular {$regular_price} (sale)\n";
        $count++;
    }

    echo "  Updated {$count} products\n";
}

echo "\nDone!\n";
