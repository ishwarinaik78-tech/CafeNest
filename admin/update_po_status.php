<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirectPO($message) {
    header("Location: purchase_orders.php?" . $message);
    exit;
}

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectPO("invalid_request=1");
}

// Validate CSRF token
$token = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["po_csrf"]) ||
    !hash_equals($_SESSION["po_csrf"], $token)
) {
    redirectPO("invalid_token=1");
}

// Validate purchase order ID
$poId = filter_var(
    $_POST["po_id"] ?? 0,
    FILTER_VALIDATE_INT
);

if (!$poId || $poId <= 0) {
    redirectPO("invalid_id=1");
}

$newStatus = $_POST["new_status"] ?? "";

// Only these transitions are allowed
$allowedTransitions = [
    "Draft" => ["Ordered", "Cancelled"],
    "Ordered" => ["Cancelled"]
];

try {

    $conn->begin_transaction();

    // Lock the purchase order while changing its status
    $stmt = $conn->prepare("
        SELECT id, status
        FROM purchase_orders
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $poId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $conn->rollback();
        redirectPO("not_found=1");
    }

    $currentStatus = $order["status"];

    // Check if transition is allowed
    if (
        !isset($allowedTransitions[$currentStatus]) ||
        !in_array(
            $newStatus,
            $allowedTransitions[$currentStatus],
            true
        )
    ) {
        $conn->rollback();
        redirectPO("invalid_status=1");
    }

    // Update status only. No stock changes here.
    $update = $conn->prepare("
        UPDATE purchase_orders
        SET status = ?
        WHERE id = ?
    ");

    $update->bind_param("si", $newStatus, $poId);
    $update->execute();

    $conn->commit();

    // Regenerate CSRF token
    $_SESSION["po_csrf"] = bin2hex(random_bytes(32));

    redirectPO("status_updated=1");

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // Ignore rollback errors
    }

    error_log("CafeNest PO Status Error: " . $e->getMessage());

    redirectPO("status_error=1");
}