<?php
require_once dirname(__DIR__) . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Deactivate old dropi-auto-tags
if (is_plugin_active('dropi-auto-tags/dropi-auto-tags.php')) {
    deactivate_plugins('dropi-auto-tags/dropi-auto-tags.php');
    echo "Deactivated: dropi-auto-tags\n";
}

// Activate dropi-customer
if (!is_plugin_active('dropi-customer/dropi-customer.php')) {
    $result = activate_plugin('dropi-customer/dropi-customer.php');
    if (is_wp_error($result)) {
        echo "Error activating dropi-customer: " . $result->get_error_message() . "\n";
    } else {
        echo "Activated: dropi-customer\n";
    }
} else {
    echo "dropi-customer already active\n";
}

// List active plugins
$active = get_option('active_plugins');
echo "\nActive plugins:\n";
foreach ($active as $p) {
    echo "  - {$p}\n";
}
