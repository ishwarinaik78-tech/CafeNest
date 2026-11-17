
<?php
session_start();

require_once "config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

// Process checkout
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid request. Please refresh and try again.";
    } else {

        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $normalized_phone = preg_replace('/[\s()-]/', '', $phone);

        if (
            strlen($customer_name) < 2 ||
            strlen($customer_name) > 100
        ) {

            $error = "Please enter a valid customer name.";

        } elseif (
            !preg_match('/^\+?[0-9]{10,15}$/', $normalized_phone)
        ) {

            $error = "Please enter a valid phone number.";

        } elseif (empty($_SESSION['cart'])) {

            $error = "Your cart is empty.";

        } else {

            try {

                mysqli_begin_transaction($conn);

                $order_products = [];
                $total = 0;

                // Fetch current product details and prices
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id, name, price, status
                     FROM products
                     WHERE id = ?"
                );

                foreach ($_SESSION['cart'] as $id => $quantity) {

                    $id = (int)$id;
                    $quantity = (int)$quantity;

                    if ($id <= 0 || $quantity < 1 || $quantity > 99) {
                        throw new Exception("Invalid cart quantity.");
                    }

                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);

                    $result = mysqli_stmt_get_result($stmt);
                    $product = mysqli_fetch_assoc($result);

                    if (!$product || $product['status'] !== 'Available') {
                        throw new Exception(
                            "A product in your cart is no longer available."
                        );
                    }

                    $price = (float)$product['price'];
                    $subtotal = $price * $quantity;

                    $total += $subtotal;

                    $order_products[] = [
                        'id' => (int)$product['id'],
                        'name' => $product['name'],
                        'price' => $price,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal
                    ];
                }

                mysqli_stmt_close($stmt);

                if (empty($order_products) || $total <= 0) {
                    throw new Exception("Unable to process your order.");
                }

                // Insert main order
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO orders
                    (customer_name, phone, total_amount, status)
                    VALUES (?, ?, ?, 'Pending')"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssd",
                    $customer_name,
                    $normalized_phone,
                    $total
                );

                mysqli_stmt_execute($stmt);

                $order_id = mysqli_insert_id($conn);

                mysqli_stmt_close($stmt);

                // Insert individual order products
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO order_items
                    (order_id, product_id, quantity, price)
                    VALUES (?, ?, ?, ?)"
                );

                foreach ($order_products as $item) {

                    $product_id = $item['id'];
                    $quantity = $item['quantity'];
                    $price = $item['price'];

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iiid",
                        $order_id,
                        $product_id,
                        $quantity,
                        $price
                    );

                    mysqli_stmt_execute($stmt);
                }

                mysqli_stmt_close($stmt);

                // Commit transaction
                mysqli_commit($conn);

                // Save receipt for confirmation page
                $_SESSION['order_receipt'] = [
                    'order_id' => $order_id,
                    'customer_name' => $customer_name,
                    'phone' => $normalized_phone,
                    'products' => $order_products,
                    'total' => $total,
                    'date' => date('d-m-Y h:i A')
                ];

                // Empty cart
                $_SESSION['cart'] = [];

                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: order_success.php");
                exit;

            } catch (Throwable $e) {

                mysqli_rollback($conn);

                error_log("CafeNest checkout error: " . $e->getMessage());

                $error = "We could not place your order. Please check your cart and try again.";
            }
        }
    }
}


// Fetch products for checkout summary
$items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, price, image, status
         FROM products
         WHERE id = ?"
    );

    foreach ($_SESSION['cart'] as $id => $quantity) {

        $id = (int)$id;
        $quantity = (int)$quantity;

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);

        if (
            $product &&
            $product['status'] === 'Available' &&
            $quantity > 0
        ) {

            $product['quantity'] = $quantity;

            $product['subtotal'] =
                (float)$product['price'] * $quantity;

            $total += $product['subtotal'];

            $items[] = $product;

        }
    }

    mysqli_stmt_close($stmt);
}

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

    <title>Checkout | CafeNest</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>

<header class="navbar">

    <div class="nav-container">

        <a href="index.php" class="logo">
            ☕ CafeNest.
        </a>

        <nav>
            <a href="index.php">Home</a>
            <a href="menu.php">Menu</a>
        </nav>

        <div class="nav-buttons">
            <a href="cart.php" class="cart-btn">🛒 Cart</a>
        </div>

    </div>

</header>


<section class="checkout-section">

    <div class="checkout-heading">

        <p class="small-heading">ALMOST THERE!</p>

        <h1>Complete Your Order</h1>

        <p>Just a few details before we prepare your favourites.</p>

    </div>


    <?php if ($error): ?>

        <div class="checkout-error">
            <?php echo clean($error); ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($items)): ?>

        <div class="checkout-layout">

            <!-- CUSTOMER FORM -->

            <div class="checkout-form-box">

                <h2>Customer Details</h2>

                <form method="POST" action="checkout.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo clean($_SESSION['csrf_token']); ?>"
                    >

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="customer_name"
                        placeholder="Enter your full name"
                        value="<?php echo clean($_POST['customer_name'] ?? ''); ?>"
                        minlength="2"
                        maxlength="100"
                        required
                    >

                    <label>Phone Number</label>

                    <input
                        type="tel"
                        name="phone"
                        placeholder="Enter your phone number"
                        value="<?php echo clean($_POST['phone'] ?? ''); ?>"
                        required
                    >

                    <label>Payment Method</label>

                    <div class="payment-option">

                        <span>💵</span>

                        <div>
                            <strong>Cash on Delivery / Pay at Cafe</strong>
                            <p>Pay when you receive your order.</p>
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="place-order-btn">

                        Confirm Order – ₹<?php echo number_format($total, 2); ?>

                    </button>

                </form>

            </div>


            <!-- ORDER SUMMARY -->

            <div class="checkout-summary">

                <h2>Your Order</h2>

                <?php foreach ($items as $item): ?>

                    <div class="checkout-item">

                        <div>
                            <strong>
                                <?php echo clean($item['name']); ?>
                            </strong>

                            <p>
                                Quantity: <?php echo (int)$item['quantity']; ?>
                            </p>
                        </div>

                        <strong>
                            ₹<?php echo number_format(
                                $item['subtotal'], 2
                            ); ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

                <div class="checkout-total">

                    <span>Total Amount</span>

                    <strong>
                        ₹<?php echo number_format($total, 2); ?>
                    </strong>

                </div>

                <a href="cart.php" class="continue-shopping">
                    ← Back to Cart
                </a>

            </div>

        </div>

    <?php else: ?>

        <div class="empty-cart">

            <h2>Your cart is empty!</h2>

            <p>Add some delicious products before checkout.</p>

            <a href="menu.php" class="primary-btn">
                Explore Menu
            </a>

        </div>

    <?php endif; ?>

</section>


<footer class="footer">

    <div class="copyright">
        © 2026 CafeNest. All Rights Reserved.
    </div>

</footer>

</body>
</html>
