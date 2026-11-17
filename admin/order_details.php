<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid order ID.");
}

// Fetch order information
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM orders WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    die("Order not found.");
}

// Fetch ordered products
$itemStmt = mysqli_prepare(
    $conn,
    "SELECT oi.quantity, oi.price, p.name
     FROM order_items oi
     INNER JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?"
);

mysqli_stmt_bind_param($itemStmt, "i", $id);
mysqli_stmt_execute($itemStmt);

$items = mysqli_stmt_get_result($itemStmt);

if (empty($_SESSION['order_csrf'])) {
    $_SESSION['order_csrf'] = bin2hex(random_bytes(32));
}

$statuses = [
    "Pending",
    "Preparing",
    "Ready",
    "Completed",
    "Cancelled"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | CafeNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page">

<header class="admin-header">

    <a href="dashboard.php" class="admin-brand">
        CafeNest <span>Admin</span>
    </a>

    <a href="orders.php" class="admin-logout">
        Back to Orders
    </a>

</header>

<main class="admin-main">

    <section class="admin-form-card">

        <h1>Order #<?php echo (int)$order['id']; ?></h1>

        <p>Customer order information and ordered products.</p>

        <div class="order-info">

            <p>
                <strong>Customer:</strong>
                <?php echo clean($order['customer_name']); ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?php echo clean($order['phone']); ?>
            </p>

            <p>
                <strong>Order Date:</strong>
                <?php echo clean($order['created_at']); ?>
            </p>

            <p>
                <strong>Current Status:</strong>
                <?php echo clean($order['status']); ?>
            </p>

        </div>

        <h2 class="order-items-heading">Ordered Items</h2>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $calculatedTotal = 0;
                    ?>

                    <?php while ($item = mysqli_fetch_assoc($items)): ?>

                        <?php
                        $subtotal = (float)$item['price'] * (int)$item['quantity'];
                        $calculatedTotal += $subtotal;
                        ?>

                        <tr>

                            <td><?php echo clean($item['name']); ?></td>

                            <td>
                                ₹<?php echo number_format((float)$item['price'], 2); ?>
                            </td>

                            <td><?php echo (int)$item['quantity']; ?></td>

                            <td>₹<?php echo number_format($subtotal, 2); ?></td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

        <div class="order-total">
            Total Amount:
            <strong>
                ₹<?php echo number_format((float)$order['total_amount'], 2); ?>
            </strong>
        </div>

        <form action="update_order.php" method="POST" class="update-order-form">

            <input type="hidden" name="csrf_token"
                value="<?php echo clean($_SESSION['order_csrf']); ?>">

            <input type="hidden" name="order_id"
                value="<?php echo (int)$order['id']; ?>">

            <label for="status">Update Order Status</label>

            <select name="status" id="status" required>

                <?php foreach ($statuses as $status): ?>

                    <option value="<?php echo clean($status); ?>"
                        <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                        <?php echo clean($status); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="admin-primary-btn form-submit">
                Update Status
            </button>

        </form>

        <a href="orders.php" class="admin-home-link">
            ← Back to All Orders
        </a>

    </section>

</main>

</body>
</html>