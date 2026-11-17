<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

// Total orders
$orderResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM orders"
);
$totalOrders = mysqli_fetch_assoc($orderResult)['total'];

// Total revenue, excluding cancelled orders
$revenueResult = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(total_amount), 0) AS revenue
     FROM orders
     WHERE status != 'Cancelled'"
);
$totalRevenue = mysqli_fetch_assoc($revenueResult)['revenue'];

// Pending orders
$pendingResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE status = 'Pending'"
);
$pendingOrders = mysqli_fetch_assoc($pendingResult)['total'];

// Available products
$productResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM products
     WHERE status = 'Available'"
);
$totalProducts = mysqli_fetch_assoc($productResult)['total'];

// Recent orders
$recentOrders = mysqli_query(
    $conn,
    "SELECT id, customer_name, total_amount, status, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 8"
);

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['logout_token'])) {
    $_SESSION['logout_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | CafeNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page">

    <header class="admin-header">

        <a href="dashboard.php" class="admin-brand">
            CafeNest <span>Admin</span>
        </a>

        <div class="admin-header-right">

            <span>
                Welcome, <?php echo clean($_SESSION['admin_username']); ?>
            </span>

            <form action="../logout.php" method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo clean($_SESSION['logout_token']); ?>"
                >

                <button type="submit" class="admin-logout">
                    Logout
                </button>
            </form>

        </div>

    </header>

    <main class="admin-main">

        <div class="admin-heading">
            <div>
                <h1>Dashboard</h1>
                <p>Here's what's happening at your cafe.</p>
            </div>
        </div>

        <section class="admin-stats">

            <div class="stat-card">
                <p>Total Orders</p>
                <h2><?php echo clean($totalOrders); ?></h2>
            </div>

            <div class="stat-card">
                <p>Total Revenue</p>
                <h2>₹<?php echo number_format((float)$totalRevenue, 2); ?></h2>
            </div>

            <div class="stat-card">
                <p>Pending Orders</p>
                <h2><?php echo clean($pendingOrders); ?></h2>
            </div>

            <div class="stat-card">
                <p>Available Products</p>
                <h2><?php echo clean($totalProducts); ?></h2>
            </div>

        </section>

        <section class="admin-actions">

            <a href="products.php" class="admin-action-card">
                <span>☕</span>
                <h3>Manage Products</h3>
                <p>Add, edit and manage cafe products.</p>
            </a>

            <a href="orders.php" class="admin-action-card">
                <span>📦</span>
                <h3>Manage Orders</h3>
                <p>View customer orders and update their status.</p>
            </a>
            <a href="reports.php" class="admin-card">
    <h3>Sales Reports</h3>
    <p>View revenue, daily sales and best-selling products.</p>
</a>

        </section>

        <section class="recent-orders">

            <h2>Recent Orders</h2>

            <div class="admin-table-wrapper">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($recentOrders) > 0): ?>

                            <?php while ($order = mysqli_fetch_assoc($recentOrders)): ?>

                                <tr>
                                    <td>#<?php echo clean($order['id']); ?></td>

                                    <td>
                                        <?php echo clean($order['customer_name']); ?>
                                    </td>

                                    <td>
                                        ₹<?php echo number_format(
                                            (float)$order['total_amount'],
                                            2
                                        ); ?>
                                    </td>

                                    <td>
                                        <span class="order-status">
                                            <?php echo clean($order['status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo clean($order['created_at']); ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">
                                    No orders have been placed yet.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <a href="../index.php" class="admin-home-link">
            ← View Customer Website
        </a>

    </main>

</body>
</html>