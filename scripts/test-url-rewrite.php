<?php
require_once dirname(__DIR__) . '/wp-load.php';

global $wpdb;

$token_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}dropi_tokens WHERE store='oxeky-ve' LIMIT 1");
if (!$token_row) {
    echo "No Venezuela token found\n";
    exit;
}

echo "Venezuela token found: " . substr($token_row->token, 0, 20) . "...\n";

// Test categories API call - should be rewritten to api.dropi.com.ve
$args = array(
    'timeout' => 30,
    'headers' => array(
        'dropi-integration-key' => $token_row->token,
    ),
);

$url = 'https://api.dropi.pe/integrations/categories/';
echo "\nTesting URL: $url\n";
echo "Expected rewritten: https://api.dropi.com.ve/integrations/categories/\n";

$response = wp_remote_get($url, $args);

if (is_wp_error($response)) {
    echo 'Error: ' . $response->get_error_message() . "\n";
    $args['sslverify'] = false;
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        echo 'Error after SSL fallback: ' . $response->get_error_message() . "\n";
    } else {
        echo 'Response code after SSL fallback: ' . wp_remote_retrieve_response_code($response) . "\n";
        $body = json_decode(wp_remote_retrieve_body($response), true);
        echo 'isSuccess: ' . (isset($body['isSuccess']) ? ($body['isSuccess'] ? 'true' : 'false') : 'not set') . "\n";
    }
} else {
    echo 'Response code: ' . wp_remote_retrieve_response_code($response) . "\n";
    $body = json_decode(wp_remote_retrieve_body($response), true);
    echo 'isSuccess: ' . (isset($body['isSuccess']) ? ($body['isSuccess'] ? 'true' : 'false') : 'not set') . "\n";
    if (isset($body['objects'])) {
        echo "Categories found: " . count($body['objects']) . "\n";
    }
}
