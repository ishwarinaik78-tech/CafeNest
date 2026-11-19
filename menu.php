
<?php
session_start();

require_once __DIR__ . "/config/db.php";

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// CSRF token
if (empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// Add product to cart
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_to_cart'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['cart_csrf'], $_POST['csrf_token'])
    ) {
        $message = "Invalid request. Please try again.";
        $messageType = "error";
    } else {

        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if (!$productId || $productId <= 0) {
            $message = "Invalid product.";
            $messageType = "error";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id FROM products WHERE id = ? AND status = 'Available'"
            );

            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {

                $currentQuantity = $_SESSION['cart'][$productId] ?? 0;

                if ($currentQuantity >= 20) {
                    $message = "Maximum quantity reached.";
                    $messageType = "error";
                } else {
                    $_SESSION['cart'][$productId] = $currentQuantity + 1;
                    $message = "Product added to your cart!";
                    $messageType = "success";
                }

            } else {
                $message = "This product is currently unavailable.";
                $messageType = "error";
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch available products
$sql = "SELECT * FROM products WHERE status = 'Available' ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

// Fetch categories
$categoryQuery = "
    SELECT DISTINCT category
    FROM products
    WHERE status = 'Available'
    ORDER BY category ASC
";

$categoryResult = mysqli_query($conn, $categoryQuery);

// Cart count
$cartCount = array_sum($_SESSION['cart']);

function clean($value) {
    return htmlspecialchars($value ?? "", ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Our Menu | CafeNest</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="menu-page">

<!-- NAVBAR -->
<header class="navbar">

    <a href="index.php" class="logo">
        Cafe<span>Nest</span>
    </a>

    <nav class="nav-links" id="navLinks">
        <a href="index.php">Home</a>
        <a href="menu.php" class="active">Menu</a>
        <a href="index.php#about">About</a>
        <a href="index.php#contact">Contact</a>
        <a href="track_order.php">Track Order</a>
    </nav>

    <div class="nav-actions">

        <a href="cart.php" class="cart-link">
            🛒 Cart
            <span class="cart-count">
                <?php echo $cartCount; ?>
            </span>
        </a>

        <a href="login.php" class="admin-link">Admin</a>

        <button class="menu-toggle" id="menuToggle" type="button">
            ☰
        </button>

    </div>

</header>


<!-- MENU HERO -->
<section class="menu-hero">

    <div class="menu-hero-content">

        <span class="menu-tag">FRESHLY MADE WITH LOVE</span>

        <h1>Explore Our <span>Menu</span></h1>

        <p>
            Discover delicious coffee, refreshing beverages,
            and freshly prepared treats at CafeNest.
        </p>

        <a href="#menuSection" class="menu-hero-btn">
            Explore Menu ↓
        </a>

    </div>

</section>


<!-- MENU SECTION -->
<section class="menu-section" id="menuSection">

    <div class="section-heading">

        <span class="section-subtitle">MADE FRESH FOR YOU</span>

        <h2>Our Delicious Menu</h2>

        <p>
            Find your favourite food and drinks.
        </p>

    </div>


    <!-- MESSAGES -->
    <?php if ($message !== ""): ?>

        <div class="menu-message <?php echo clean($messageType); ?>">
            <?php echo clean($message); ?>
        </div>

    <?php endif; ?>


    <!-- SEARCH, CATEGORY AND SORT -->
    <div class="menu-controls">

        <input
            type="text"
            id="menuSearch"
            placeholder="Search food or drinks..."
        >

        <select id="categoryFilter">

            <option value="all">All Categories</option>

            <?php while ($category = mysqli_fetch_assoc($categoryResult)): ?>

                <option value="<?php echo clean(strtolower($category['category'])); ?>">
                    <?php echo clean($category['category']); ?>
                </option>

            <?php endwhile; ?>

        </select>

        <!-- SORTING OPTIONS -->
        <select id="sortFilter">

            <option value="default">Sort By: Default</option>

            <option value="price-low">
                Price: Low to High
            </option>

            <option value="price-high">
                Price: High to Low
            </option>

            <option value="name-asc">
                Name: A to Z
            </option>

            <option value="name-desc">
                Name: Z to A
            </option>

        </select>

    </div>


    <!-- PRODUCTS -->
    <div class="menu-grid" id="menuGrid">

        <?php if ($result && mysqli_num_rows($result) > 0): ?>

            <?php while ($product = mysqli_fetch_assoc($result)): ?>

                <div
                    class="menu-card"
                    data-name="<?php echo clean(strtolower($product['name'])); ?>"
                    data-category="<?php echo clean(strtolower($product['category'])); ?>"
                    data-price="<?php echo (float)$product['price']; ?>"
                >

                    <div class="menu-image">

                        <?php if (!empty($product['image'])): ?>

                            <img
                                src="<?php echo clean($product['image']); ?>"
                                alt="<?php echo clean($product['name']); ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                            <div class="image-fallback" style="display:none;">
                                ☕
                            </div>

                        <?php else: ?>

                            <div class="image-fallback">
                                ☕
                            </div>

                        <?php endif; ?>

                        <span class="category-badge">
                            <?php echo clean($product['category']); ?>
                        </span>

                    </div>


                    <div class="menu-card-content">

                        <h3>
                            <?php echo clean($product['name']); ?>
                        </h3>

                        <p class="product-description">
                            <?php echo clean($product['description']); ?>
                        </p>


                        <div class="product-bottom">

                            <span class="product-price">
                                ₹<?php echo number_format((float)$product['price'], 2); ?>
                            </span>

                            <form method="POST" action="menu.php">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo clean($_SESSION['cart_csrf']); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?php echo (int)$product['id']; ?>"
                                >

                                <button
                                    type="submit"
                                    name="add_to_cart"
                                    class="add-cart-btn"
                                >
                                    + Add
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-menu">
                <h3>No products available yet!</h3>
                <p>Please check back later.</p>
            </div>

        <?php endif; ?>

    </div>


    <!-- NO MATCHING PRODUCTS -->
    <div class="no-products" id="noProducts" style="display:none;">

        <h3>No matching products found.</h3>

        <p>Try another search or category.</p>

    </div>

</section>


<!-- FOOTER -->
<footer class="footer">

    <div class="footer-content">

        <h2>Cafe<span>Nest</span></h2>

        <p>
            Your cosy corner for coffee, food and happiness.
        </p>

        <p class="copyright">
            &copy; <?php echo date("Y"); ?> CafeNest. All rights reserved.
        </p>

    </div>

</footer>


<script src="assets/js/script.js"></script>

</body>
</html>
