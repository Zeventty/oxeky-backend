<?php
/**
 * Generate fake data for WooCommerce: reviews, orders, customers.
 * Run once via: php scripts/generate-fake-data.php
 */

// Disable output buffering
if (ob_get_level()) ob_end_flush();

// Load WordPress
$wp_load = dirname(__DIR__) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("wp-load.php not found\n");
}
require_once $wp_load;

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    die("WooCommerce is not active\n");
}

echo "=== Generating Fake Data ===\n\n";

// --- 1. Get all published products ---
$products = wc_get_products(array('limit' => -1, 'status' => 'publish'));
echo "Found " . count($products) . " products.\n";

if (empty($products)) {
    die("No products found. Import some Dropi products first.\n");
}

// --- 2. Create fake reviews ---
$reviewers = array(
    array('name' => 'Carlos Mendoza', 'email' => 'carlos@example.com'),
    array('name' => 'Maria Lopez', 'email' => 'maria@example.com'),
    array('name' => 'Jose Rodriguez', 'email' => 'jose@example.com'),
    array('name' => 'Ana Martinez', 'email' => 'ana@example.com'),
    array('name' => 'Luis Garcia', 'email' => 'luis@example.com'),
    array('name' => 'Laura Fernandez', 'email' => 'laura@example.com'),
    array('name' => 'Pedro Sanchez', 'email' => 'pedro@example.com'),
    array('name' => 'Sofia Ramirez', 'email' => 'sofia@example.com'),
    array('name' => 'Diego Torres', 'email' => 'diego@example.com'),
    array('name' => 'Valentina Diaz', 'email' => 'valentina@example.com'),
    array('name' => 'Andres Vargas', 'email' => 'andres@example.com'),
    array('name' => 'Camila Rojas', 'email' => 'camila@example.com'),
);

$positive_reviews = array(
    'Excelente producto, superó mis expectativas.',
    'Muy buena calidad, lo recomiendo completamente.',
    'Llegó rápido y en perfectas condiciones. Muy satisfecho.',
    'Cumple con lo prometido. Buena relación calidad-precio.',
    'Me encantó, volvería a comprar sin dudas.',
    'Producto de alta calidad, tal como se describe.',
    'Muy contento con mi compra. Envío rápido.',
    'Perfecto para lo que necesitaba. Muy recomendable.',
    'Buena compra, funciona exactamente como esperaba.',
    'Calidad superior, definitivamente lo recomiendo.',
);

$neutral_reviews = array(
    'Está bien para el precio, pero esperaba un poco más.',
    'Producto decente, cumple su función sin más.',
    'Podría mejorar en algunos aspectos, pero en general bien.',
    'Aceptable, pero hay opciones mejores en el mercado.',
    'No está mal, pero tampoco es excelente.',
);

$negative_reviews = array(
    'No cumplió con mis expectativas, esperaba mejor calidad.',
    'Llegó con algunos detalles, aunque funciona.',
    'Podría ser mejor, la calidad no es la mejor.',
    'No lo recomendaría, hay productos similares mejores.',
);

echo "\n--- Creating reviews ---\n";
$review_count = 0;
foreach ($products as $product) {
    $num_reviews = rand(2, 6);
    $used_reviewers = array();

    for ($i = 0; $i < $num_reviews; $i++) {
        // Pick a random reviewer (avoid duplicates on same product)
        $reviewer_idx = array_rand($reviewers);
        while (in_array($reviewer_idx, $used_reviewers)) {
            $reviewer_idx = array_rand($reviewers);
        }
        $used_reviewers[] = $reviewer_idx;
        $reviewer = $reviewers[$reviewer_idx];

        // Random rating weighted towards positive
        $rand = rand(1, 10);
        if ($rand <= 6) {
            $rating = 5;
        } elseif ($rand <= 8) {
            $rating = 4;
        } elseif ($rand <= 9) {
            $rating = 3;
        } else {
            $rating = rand(1, 2);
        }

        // Pick review text based on rating
        if ($rating >= 4) {
            $comment = $positive_reviews[array_rand($positive_reviews)];
        } elseif ($rating == 3) {
            $comment = $neutral_reviews[array_rand($neutral_reviews)];
        } else {
            $comment = $negative_reviews[array_rand($negative_reviews)];
        }

        // Random date within the last 60 days
        $days_ago = rand(1, 60);
        $comment_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));

        $commentdata = array(
            'comment_post_ID' => $product->get_id(),
            'comment_author' => $reviewer['name'],
            'comment_author_email' => $reviewer['email'],
            'comment_content' => $comment,
            'comment_type' => 'review',
            'comment_approved' => 1,
            'comment_date' => $comment_date,
        );

        $comment_id = wp_insert_comment($commentdata);
        if ($comment_id) {
            update_comment_meta($comment_id, 'rating', $rating);
            update_comment_meta($comment_id, 'verified', rand(0, 1));
            $review_count++;
        }
    }
}
echo "Created {$review_count} reviews.\n";

// --- 3. Create fake orders ---
echo "\n--- Creating orders ---\n";

// Create a few fake customers
$customers = array(
    array('name' => 'Juan Perez', 'email' => 'juan.perez@email.com'),
    array('name' => 'Ana Gomez', 'email' => 'ana.gomez@email.com'),
    array('name' => 'Roberto Diaz', 'email' => 'roberto.diaz@email.com'),
    array('name' => 'Carmen Ruiz', 'email' => 'carmen.ruiz@email.com'),
    array('name' => 'Miguel Torres', 'email' => 'miguel.torres@email.com'),
);

// Shipping/billing addresses
$addresses = array(
    array(
        'first_name' => 'Juan', 'last_name' => 'Perez',
        'address_1' => 'Av. Principal 123', 'city' => 'Lima',
        'state' => 'Lima', 'postcode' => '15001', 'country' => 'PE',
    ),
    array(
        'first_name' => 'Ana', 'last_name' => 'Gomez',
        'address_1' => 'Calle Real 456', 'city' => 'Bogotá',
        'state' => 'Cundinamarca', 'postcode' => '11001', 'country' => 'CO',
    ),
    array(
        'first_name' => 'Roberto', 'last_name' => 'Diaz',
        'address_1' => 'Av. Siempre Viva 789', 'city' => 'Caracas',
        'state' => 'Distrito Capital', 'postcode' => '1010', 'country' => 'VE',
    ),
    array(
        'first_name' => 'Carmen', 'last_name' => 'Ruiz',
        'address_1' => 'Calle Mayor 321', 'city' => 'Madrid',
        'state' => 'Madrid', 'postcode' => '28001', 'country' => 'ES',
    ),
    array(
        'first_name' => 'Miguel', 'last_name' => 'Torres',
        'address_1' => 'Av. del Parque 654', 'city' => 'Santiago',
        'state' => 'Región Metropolitana', 'postcode' => '8320000', 'country' => 'CL',
    ),
);

$order_statuses = array('completed', 'completed', 'completed', 'processing', 'on-hold');
$payment_methods = array('bacs' => 'Transferencia bancaria', 'cod' => 'Pago contra entrega', 'paypal' => 'PayPal');

$order_count = 0;
$num_orders = min(25, count($products) * 3);

for ($i = 0; $i < $num_orders; $i++) {
    $addr = $addresses[array_rand($addresses)];
    $customer = $customers[array_rand($customers)];
    $status = $order_statuses[array_rand($order_statuses)];
    $payment = $payment_methods[array_rand($payment_methods)];

    // Pick 1-4 random products
    $num_items = rand(1, 4);
    $selected_products = array_rand(array_flip(range(0, count($products) - 1)), min($num_items, count($products)));
    if (!is_array($selected_products)) $selected_products = array($selected_products);

    $order = wc_create_order();

    $order->set_customer_id(0);
    $order->set_address($addr, 'billing');
    $order->set_address($addr, 'shipping');

    foreach ($selected_products as $idx) {
        $product = $products[$idx];
        $qty = rand(1, 3);
        $order->add_product($product, $qty);
    }

    // Random date within last 90 days
    $days_ago = rand(1, 90);
    $order_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
    $order->set_date_created($order_date);

    $order->set_payment_method(key($payment_methods));
    $order->set_payment_method_title(current($payment_methods));
    $order->set_status($status);

    // Calculate totals
    $order->calculate_totals();

    $order_id = $order->save();
    if ($order_id) {
        $order_count++;
    }
}
echo "Created {$order_count} orders.\n";

echo "\n=== Done! ===\n";
echo "Reviews: {$review_count}\n";
echo "Orders: {$order_count}\n";
