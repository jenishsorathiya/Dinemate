<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($orderId > 0 && in_array($action, ['confirm_order', 'complete_order', 'cancel_order'], true)) {
        try {
            $pdo->beginTransaction();
            if ($action === 'confirm_order') {
                $stmt = $pdo->prepare("UPDATE restaurant_orders SET status = 'confirmed' WHERE order_id = ? AND status = 'pending'");
                $stmt->execute([$orderId]);
                $message = $stmt->rowCount() > 0 ? 'Order confirmed.' : 'Order could not be confirmed.';
            } elseif ($action === 'complete_order') {
                $stmt = $pdo->prepare("UPDATE restaurant_orders SET status = 'completed' WHERE order_id = ? AND status = 'confirmed'");
                $stmt->execute([$orderId]);
                $message = $stmt->rowCount() > 0 ? 'Order marked completed.' : 'Order could not be completed.';
            } else {
                $stmt = $pdo->prepare("UPDATE restaurant_orders SET status = 'cancelled' WHERE order_id = ? AND status IN ('pending', 'confirmed')");
                $stmt->execute([$orderId]);
                $message = $stmt->rowCount() > 0 ? 'Order cancelled.' : 'Order could not be cancelled.';
            }
            $pdo->commit();
            setFlashMessage('success', $message);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlashMessage('error', 'Unable to update order status.');
        }
    }

    header('Location: orders-management.php');
    exit();
}

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

$orderStatus = trim((string) ($_GET['status'] ?? 'all'));
$allowedStatuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($orderStatus, $allowedStatuses, true)) {
    $orderStatus = 'all';
}

$statusFilterSql = '';
$params = [];
if ($orderStatus !== 'all') {
    $statusFilterSql = 'WHERE o.status = ?';
    $params[] = $orderStatus;
}

$orderQuery = "SELECT
    o.order_id,
    o.order_number,
    o.customer_name,
    o.customer_email,
    o.customer_phone,
    o.payment_method,
    o.promo_code,
    o.discount_amount,
    o.subtotal,
    o.tax_amount,
    o.total_amount,
    o.item_count,
    o.status,
    o.created_at,
    o.updated_at,
    o.delivery_address,
    o.order_notes,
    GROUP_CONCAT(CONCAT(i.quantity, ' × ', i.item_name) ORDER BY i.order_item_id SEPARATOR '; ') AS items_summary
FROM restaurant_orders o
LEFT JOIN restaurant_order_items i ON i.order_id = o.order_id
{$statusFilterSql}
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT 250";

$orderStmt = $pdo->prepare($orderQuery);
$orderStmt->execute($params);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$flash = getFlashMessage();
$adminSidebarActive = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <title>Order Management - DineMate</title>
    <style>
        .orders-panel {
            margin: 0 auto;
            max-width: 1180px;
            padding: 30px 24px 60px;
        }

        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 28px;
        }

        .orders-header h1 {
            margin: 0;
            font-size: 2rem;
        }

        .orders-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .orders-toolbar select,
        .orders-toolbar button {
            border-radius: 12px;
            border: 1px solid #d1d5db;
            padding: 10px 14px;
            background: #fff;
            color: #111827;
            font-size: 0.95rem;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.06);
            border-radius: 20px;
            overflow: hidden;
        }

        .orders-table th,
        .orders-table td {
            padding: 18px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .orders-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #0f172a;
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .status-pill {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-pill.pending { background: #fef3c7; color: #92400e; }
        .status-pill.confirmed { background: #d1fae5; color: #166534; }
        .status-pill.completed { background: #e0f2fe; color: #0369a1; }
        .status-pill.cancelled { background: #fee2e2; color: #991b1b; }

        .orders-note {
            color: #475569;
            margin-top: 4px;
            font-size: 0.94rem;
            line-height: 1.6;
        }

        @media (max-width: 1024px) {
            .orders-header { flex-direction: column; align-items: stretch; }
            .orders-table th, .orders-table td { padding: 14px 12px; }
        }

        @media (max-width: 760px) {
            .orders-table th, .orders-table td { display: block; width: 100%; }
            .orders-table tr { margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
            .orders-table td { border-bottom: none; }
            .orders-table th { display: none; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>
    <div class="main-content">
        <div class="page-shell">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="orders-panel">
                <div class="orders-header">
                    <div>
                        <h1>Customer Orders</h1>
                        <p class="orders-note">Review all menu orders and payment details submitted by customers.</p>
                    </div>
                    <form method="GET" class="orders-toolbar">
                        <label>
                            <span class="sr-only">Filter by status</span>
                            <select name="status" onchange="this.form.submit()">
                                <option value="all"<?php echo $orderStatus === 'all' ? ' selected' : ''; ?>>All statuses</option>
                                <option value="pending"<?php echo $orderStatus === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                <option value="confirmed"<?php echo $orderStatus === 'confirmed' ? ' selected' : ''; ?>>Confirmed</option>
                                <option value="completed"<?php echo $orderStatus === 'completed' ? ' selected' : ''; ?>>Completed</option>
                                <option value="cancelled"<?php echo $orderStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
                            </select>
                        </label>
                        <button type="submit">Refresh</button>
                    </form>
                </div>

                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Totals</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Placed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8">No orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                                        <small><?php echo htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'])), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                        <?php if (!empty($order['promo_code'])): ?>
                                            <small>Promo: <?php echo htmlspecialchars($order['promo_code'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format((float) $order['total_amount'], 2); ?></strong><br>
                                        <small>Sub: $<?php echo number_format((float) $order['subtotal'], 2); ?></small><br>
                                        <small>Tax: $<?php echo number_format((float) $order['tax_amount'], 2); ?></small>
                                    </td>
                                    <td><span class="status-pill <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <small><?php echo htmlspecialchars((string) $order['items_summary'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8'); ?><br>
                                        <small><?php echo htmlspecialchars((string) $order['item_count'], ENT_QUOTES, 'UTF-8'); ?> items</small>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" class="inline-form" style="display:inline-block; margin-right:8px;">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                <input type="hidden" name="action" value="confirm_order">
                                                <button type="submit" class="btn-workflow is-confirm">Confirm</button>
                                            </form>
                                            <form method="POST" class="inline-form" style="display:inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <button type="submit" class="btn-workflow is-reject" onclick="return confirm('Cancel this order?');">Cancel</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'confirmed'): ?>
                                            <form method="POST" class="inline-form" style="display:inline-block; margin-right:8px;">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                <input type="hidden" name="action" value="complete_order">
                                                <button type="submit" class="btn-workflow is-complete">Complete</button>
                                            </form>
                                            <form method="POST" class="inline-form" style="display:inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <button type="submit" class="btn-workflow is-reject" onclick="return confirm('Cancel this order?');">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-pill <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
