<?php
/**
 * Plugin Name: Dropi Customer
 * Description: Extiende wc-dropi-integration con soporte multi-tienda, URLs dinámicas por token,
 *              galería de imágenes, tags automáticos por país, y fixes de UI.
 * Version:     1.0
 * Author:      Oxeky
 * Requires Plugins: woocommerce, wc-dropi-integration
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DROPI_CUSTOMER_VERSION', '1.0');
define('DROPI_CUSTOMER_FILE', __FILE__);

class Dropi_Customer {

    private static $instance;
    
    // Contexto para resolución de URLs de imágenes
    private static $current_store_name = '';
    private static $current_store_token = '';

    static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('plugins_loaded', array($this, 'check_dependencies'), 0);
    }

    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        if (!class_exists('JPIODFW_ProductsModel')) {
            return;
        }
        $this->setup_hooks();
    }

    private function setup_hooks() {
        // ========== HTTP API: URL dinámica + SSL fallback ==========
        add_filter('pre_http_request', array($this, 'intercept_http'), 10, 3);

        // ========== Contexto para imágenes (capturar tienda actual) ==========
        add_action('add_post_meta', array($this, 'capture_store_context'), 0, 3);
        add_action('update_post_meta', array($this, 'capture_store_context'), 0, 4);

        // ========== Tags automáticos por país ==========
        add_action('add_post_meta', array($this, 'auto_tag'), 10, 3);
        add_action('update_post_meta', array($this, 'auto_tag'), 10, 4);

        // ========== Galería de imágenes (fijar _product_image_gallery) ==========
        add_action('add_post_meta', array($this, 'fix_gallery'), 15, 3);
        add_action('update_post_meta', array($this, 'fix_gallery'), 15, 4);

        // ========== Categorías múltiples ==========
        add_action('add_post_meta', array($this, 'fix_categories'), 15, 3);
        add_action('update_post_meta', array($this, 'fix_categories'), 15, 4);

        // ========== Admin UI fixes ==========
        add_action('admin_footer', array($this, 'admin_footer_scripts'));

        // ========== Log debug ==========
        add_action('add_post_meta', array($this, 'debug_log'), 99, 3);
        add_action('update_post_meta', array($this, 'debug_log'), 99, 4);
    }

    // ========================================================================
    //  HTTP INTERCEPTION: API URL dinámica + SSL fallback
    // ========================================================================

    private static $http_recursing = false;

    /**
     * Mapa de store_name → API URL
     */
    private static function get_api_url_map() {
        return array(
            'pe' => 'https://api.dropi.pe/integrations/',
            'co' => 'https://api.dropi.co/integrations/',
            'ec' => 'https://api.dropi.ec/integrations/',
            'ar' => 'https://api.dropi.ar/integrations/',
            'cl' => 'https://api.dropi.cl/integrations/',
            'py' => 'https://api.dropi.com.py/integrations/',
            'pa' => 'https://api.dropi.pa/integrations/',
            'mx' => 'https://api.dropi.mx/integrations/',
            've' => 'https://api.dropi.com.ve/integrations/',
            'es' => 'https://api.dropi.com.es/integrations/',
        );
    }

    /**
     * Mapa de store_name → IMG URL (para imágenes no S3)
     */
    private static function get_img_url_map() {
        return array(
            'pe' => 'https://api.dropi.pe/',
            'co' => 'https://api.dropi.co/',
            'ec' => 'https://api.dropi.ec/',
            'ar' => 'https://api.dropi.ar/',
            'cl' => 'https://api.dropi.cl/',
            'py' => 'https://api.dropi.com.py/',
            'pa' => 'https://api.dropi.pa/',
            'mx' => 'https://api.dropi.mx/',
            've' => 'https://api.dropi.com.ve/',
            'es' => 'https://api.dropi.com.es/',
        );
    }

    /**
     * Mapa de store_name → CDN URL (para urlS3)
     */
    private static function get_cdn_url_map() {
        return array(
            'pe' => 'https://d39ru7awumhhs2.cloudfront.net/',
            've' => 'https://d3vg2225d5fxrl.cloudfront.net/',
        );
    }

    /**
     * Obtiene el country code desde store_name (ej. "oxeky-ve" → "ve")
     */
    private static function get_country_code($store_name) {
        $parts = explode('-', $store_name);
        return isset($parts[1]) ? strtolower($parts[1]) : '';
    }

    /**
     * Busca un token en la DB y devuelve su store_name
     */
    private static function get_store_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'dropi_tokens';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT store FROM {$table} WHERE token = %s LIMIT 1",
            $token
        ));
        return $row ? $row->store : '';
    }

    /**
     * Intercepta llamadas HTTP a Dropi para:
     *   - Reescribir la URL de la API según el token (multi-tienda)
     *   - Reescribir URLs de imágenes según contexto de tienda
     *   - Fallback SSL (sslverify=true → false en error)
     */
    public function intercept_http($pre, $args, $url) {
        if (self::$http_recursing) {
            return $pre;
        }

        // Solo procesar URLs de Dropi
        if (strpos($url, 'api.dropi') === false && strpos($url, 'cloudfront.net') === false) {
            return $pre;
        }

        self::$http_recursing = true;

        $new_url    = $url;
        $store_name = '';
        $ssl_try    = true;

        // --- Caso 1: Llamada API con token en headers ---
        if (isset($args['headers']['dropi-integration-key'])) {
            $token = $args['headers']['dropi-integration-key'];
            $store_name = self::get_store_by_token($token);
            $cc = self::get_country_code($store_name);

            $api_map = self::get_api_url_map();
            $target_base = $cc && isset($api_map[$cc]) ? $api_map[$cc] : null;

            if ($target_base) {
                // Detectar qué base URL tiene actualmente la petición
                $current_base = null;
                foreach ($api_map as $_cc => $_base) {
                    $_base_clean = rtrim($_base, '/');
                    if (strpos($url, $_base_clean) === 0) {
                        $current_base = $_base;
                        break;
                    }
                }
                // Solo reescribir si la base actual difiere de la target
                if ($current_base && $current_base !== $target_base) {
                    $new_url = str_replace(rtrim($current_base, '/'), rtrim($target_base, '/'), $url);
                }
            }
        }

        // --- Caso 2: Descarga de imagen (usa contexto capturado) ---
        if (empty($store_name) && !empty(self::$current_store_name)) {
            $store_name = self::$current_store_name;
            $cc = self::get_country_code($store_name);

            // IMG URL (api.dropi.*.dominio/ruta)
            if (strpos($url, 'api.dropi') !== false) {
                $img_map = self::get_img_url_map();
                if ($cc && isset($img_map[$cc])) {
                    foreach ($img_map as $other_cc => $other_url) {
                        $other_base = rtrim($other_url, '/');
                        if (strpos($url, $other_base) === 0) {
                            $new_url = str_replace($other_base, rtrim($img_map[$cc], '/'), $url);
                            break;
                        }
                    }
                }
            }

            // CDN URL (cloudfront.net)
            if (strpos($url, 'cloudfront.net') !== false) {
                $cdn_map = self::get_cdn_url_map();
                if ($cc && isset($cdn_map[$cc])) {
                    foreach ($cdn_map as $other_cc => $other_cdn) {
                        $other_cdn_clean = rtrim($other_cdn, '/');
                        if (strpos($url, $other_cdn_clean) === 0) {
                            $new_url = str_replace($other_cdn_clean, rtrim($cdn_map[$cc], '/'), $url);
                            break;
                        }
                    }
                }
            }
        }

        // --- SSL fallback: intentar con sslverify=true, luego false ---
        $args['sslverify'] = true;
        $response = wp_remote_request($new_url, $args);

        if (is_wp_error($response)) {
            $args['sslverify'] = false;
            $response = wp_remote_request($new_url, $args);
        }

        self::$http_recursing = false;
        return $response;
    }

    // ========================================================================
    //  CONTEXTO: capturar store actual durante la importación
    // ========================================================================

    public function capture_store_context() {
        $args = func_get_args();
        $count = func_num_args();

        if ($count === 4) {
            $post_id = $args[1];
            $meta_key = $args[2];
            $meta_value = $args[3];
        } else {
            $post_id = $args[0];
            $meta_key = $args[1];
            $meta_value = $args[2];
        }

        if ($meta_key === '_dropi_token_store') {
            self::$current_store_name = $meta_value;
        }
        if ($meta_key === '_dropi_token') {
            self::$current_store_token = $meta_value;
        }
    }

    // ========================================================================
    //  AUTO-TAGS: asignar tag de país al producto importado
    // ========================================================================

    public function auto_tag() {
        $args = func_get_args();
        $count = func_num_args();

        if ($count === 4) {
            $post_id = $args[1];
            $meta_key = $args[2];
            $meta_value = $args[3];
        } else {
            $post_id = $args[0];
            $meta_key = $args[1];
            $meta_value = $args[2];
        }

        if ($meta_key !== '_dropi_token_store') {
            return;
        }
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $tags_map = array(
            'oxeky-pe' => 'pe',
            'oxeky-ar' => 'ar',
            'oxeky-bo' => 'bo',
            'oxeky-co' => 'co',
            'oxeky-ve' => 've',
            'oxeky-ec' => 'ec',
            'oxeky-cl' => 'cl',
            'oxeky-cr' => 'cr',
            'oxeky-es' => 'es',
        );

        if (isset($tags_map[$meta_value])) {
            wp_set_object_terms($post_id, $tags_map[$meta_value], 'product_tag', true);
        }
    }

    // ========================================================================
    //  GALERÍA: fijar _product_image_gallery después de importar imágenes
    // ========================================================================

    public function fix_gallery() {
        $args = func_get_args();
        $count = func_num_args();

        if ($count === 4) {
            $post_id = $args[1];
            $meta_key = $args[2];
            $meta_value = $args[3];
        } else {
            $post_id = $args[0];
            $meta_key = $args[1];
            $meta_value = $args[2];
        }

        // Ejecutar después de que _dropi_token_store se haya guardado
        if ($meta_key !== '_dropi_token_store') {
            return;
        }
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        // Si ya tiene _product_image_gallery, no hacemos nada
        $existing = get_post_meta($post_id, '_product_image_gallery', true);
        if (!empty($existing)) {
            return;
        }

        // Buscar attachments hijos del producto
        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'post_status'    => 'inherit',
        ));

        if (count($attachments) <= 1) {
            return;
        }

        $ids = array();
        foreach ($attachments as $att) {
            $ids[] = $att->ID;
        }

        // El primero ya debería ser featured (set_post_thumbnail en setPostImages)
        set_post_thumbnail($post_id, $ids[0]);

        // El resto van a la galería
        $gallery_ids = array_slice($ids, 1);
        update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info("dropi-customer: fix_gallery post_id={$post_id} gallery=" . implode(',', $gallery_ids), array('source' => 'dropi-customer'));
        }
    }

    // ========================================================================
    //  CATEGORÍAS: asignar TODAS las categorías del producto Dropi
    // ========================================================================

    public function fix_categories() {
        $args = func_get_args();
        $count = func_num_args();

        if ($count === 4) {
            $post_id = $args[1];
            $meta_key = $args[2];
            $meta_value = $args[3];
        } else {
            $post_id = $args[0];
            $meta_key = $args[1];
            $meta_value = $args[2];
        }

        if ($meta_key !== '_dropi_product') {
            return;
        }
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        // Obtener los datos del producto Dropi (ya serializados)
        $dropi_data = get_post_meta($post_id, '_dropi_product', true);
        if (empty($dropi_data)) {
            return;
        }

        $product = maybe_unserialize($dropi_data);
        if (!is_object($product) || !isset($product->categories)) {
            return;
        }

        $term_ids = array();
        foreach ($product->categories as $cat) {
            $cat_name = isset($cat->name) ? trim($cat->name) : '';
            if (empty($cat_name)) {
                continue;
            }

            $category = get_term_by('name', $cat_name, 'product_cat');
            if ($category) {
                $term_ids[] = $category->term_id;
            } else {
                $term = wp_insert_term($cat_name, 'product_cat', array(
                    'description' => $cat_name,
                    'parent' => 0,
                ));
                if (!is_wp_error($term)) {
                    $term_ids[] = $term['term_id'];
                }
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, 'product_cat');
        }
    }

    // ========================================================================
    //  ADMIN UI: fixes con JavaScript
    // ========================================================================

    public function admin_footer_scripts() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'dropi-products') {
            return;
        }
        ?>
<script>
jQuery(document).ready(function($) {
    // 1. Fijar store_filter dropdown según URL
    var urlParams = new URLSearchParams(window.location.search);
    var storeFilter = urlParams.get("store_filter");
    if (storeFilter) {
        $("#store-filter").val(storeFilter);
    }

    // 2. Agregar data-store y &store= a los botones de importación
    $(".btn-dropi-import").each(function() {
        var $btn = $(this);
        var itemData = $btn.data("item");
        if (itemData && itemData.store && itemData.store.id) {
            var storeId = itemData.store.id;
            if (!$btn.attr("data-store")) {
                $btn.attr("data-store", storeId);
            }
            var href = $btn.attr("href") || "";
            if (href.indexOf("&store=") === -1 && href.indexOf("?store=") === -1) {
                var sep = href.indexOf("?") === -1 ? "?" : "&";
                $btn.attr("href", href + sep + "store=" + storeId);
            }
        }
    });
});
</script>
<?php
    }

    // ========================================================================
    //  DEBUG LOG
    // ========================================================================

    public function debug_log() {
        $args = func_get_args();
        $count = func_num_args();

        if ($count === 4) {
            $post_id = $args[1];
            $meta_key = $args[2];
            $meta_value = $args[3];
        } else {
            $post_id = $args[0];
            $meta_key = $args[1];
            $meta_value = $args[2];
        }

        $debug_keys = array('_dropi_token_store', '_dropi_token', '_dropi_product');
        if (!in_array($meta_key, $debug_keys)) {
            return;
        }
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $val_preview = is_string($meta_value) ? substr($meta_value, 0, 80) : gettype($meta_value);
        $logger->info("dropi-customer: meta post_id={$post_id} key={$meta_key} val={$val_preview}", array('source' => 'dropi-customer'));
    }
}

// Inicializar
Dropi_Customer::get_instance()->init();
