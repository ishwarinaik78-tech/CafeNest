<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: orders.php");
    exit;
}

$token = $_POST['csrf_token'] ?? "";

if (
    !isset($_SESSION['order_csrf']) ||
    !hash_equals($_SESSION['order_csrf'], $token)
) {
    die("Invalid request. Please go back and try again.");
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? "";

$allowedStatuses = [
    "Pending",
    "Preparing",
    "Ready",
    "Completed",
    "Cancelled"
];

if (!$orderId || $orderId < 1) {
    die("Invalid order ID.");
}

if (!in_array($status, $allowedStatuses, true)) {
    die("Invalid order status.");
}

// Check that the order exists
$check = mysqli_prepare(
    $conn,
    "SELECT id FROM orders WHERE id = ?"
);

mysqli_stmt_bind_param($check, "i", $orderId);
mysqli_stmt_execute($check);

$existingOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

if (!$existingOrder) {
    die("Order not found.");
}

// Update the status
$update = mysqli_prepare(
    $conn,
    "UPDATE orders SET status = ? WHERE id = ?"
);

mysqli_stmt_bind_param($update, "si", $status, $orderId);

if (mysqli_stmt_execute($update)) {
    header("Location: order_details.php?id=" . $orderId . "&updated=1");
    exit;
}

die("Could not update the order status.");