
<?php
session_start();

require_once "config/db.php";

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Remove product
if (isset($_POST['remove_item'])) {

    $id = filter_var(
        $_POST['remove_item'],
        FILTER_VALIDATE_INT
    );

    if ($id && isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }

    header("Location: cart.php");
    exit;
}

// Update quantities
if (isset($_POST['update_cart'])) {

    if (isset($_POST['quantity'])
        && is_array($_POST['quantity'])) {

        foreach ($_POST['quantity'] as $id => $quantity) {

            $id = filter_var($id, FILTER_VALIDATE_INT);

            $quantity = filter_var(
                $quantity,
                FILTER_VALIDATE_INT
            );

            if (!$id || !isset($_SESSION['cart'][$id])) {
                continue;
            }

            if ($quantity === false || $quantity <= 0) {

                unset($_SESSION['cart'][$id]);

            } else {

                $_SESSION['cart'][$id] = min($quantity, 99);

            }
        }
    }

    header("Location: cart.php");
    exit;
}


// Fetch cart products from database
$cart_items = [];
$total = 0;

foreach ($_SESSION['cart'] as $id => $quantity) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM products
         WHERE id = ? AND status = 'Available'"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($product = mysqli_fetch_assoc($result)) {

        $product['quantity'] = $quantity;

        $product['subtotal'] =
            $product['price'] * $quantity;

        $total += $product['subtotal'];

        $cart_items[] = $product;

    } else {

        unset($_SESSION['cart'][$id]);

    }

    mysqli_stmt_close($stmt);
}

$cart_count = array_sum($_SESSION['cart']);

function clean($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Shopping Cart | CafeNest</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>

<header class="navbar">

    <div class="nav-container">

        <a href="index.php" class="logo">
            ☕ CafeNest.
        </a>

        <button class="menu-toggle" id="menuToggle">
            ☰
        </button>

        <nav id="navLinks">

            <a href="index.php">Home</a>

            <a href="menu.php">Menu</a>

            <a href="index.php#about">About</a>

        </nav>

        <div class="nav-buttons">

            <a href="cart.php" class="cart-btn">
                🛒 Cart (<?php echo $cart_count; ?>)
            </a>

            <a href="login.php" class="login-btn">
                Admin Login
            </a>

        </div>

    </div>

</header>


<section class="cart-section">

    <div class="cart-heading">

        <p class="small-heading">YOUR SELECTION</p>

        <h1>Shopping Cart</h1>

        <p>Review your delicious choices.</p>

    </div>


    <?php if (count($cart_items) > 0): ?>

        <form method="POST" action="cart.php">

            <div class="cart-layout">

                <div class="cart-items">

                    <?php foreach ($cart_items as $item): ?>

                        <div class="cart-item">

                            <img
                                src="<?php echo clean($item['image']); ?>"
                                alt="<?php echo clean($item['name']); ?>"
                                onerror="this.style.display='none'"
                            >

                            <div class="cart-item-details">

                                <span class="product-category">
                                    <?php echo clean($item['category']); ?>
                                </span>

                                <h3>
                                    <?php echo clean($item['name']); ?>
                                </h3>

                                <p>
                                    ₹<?php echo number_format(
                                        (float)$item['price'], 2
                                    ); ?>
                                </p>

                                <div class="quantity-control">

                                    <label>
                                        Quantity:
                                    </label>

                                    <input
                                        type="number"
                                        name="quantity[<?php echo (int)$item['id']; ?>]"
                                        value="<?php echo (int)$item['quantity']; ?>"
                                        min="0"
                                        max="99"
                                    >

                                </div>

                            </div>

                            <div class="cart-item-actions">

                                <strong>
                                    ₹<?php echo number_format(
                                        (float)$item['subtotal'], 2
                                    ); ?>
                                </strong>

                                <button
                                    type="submit"
                                    name="remove_item"
                                    value="<?php echo (int)$item['id']; ?>"
                                    class="remove-btn">

                                    Remove

                                </button>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


                <aside class="cart-summary">

                    <h2>Order Summary</h2>

                    <div class="summary-row">

                        <span>Items</span>

                        <strong>
                            <?php echo $cart_count; ?>
                        </strong>

                    </div>

                    <div class="summary-row">

                        <span>Subtotal</span>

                        <strong>
                            ₹<?php echo number_format($total, 2); ?>
                        </strong>

                    </div>

                    <div class="summary-row total-row">

                        <span>Total</span>

                        <strong>
                            ₹<?php echo number_format($total, 2); ?>
                        </strong>

                    </div>

                    <button
                        type="submit"
                        name="update_cart"
                        class="update-cart-btn">

                        Update Cart

                    </button>
                    <a href="checkout.php" class="checkout-btn">
    Proceed to Checkout →
</a>

                    <a href="menu.php"
                       class="continue-shopping">

                        ← Continue Shopping

                    </a>

                </aside>

            </div>

        </form>

    <?php else: ?>

        <div class="empty-cart">

            <div class="empty-cart-icon">🛒</div>

            <h2>Your cart is empty!</h2>

            <p>
                Looks like you haven't added anything yet.
            </p>

            <a href="menu.php" class="primary-btn">
                Explore Our Menu
            </a>

        </div>

    <?php endif; ?>

</section>


<footer class="footer">

    <div class="copyright">
        © 2026 CafeNest. All Rights Reserved.
    </div>

</footer>

<script src="assets/js/script.js"></script>

</body>
</html>
