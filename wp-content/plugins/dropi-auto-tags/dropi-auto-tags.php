<?php
/**
 * Plugin Name: Dropi Auto Tags
 * Description: Asigna automáticamente tags de país a productos importados desde Dropi según la tienda de origen.
 * Version: 1.0
 * Author: Oxeky
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_post_meta', 'dropi_auto_tag_add', 10, 3);
add_action('update_post_meta', 'dropi_auto_tag_update', 10, 4);

function dropi_auto_tag_add($post_id, $meta_key, $meta_value) {
    dropi_auto_tag_asignar($post_id, $meta_key, $meta_value);
}

function dropi_auto_tag_update($meta_id, $post_id, $meta_key, $meta_value) {
    dropi_auto_tag_asignar($post_id, $meta_key, $meta_value);
}

function dropi_auto_tag_asignar($post_id, $meta_key, $meta_value) {
    if ($meta_key !== '_dropi_token_store') {
        return;
    }
    if (get_post_type($post_id) !== 'product') {
        return;
    }

    $tags_map = [
        'oxeky-pe' => 'pe',
        'oxeky-ar' => 'ar',
        'oxeky-bo' => 'bo',
        'oxeky-co' => 'co',
        'oxeky-ve' => 've',
        'oxeky-ec' => 'ec',
        'oxeky-cl' => 'cl',
        'oxeky-cr' => 'cr',
        'oxeky-es' => 'es',
    ];

    $tags_map = apply_filters('dropi_auto_tags_map', $tags_map);

    if (isset($tags_map[$meta_value])) {
        wp_set_object_terms($post_id, $tags_map[$meta_value], 'product_tag', true);
    }
}

add_action('add_post_meta', 'dropi_auto_tag_debug_add', 10, 3);
add_action('update_post_meta', 'dropi_auto_tag_debug_update', 10, 4);

function dropi_auto_tag_debug_add($post_id, $meta_key, $meta_value) {
    if ($meta_key === '_dropi_token_store' && function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->info("dropi-auto-tag: add meta post_id={$post_id} meta_key={$meta_key} meta_value={$meta_value} post_type=" . get_post_type($post_id), array('source' => 'dropi-auto-tags'));
    }
}

function dropi_auto_tag_debug_update($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key === '_dropi_token_store' && function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->info("dropi-auto-tag: update meta post_id={$post_id} meta_key={$meta_key} meta_value={$meta_value} post_type=" . get_post_type($post_id), array('source' => 'dropi-auto-tags'));
    }
}
