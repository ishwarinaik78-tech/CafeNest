<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: products.php");
    exit;
}

$token = $_POST['csrf_token'] ?? "";

if (
    !isset($_SESSION['product_csrf']) ||
    !hash_equals($_SESSION['product_csrf'], $token)
) {
    die("Invalid request. Please go back and try again.");
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid product ID.");
}

// Check whether this product is used in an existing order.
$check = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM order_items WHERE product_id = ?"
);

mysqli_stmt_bind_param($check, "i", $id);
mysqli_stmt_execute($check);

$usage = mysqli_fetch_assoc(mysqli_stmt_get_result($check));

if ((int)$usage['total'] > 0) {
    die(
        "This product is part of an existing order and cannot be deleted. "
        . "Please edit it and mark it Unavailable instead. "
        . '<a href="products.php">Back to products</a>'
    );
}

// Get image path before deleting the database row.
$get = mysqli_prepare(
    $conn,
    "SELECT image FROM products WHERE id = ?"
);

mysqli_stmt_bind_param($get, "i", $id);
mysqli_stmt_execute($get);

$product = mysqli_fetch_assoc(mysqli_stmt_get_result($get));

if (!$product) {
    header("Location: products.php");
    exit;
}

$delete = mysqli_prepare(
    $conn,
    "DELETE FROM products WHERE id = ?"
);

mysqli_stmt_bind_param($delete, "i", $id);

if (mysqli_stmt_execute($delete)) {

    // Delete only locally uploaded product images.
    if (strpos($product['image'], 'uploads/products/') === 0) {

        $file = __DIR__ . "/../" . $product['image'];

        if (is_file($file)) {
            @unlink($file);
        }
    }

    header("Location: products.php?deleted=1");
    exit;

} else {
    die("Could not delete the product. <a href='products.php'>Go back</a>");
}