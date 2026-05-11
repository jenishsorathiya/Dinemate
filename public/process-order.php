<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$rawPayload = trim(file_get_contents('php://input'));
$payload = json_decode($rawPayload, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request payload.']);
    exit();
}

$customerName = sanitize($payload['customerName'] ?? '');
$customerPhone = sanitize($payload['customerPhone'] ?? '');
$customerEmail = sanitize($payload['customerEmail'] ?? '');
$customerAddress = null;
$orderNotes = sanitize($payload['orderNotes'] ?? '');
$paymentMethod = strtolower(trim((string) ($payload['paymentMethod'] ?? '')));
$promoCode = strtoupper(trim((string) ($payload['promoCode'] ?? '')));
$cardName = sanitize($payload['cardName'] ?? '');
$cardNumber = preg_replace('/\D+/', '', (string) ($payload['cardNumber'] ?? ''));
$cardExpiry = sanitize($payload['cardExpiry'] ?? '');
$cardCvv = preg_replace('/\D+/', '', (string) ($payload['cardCvv'] ?? ''));
$items = $payload['items'] ?? [];

$validPaymentMethods = ['cash', 'apple', 'card', 'paypal'];
if (!in_array($paymentMethod, $validPaymentMethods, true)) {
    $paymentMethod = 'paypal';
}

$errors = [];
if ($customerName === '') {
    $errors[] = 'Customer name is required.';
}
if ($customerPhone === '') {
    $errors[] = 'Phone number is required.';
}
if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (!is_array($items) || count($items) === 0) {
    $errors[] = 'Add at least one item to your cart.';
}

if ($paymentMethod === 'card') {
    if ($cardName === '') {
        $errors[] = 'Cardholder name is required for card payments.';
    }
    if (strlen($cardNumber) < 15 || strlen($cardNumber) > 19) {
        $errors[] = 'Card number must be between 15 and 19 digits.';
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $errors[] = 'Expiry date must use MM/YY format.';
    }
    if (strlen($cardCvv) < 3 || strlen($cardCvv) > 4) {
        $errors[] = 'CVV must be 3 or 4 digits.';
    }
}

$validatedItems = [];
$subtotal = 0.0;
$itemCount = 0;
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $itemId = (int) ($item['id'] ?? 0);
    $itemName = sanitize($item['name'] ?? '');
    $quantity = max(1, (int) ($item['quantity'] ?? 0));
    $price = (float) ($item['price'] ?? 0);

    if ($itemId < 1 || $itemName === '' || $quantity < 1 || $price <= 0) {
        continue;
    }

    $lineTotal = round($price * $quantity, 2);
    $subtotal += $lineTotal;
    $itemCount += $quantity;

    $validatedItems[] = [
        'menu_item_id' => $itemId,
        'name' => $itemName,
        'price' => $price,
        'quantity' => $quantity,
        'line_total' => $lineTotal,
    ];
}

if (count($validatedItems) === 0) {
    $errors[] = 'Add at least one valid item to your order.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit();
}

$promoAmounts = [
    'DINEMATE10' => 0.10,
    'DINE20' => 0.20,
    'CHEF15' => 0.15,
];
$discountRate = $promoAmounts[$promoCode] ?? 0.0;
$discountAmount = round($subtotal * $discountRate, 2);
$taxAmount = round(max($subtotal - $discountAmount, 0) * 0.12, 2);
$totalAmount = round(max($subtotal - $discountAmount, 0) + $taxAmount, 2);
$orderNumber = 'ORD' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$orderStatus = 'pending';
$paymentMethodMap = [
    'apple' => 'apple_pay',
    'card' => 'card',
    'paypal' => 'paypal',
];
$paymentMethodValue = $paymentMethodMap[$paymentMethod] ?? 'paypal';
$cardLast4 = $paymentMethod === 'card' && strlen($cardNumber) >= 4 ? substr($cardNumber, -4) : null;
$userId = getCurrentUserId();

try {
    $pdo->beginTransaction();

    $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_orders (
        order_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(32) NOT NULL UNIQUE,
        user_id INT NULL DEFAULT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(30) NOT NULL,
        customer_email VARCHAR(100) NOT NULL,
        delivery_address TEXT NULL DEFAULT NULL,
        order_notes TEXT NULL DEFAULT NULL,
        payment_method ENUM('cash','apple_pay','card','paypal') NOT NULL DEFAULT 'cash',
        card_name VARCHAR(100) NULL DEFAULT NULL,
        card_last4 VARCHAR(4) NULL DEFAULT NULL,
        card_expiry VARCHAR(8) NULL DEFAULT NULL,
        promo_code VARCHAR(50) NULL DEFAULT NULL,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        item_count INT NOT NULL DEFAULT 0,
        status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $paymentColumn = $pdo->query("SHOW COLUMNS FROM restaurant_orders LIKE 'payment_method'")->fetch(PDO::FETCH_ASSOC);
    if ($paymentColumn && strpos($paymentColumn['Type'], "'cash'") === false) {
        $pdo->exec("ALTER TABLE restaurant_orders MODIFY COLUMN payment_method ENUM('cash','apple_pay','card','paypal') NOT NULL DEFAULT 'cash'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_order_items (
        order_item_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        menu_item_id INT NULL DEFAULT NULL,
        item_name VARCHAR(150) NOT NULL,
        item_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantity INT NOT NULL DEFAULT 1,
        line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_items_order_id (order_id),
        CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES restaurant_orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $orderInsert = $pdo->prepare("INSERT INTO restaurant_orders (
        order_number,
        user_id,
        customer_name,
        customer_phone,
        customer_email,
        delivery_address,
        order_notes,
        payment_method,
        card_name,
        card_last4,
        card_expiry,
        promo_code,
        discount_amount,
        subtotal,
        tax_amount,
        total_amount,
        item_count,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $orderInsert->execute([
        $orderNumber,
        ($userId !== null ? (int) $userId : null),
        $customerName,
        $customerPhone,
        $customerEmail,
        $customerAddress,
        $orderNotes !== '' ? $orderNotes : null,
        $paymentMethodValue,
        $paymentMethod === 'card' ? $cardName : null,
        $cardLast4,
        $paymentMethod === 'card' ? $cardExpiry : null,
        $promoCode !== '' ? $promoCode : null,
        $discountAmount,
        $subtotal,
        $taxAmount,
        $totalAmount,
        $itemCount,
        $orderStatus,
    ]);

    $orderId = (int) $pdo->lastInsertId();
    $itemInsert = $pdo->prepare("INSERT INTO restaurant_order_items (
        order_id,
        menu_item_id,
        item_name,
        item_price,
        quantity,
        line_total
    ) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($validatedItems as $item) {
        $itemInsert->execute([
            $orderId,
            (int) $item['menu_item_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['line_total'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'message' => 'Order placed successfully.'
    ]);
    exit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    http_response_code(500);
    error_log('Order processing failed: ' . $exception->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to process your order at this time.']);
    exit();
}
