```php
<?php
session_start();

require_once __DIR__ . "/config/db.php";

// Create a CSRF token for the tracking form.
if (empty($_SESSION['track_csrf'])) {
    $_SESSION['track_csrf'] = bin2hex(random_bytes(32));
}

$order = null;
$orderItems = [];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['track_csrf'], $token)) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $phone = trim($_POST['phone'] ?? '');

        if (!$orderId || $orderId < 1 || $phone === '') {
            $error = "Please enter a valid Order ID and phone number.";
        } else {

            // Both Order ID and phone number must match.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, customer_name, phone, total_amount, status, created_at
                 FROM orders
                 WHERE id = ? AND phone = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, "is", $orderId, $phone);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$order) {
                $error = "No order found. Please check your Order ID and phone number.";
            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT p.name, oi.quantity, oi.price
                     FROM order_items oi
                     INNER JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $orderId);
                mysqli_stmt_execute($stmt);

                $itemsResult = mysqli_stmt_get_result($stmt);

                while ($item = mysqli_fetch_assoc($itemsResult)) {
                    $orderItems[] = $item;
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$steps = ["Pending", "Preparing", "Ready", "Completed"];
$currentStatus = $order['status'] ?? '';
$currentStep = array_search($currentStatus, $steps, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order | CafeNest</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f7f2eb;
            color: #35251d;
            padding: 30px 15px;
        }

        .container {
            max-width: 750px;
            margin: 20px auto;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            color: #70452c;
            margin-bottom: 8px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #70452c;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #51311f;
        }

        .error {
            background: #ffe8e8;
            color: #a00000;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .order-info p {
            margin: 10px 0;
            line-height: 1.5;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: #eee5dc;
            color: #70452c;
            font-weight: bold;
        }

        .progress {
            display: flex;
            justify-content: space-between;
            gap: 5px;
            margin: 28px 0;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            font-size: 12px;
            color: #999;
            position: relative;
        }

        .progress-step .circle {
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background: #ddd;
            margin: 0 auto 8px;
            font-weight: bold;
        }

        .progress-step.active {
            color: #70452c;
            font-weight: bold;
        }

        .progress-step.active .circle {
            background: #70452c;
            color: white;
        }

        .cancelled {
            background: #ffe8e8;
            color: #a00000;
            padding: 14px;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 11px 7px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f7f2eb;
        }

        .total {
            text-align: right;
            font-size: 19px;
            font-weight: bold;
            margin-top: 18px;
        }

        .back {
            display: block;
            text-align: center;
            color: #70452c;
            margin-top: 20px;
            text-decoration: none;
        }

        @media (max-width: 500px) {
            .card {
                padding: 18px;
            }

            .progress-step {
                font-size: 10px;
            }

            th, td {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>CafeNest</h1>
        <p>Track your order and follow its progress.</p>
    </div>

    <div class="card">

        <h2>Track Your Order</h2>
        <p style="margin: 10px 0 20px;">
            Enter the Order ID and phone number used during checkout.
        </p>

        <?php if ($error !== ""): ?>
            <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="track_order.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION['track_csrf']); ?>"
            >

            <div class="form-group">
                <label for="order_id">Order ID</label>
                <input
                    type="number"
                    id="order_id"
                    name="order_id"
                    min="1"
                    required
                    placeholder="Example: 12"
                    value="<?php echo e($_POST['order_id'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    required
                    placeholder="Enter your checkout phone number"
                    value="<?php echo e($_POST['phone'] ?? ''); ?>"
                >
            </div>

            <button type="submit">Track My Order</button>

        </form>
    </div>

    <?php if ($order): ?>

        <div class="card">

            <h2>Order Details</h2>

            <div class="order-info">
                <p><strong>Order ID:</strong> #<?php echo e($order['id']); ?></p>
                <p><strong>Customer:</strong> <?php echo e($order['customer_name']); ?></p>
                <p><strong>Order Date:</strong> <?php echo e($order['created_at']); ?></p>
                <p>
                    <strong>Current Status:</strong>
                    <span class="status"><?php echo e($currentStatus); ?></span>
                </p>
            </div>

            <?php if ($currentStatus === "Cancelled"): ?>

                <div class="cancelled">
                    This order has been cancelled.
                </div>

            <?php else: ?>

                <h3 style="margin-top: 25px;">Order Progress</h3>

                <div class="progress">

                    <?php foreach ($steps as $index => $step): ?>

                        <div class="progress-step <?php
                            echo ($currentStep !== false && $index <= $currentStep)
                                ? 'active'
                                : '';
                        ?>">
                            <div class="circle">
                                <?php echo ($currentStep !== false && $index <= $currentStep) ? '✓' : ($index + 1); ?>
                            </div>

                            <?php echo e($step); ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <h3 style="margin-top: 25px;">Items Ordered</h3>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?php echo e($item['name']); ?></td>
                            <td><?php echo e($item['quantity']); ?></td>
                            <td>₹<?php echo number_format((float)$item['price'], 2); ?></td>
                            <td>
                                ₹<?php
                                    echo number_format(
                                        (float)$item['price'] * (int)$item['quantity'],
                                        2
                                    );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total">
                Total: ₹<?php echo number_format((float)$order['total_amount'], 2); ?>
            </div>

        </div>

    <?php endif; ?>

    <a href="index.php" class="back">← Back to Home</a>

</div>

</body>
</html>
```