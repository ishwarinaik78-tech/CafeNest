
<?php
session_start();

if (!isset($_SESSION['order_receipt'])) {
    header("Location: index.php");
    exit;
}

$receipt = $_SESSION['order_receipt'];

unset($_SESSION['order_receipt']);

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

    <title>Order Confirmed | CafeNest</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>

<header class="navbar">

    <div class="nav-container">

        <a href="index.php" class="logo">
            ☕ CafeNest.
        </a>

        <a href="index.php" class="login-btn">
            Home
        </a>

    </div>

</header>


<section class="success-section">

    <div class="success-card">

        <div class="success-icon">✓</div>

        <p class="small-heading">THANK YOU FOR CHOOSING CAFENEST</p>

        <h1>Order Placed Successfully!</h1>

        <p class="success-description">
            Your order has been received.
            We look forward to serving you!
        </p>

        <div class="order-number">

            Order ID:
            <strong>#<?php echo (int)$receipt['order_id']; ?></strong>

        </div>

        <div class="receipt">

            <h2>Order Receipt</h2>

            <p>
                <strong>Customer:</strong>
                <?php echo clean($receipt['customer_name']); ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?php echo clean($receipt['phone']); ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?php echo clean($receipt['date']); ?>
            </p>

            <div class="receipt-divider"></div>

            <?php foreach ($receipt['products'] as $item): ?>

                <div class="receipt-item">

                    <span>
                        <?php echo clean($item['name']); ?>
                        × <?php echo (int)$item['quantity']; ?>
                    </span>

                    <strong>
                        ₹<?php echo number_format(
                            $item['subtotal'], 2
                        ); ?>
                    </strong>

                </div>

            <?php endforeach; ?>

            <div class="receipt-divider"></div>

            <div class="receipt-total">

                <strong>Total Amount</strong>

                <strong>
                    ₹<?php echo number_format(
                        $receipt['total'], 2
                    ); ?>
                </strong>

            </div>

            <p class="payment-note">
                Payment: Cash on Delivery / Pay at Cafe
            </p>

            <p class="payment-note">
                Order Status: Pending
            </p>

        </div>

        <button onclick="window.print()"
                class="primary-btn print-btn">

            Print Bill

        </button>

        <a href="menu.php" class="continue-shopping">
            Continue Shopping →
        </a>

    </div>

</section>


<footer class="footer">

    <div class="copyright">
        © 2026 CafeNest. All Rights Reserved.
    </div>

</footer>

</body>
</html>
