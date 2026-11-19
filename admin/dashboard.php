<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

// Total orders
$orderResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders");
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

    <!-- DASHBOARD HEADING -->
    <div class="admin-heading">
        <div>
            <h1>Dashboard</h1>
            <p>Here's what's happening at your cafe.</p>
        </div>
    </div>

    <!-- DASHBOARD STATISTICS -->
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

    <!-- MAIN MANAGEMENT -->
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

        <a href="reports.php" class="admin-action-card">
            <span>📊</span>
            <h3>Sales Reports</h3>
            <p>View revenue, daily sales and best-selling products.</p>
        </a>

    </section>

    <!-- MANAGEMENT & TOOLS -->
    <section class="recent-orders" style="margin-top: 35px;">

        <div class="admin-heading">
            <div>
                <h2>Management & Tools</h2>
                <p>Access all CafeNest management modules from here.</p>
            </div>
        </div>

        <section class="admin-actions">

            <!-- INVENTORY -->
            <a href="inventory_center.php" class="admin-action-card">
                <span>📦</span>
                <h3>Inventory Center</h3>
                <p>Central access to stock management and inventory tools.</p>
            </a>

            <!-- INGREDIENTS -->
            <a href="ingredients.php" class="admin-action-card">
                <span>🥛</span>
                <h3>Ingredients</h3>
                <p>Manage ingredients and available stock.</p>
            </a>

            <!-- SUPPLIERS -->
            <a href="suppliers.php" class="admin-action-card">
                <span>🚚</span>
                <h3>Suppliers</h3>
                <p>Manage suppliers and supplier information.</p>
            </a>

            <!-- RECIPES -->
            <a href="recipes.php" class="admin-action-card">
                <span>📝</span>
                <h3>Recipes</h3>
                <p>Manage recipes and ingredient requirements.</p>
            </a>

            <!-- PURCHASE ORDERS -->
            <a href="purchase_orders.php" class="admin-action-card">
                <span>🛒</span>
                <h3>Purchase Orders</h3>
                <p>Create, view and manage stock purchase orders.</p>
            </a>

            <!-- INVENTORY REPORTS -->
            <a href="inventory_reports.php" class="admin-action-card">
                <span>📈</span>
                <h3>Inventory Reports</h3>
                <p>View stock reports and inventory summaries.</p>
            </a>

            <!-- STOCK RECONCILIATION -->
            <a href="stock_reconciliation.php" class="admin-action-card">
                <span>⚖️</span>
                <h3>Stock Reconciliation</h3>
                <p>Compare recorded stock with actual stock.</p>
            </a>

            <!-- RESTOCK PLANNER -->
            <a href="supplier_restock_planner.php" class="admin-action-card">
                <span>📋</span>
                <h3>Restock Planner</h3>
                <p>Plan upcoming purchases and supplier restocking.</p>
            </a>

            <!-- SUPPLIER PERFORMANCE -->
            <a href="supplier_performance.php" class="admin-action-card">
                <span>🏆</span>
                <h3>Supplier Performance</h3>
                <p>Review supplier activity and purchase performance.</p>
            </a>

            <!-- MONTHLY ANALYTICS -->
            <a href="inventory_monthly_analytics.php" class="admin-action-card">
                <span>📅</span>
                <h3>Monthly Analytics</h3>
                <p>Review monthly inventory trends and analytics.</p>
            </a>

            <!-- RESTOCK RECOMMENDATIONS -->
            <a href="inventory_restock_recommendations.php" class="admin-action-card">
                <span>💡</span>
                <h3>Restock Recommendations</h3>
                <p>Review inventory restocking recommendations.</p>
            </a>

            <!-- STOCK MOVEMENT -->
            <a href="inventory_history.php" class="admin-action-card">
                <span>🔄</span>
                <h3>Stock Movement History</h3>
                <p>Review stock movements and inventory history.</p>
            </a>

            <!-- WASTAGE -->
            <a href="wastage.php" class="admin-action-card">
                <span>🗑️</span>
                <h3>Wastage Management</h3>
                <p>Record and review ingredient wastage.</p>
            </a>

            <!-- LOW STOCK -->
            <a href="low_stock.php" class="admin-action-card">
                <span>⚠️</span>
                <h3>Low Stock Alerts</h3>
                <p>Check ingredients that need replenishment.</p>
            </a>

            <!-- ACTIVITY LOG -->
            <a href="inventory_activity_log.php" class="admin-action-card">
                <span>🕒</span>
                <h3>Activity Log</h3>
                <p>Review recorded inventory activities.</p>
            </a>

            <!-- INTEGRITY AUDIT -->
            <a href="inventory_integrity_audit.php" class="admin-action-card">
                <span>🔍</span>
                <h3>Inventory Audit</h3>
                <p>Check inventory records for inconsistencies.</p>
            </a>

        </section>

    </section>

    <!-- RECENT ORDERS -->
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

    <!-- CUSTOMER WEBSITE -->
    <a href="../index.php" class="admin-home-link">
        ← View Customer Website
    </a>

</main>

</body>
</html>