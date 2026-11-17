<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$products = mysqli_query(
    $conn,
    "SELECT * FROM products ORDER BY id DESC"
);

if (empty($_SESSION['product_csrf'])) {
    $_SESSION['product_csrf'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | CafeNest</title>
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
                   value="<?php echo clean($_SESSION['logout_token'] ?? ''); ?>">
            <button type="submit" class="admin-logout">Logout</button>
        </form>
    </div>
</header>

<main class="admin-main">

    <div class="product-page-heading">
        <div>
            <h1>Manage Products</h1>
            <p>Add, edit and manage your cafe menu.</p>
        </div>

        <a href="add_product.php" class="admin-primary-btn">
            + Add New Product
        </a>
    </div>

    <section class="recent-orders">

        <div class="admin-table-wrapper">
            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($products && mysqli_num_rows($products) > 0): ?>

                    <?php while ($product = mysqli_fetch_assoc($products)): ?>

                        <tr>
                            <td>
                                <?php if (!empty($product['image'])): ?>
                                    <img
                                        src="<?php echo clean(
                                            strpos($product['image'], 'http://') === 0 ||
                                            strpos($product['image'], 'https://') === 0
                                            ? $product['image']
                                            : '../' . $product['image']
                                        ); ?>"
                                        alt="<?php echo clean($product['name']); ?>"
                                        class="admin-product-image"
                                        onerror="this.style.display='none'"
                                    >
                                <?php else: ?>
                                    No image
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?php echo clean($product['name']); ?></strong>
                                <br>
                                <small><?php echo clean($product['description']); ?></small>
                            </td>

                            <td><?php echo clean($product['category']); ?></td>

                            <td>₹<?php echo number_format((float)$product['price'], 2); ?></td>

                            <td>
                                <span class="order-status">
                                    <?php echo clean($product['status']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="product-action-buttons">

                                    <a
                                        href="edit_product.php?id=<?php echo (int)$product['id']; ?>"
                                        class="admin-edit-btn"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="delete_product.php"
                                        method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this product?');"
                                    >
                                        <input type="hidden" name="id"
                                               value="<?php echo (int)$product['id']; ?>">

                                        <input type="hidden" name="csrf_token"
                                               value="<?php echo clean($_SESSION['product_csrf']); ?>">

                                        <button type="submit" class="admin-delete-btn">
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">No products found. Add your first product!</td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </section>

    <a href="dashboard.php" class="admin-home-link">
        ← Back to Dashboard
    </a>

</main>

</body>
</html>