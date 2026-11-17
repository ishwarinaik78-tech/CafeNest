<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? "");
$statusFilter = $_GET['status'] ?? "";

$allowedStatuses = [
    "Pending",
    "Preparing",
    "Ready",
    "Completed",
    "Cancelled"
];

$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (
        customer_name LIKE ?
        OR phone LIKE ?
        OR CAST(id AS CHAR) LIKE ?
    )";

    $searchTerm = "%" . $search . "%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($types !== "") {
    $bindParams = [$types];

    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindParams
    );

    unset($value);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);

if (empty($_SESSION['logout_token'])) {
    $_SESSION['logout_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | CafeNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page">

<header class="admin-header">

    <a href="dashboard.php" class="admin-brand">
        CafeNest <span>Admin</span>
    </a>

    <div class="admin-header-right">

        <span>Welcome, <?php echo clean($_SESSION['admin_username']); ?></span>

        <form action="../logout.php" method="POST">
            <input type="hidden" name="csrf_token"
                value="<?php echo clean($_SESSION['logout_token']); ?>">

            <button type="submit" class="admin-logout">
                Logout
            </button>
        </form>

    </div>

</header>

<main class="admin-main">

    <div class="product-page-heading">

        <div>
            <h1>Manage Orders</h1>
            <p>View customer orders and manage their progress.</p>
        </div>

        <a href="dashboard.php" class="admin-primary-btn">
            Back to Dashboard
        </a>

    </div>

    <section class="recent-orders">

        <form method="GET" class="order-filter-form">

            <input
                type="text"
                name="search"
                placeholder="Search by order ID, customer or phone"
                value="<?php echo clean($search); ?>"
            >

            <select name="status">

                <option value="">All Orders</option>

                <?php foreach ($allowedStatuses as $status): ?>

                    <option value="<?php echo clean($status); ?>"
                        <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                        <?php echo clean($status); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="admin-primary-btn">
                Search
            </button>

            <a href="orders.php" class="admin-reset-btn">
                Reset
            </a>

        </form>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($orders) > 0): ?>

                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>

                        <tr>

                            <td>#<?php echo (int)$order['id']; ?></td>

                            <td><?php echo clean($order['customer_name']); ?></td>

                            <td><?php echo clean($order['phone']); ?></td>

                            <td>
                                ₹<?php echo number_format(
                                    (float)$order['total_amount'],
                                    2
                                ); ?>
                            </td>

                            <td>
                                <span class="status-<?php echo strtolower(clean($order['status'])); ?>">
                                    <?php echo clean($order['status']); ?>
                                </span>
                            </td>

                            <td><?php echo clean($order['created_at']); ?></td>

                            <td>
                                <a
                                    href="order_details.php?id=<?php echo (int)$order['id']; ?>"
                                    class="admin-edit-btn"
                                >
                                    View Details
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">No orders found.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>