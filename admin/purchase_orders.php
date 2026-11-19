
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['po_csrf'])) {
    $_SESSION['po_csrf'] = bin2hex(random_bytes(32));
}

function po_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$error = "";

// =========================================
// CREATE PURCHASE ORDER
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_po"])) {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION['po_csrf'], $token)) {

        $error = "Invalid request. Please refresh the page.";

    } else {

        $supplierId = filter_var(
            $_POST["supplier_id"] ?? 0,
            FILTER_VALIDATE_INT
        );

        $status = $_POST["status"] ?? "Draft";
        $notes = trim($_POST["notes"] ?? "");

        $ingredientIds = $_POST["ingredient_id"] ?? [];
        $quantities = $_POST["quantity"] ?? [];
        $costs = $_POST["unit_cost"] ?? [];

        if (!$supplierId || !in_array($status, ["Draft", "Ordered"], true)) {

            $error = "Please select a valid supplier and status.";

        } elseif (
            !is_array($ingredientIds) ||
            !is_array($quantities) ||
            !is_array($costs) ||
            empty($ingredientIds)
        ) {

            $error = "Please add at least one ingredient.";

        } elseif (
            count($ingredientIds) !== count($quantities) ||
            count($ingredientIds) !== count($costs)
        ) {

            $error = "Invalid ingredient form data.";

        } else {

            $items = [];
            $totalAmount = 0;
            $usedIngredients = [];

            foreach ($ingredientIds as $index => $ingredientId) {

                $ingredientId = filter_var(
                    $ingredientId,
                    FILTER_VALIDATE_INT
                );

                $quantityRaw = $quantities[$index] ?? "";
                $costRaw = $costs[$index] ?? "";

                if (
                    $quantityRaw === "" ||
                    $costRaw === "" ||
                    !is_numeric($quantityRaw) ||
                    !is_numeric($costRaw)
                ) {
                    $error = "Please enter valid quantities and costs.";
                    break;
                }

                $quantity = (float)$quantityRaw;
                $unitCost = (float)$costRaw;

                if (
                    !$ingredientId ||
                    !is_finite($quantity) ||
                    !is_finite($unitCost) ||
                    $quantity <= 0 ||
                    $unitCost < 0
                ) {
                    $error = "Please enter valid ingredient quantities and costs.";
                    break;
                }

                // Prevent duplicate ingredients
                if (isset($usedIngredients[$ingredientId])) {
                    $error = "Please do not add the same ingredient more than once.";
                    break;
                }

                $usedIngredients[$ingredientId] = true;

                $subtotal = round($quantity * $unitCost, 2);

                $items[] = [
                    "ingredient_id" => $ingredientId,
                    "quantity" => $quantity,
                    "unit_cost" => $unitCost,
                    "subtotal" => $subtotal
                ];

                $totalAmount += $subtotal;
            }

            $totalAmount = round($totalAmount, 2);

            if (!$error) {

                try {

                    $conn->begin_transaction();

                    // Verify supplier is active
                    $stmt = $conn->prepare("
                        SELECT id
                        FROM suppliers
                        WHERE id = ?
                          AND is_active = 1
                    ");

                    $stmt->bind_param("i", $supplierId);
                    $stmt->execute();

                    if (!$stmt->get_result()->fetch_assoc()) {
                        throw new Exception("Supplier is not active.");
                    }

                    // Verify supplier-ingredient mapping
                    $checkIngredient = $conn->prepare("
                        SELECT i.id
                        FROM ingredients i
                        INNER JOIN supplier_ingredients si
                            ON si.ingredient_id = i.id
                        WHERE i.id = ?
                          AND i.is_active = 1
                          AND si.supplier_id = ?
                    ");

                    foreach ($items as $item) {

                        $iid = $item["ingredient_id"];

                        $checkIngredient->bind_param(
                            "ii",
                            $iid,
                            $supplierId
                        );

                        $checkIngredient->execute();

                        if (!$checkIngredient->get_result()->fetch_assoc()) {
                            throw new Exception(
                                "An ingredient is inactive or not mapped to this supplier."
                            );
                        }
                    }

                    // Generate PO number
                    $poNumber = "PO-" . date("Ymd") . "-" .
                        strtoupper(bin2hex(random_bytes(3)));

                    // Insert purchase order
                    $stmt = $conn->prepare("
                        INSERT INTO purchase_orders
                        (po_number, supplier_id, status, total_amount, notes)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "sisds",
                        $poNumber,
                        $supplierId,
                        $status,
                        $totalAmount,
                        $notes
                    );

                    $stmt->execute();

                    $poId = $conn->insert_id;

                    // Insert purchase order items
                    $stmtItem = $conn->prepare("
                        INSERT INTO purchase_order_items
                        (purchase_order_id, ingredient_id, quantity,
                         unit_cost, subtotal)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    foreach ($items as $item) {

                        $iid = $item["ingredient_id"];
                        $qty = $item["quantity"];
                        $cost = $item["unit_cost"];
                        $subtotal = $item["subtotal"];

                        $stmtItem->bind_param(
                            "iiddd",
                            $poId,
                            $iid,
                            $qty,
                            $cost,
                            $subtotal
                        );

                        $stmtItem->execute();
                    }

                    $conn->commit();

                    // Regenerate CSRF token
                    $_SESSION['po_csrf'] = bin2hex(random_bytes(32));

                    header("Location: purchase_orders.php?created=1");
                    exit;

                } catch (Throwable $e) {

                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackError) {
                        // Transaction may already have ended
                    }

                    error_log("CafeNest Purchase Order Error: " . $e->getMessage());

                    $error = "Unable to create the purchase order. Please check your supplier, ingredients, and database configuration.";
                }
            }
        }
    }
}

// =========================================
// SUCCESS AND ERROR MESSAGES
// =========================================

if (isset($_GET["created"])) {
    $message = "Purchase order created successfully!";
}

if (isset($_GET["status_updated"])) {
    $message = "Purchase order status updated successfully!";
}

if (isset($_GET["received"])) {
    $message = "Purchase order received successfully!";
}

if (isset($_GET["invalid_request"])) {
    $error = "Invalid request.";
}

if (isset($_GET["invalid_token"])) {
    $error = "Security validation failed. Please refresh and try again.";
}

if (isset($_GET["invalid_id"])) {
    $error = "Invalid purchase order ID.";
}

if (isset($_GET["not_found"])) {
    $error = "Purchase order not found.";
}

if (isset($_GET["invalid_status"])) {
    $error = "This status change is not allowed.";
}

if (isset($_GET["status_error"])) {
    $error = "Unable to update purchase order status.";
}

// =========================================
// FETCH ACTIVE SUPPLIERS
// =========================================

$suppliers = [];

$result = $conn->query("
    SELECT id, supplier_name, company_name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY supplier_name
");

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

// =========================================
// FETCH ACTIVE INGREDIENTS
// =========================================

$ingredients = [];

$result = $conn->query("
    SELECT id, name, unit, cost_per_unit
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name
");

while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

// =========================================
// FETCH PURCHASE ORDERS
// =========================================

$orders = [];

$result = $conn->query("
    SELECT
        po.id,
        po.po_number,
        po.supplier_id,
        po.status,
        po.total_amount,
        po.notes,
        po.created_at,
        s.supplier_name
    FROM purchase_orders po
    INNER JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.id DESC
");

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// =========================================
// FETCH SUPPLIER-INGREDIENT MAPPINGS
// =========================================

$supplierIngredients = [];

$result = $conn->query("
    SELECT supplier_id, ingredient_id
    FROM supplier_ingredients
");

while ($row = $result->fetch_assoc()) {
    $supplierIngredients[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Purchase Orders | CafeNest</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #222;
}

.container {
    max-width: 1300px;
    margin: 30px auto;
    padding: 20px;
}

h1 {
    margin: 0 0 8px;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.card {
    background: #fff;
    padding: 24px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #6c3fc5;
    box-shadow: 0 0 0 2px rgba(108,63,197,.1);
}

textarea {
    resize: vertical;
}

.items-scroll,
.table-wrapper {
    overflow-x: auto;
}

.items-table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
    margin-top: 15px;
}

.items-table th,
.items-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.items-table th {
    background: #f7f7f7;
}

.items-table select,
.items-table input {
    min-width: 110px;
}

.btn {
    display: inline-block;
    border: none;
    padding: 11px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 12px;
    text-decoration: none;
}

.btn-primary {
    background: #6c3fc5;
    color: white;
}

.btn-primary:hover {
    background: #5831a7;
}

.btn-secondary {
    background: #e9e9ef;
    color: #333;
}

.btn-secondary:hover {
    background: #dddde5;
}

.btn-danger {
    background: #ffe8e8;
    color: #b42323;
}

.total-box {
    text-align: right;
    font-size: 24px;
    font-weight: bold;
    margin-top: 20px;
}

.alert {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.success {
    background: #e8f8ed;
    color: #217a3b;
}

.error {
    background: #fdeaea;
    color: #a52828;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.orders-table th,
.orders-table td {
    padding: 14px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: middle;
}

.orders-table th {
    background: #f7f7f7;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.Draft {
    background: #fff4d6;
    color: #936900;
}

.Ordered {
    background: #e4efff;
    color: #245aab;
}

.Received {
    background: #dff7e7;
    color: #237c43;
}

.Cancelled {
    background: #ffe4e4;
    color: #a52828;
}

.remove-btn {
    padding: 8px 10px;
    border: none;
    border-radius: 7px;
    background: #ffe7e7;
    color: #b22;
    cursor: pointer;
}

.view-btn {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 7px;
    background: #eee8ff;
    color: #6c3fc5;
    font-weight: bold;
    text-decoration: none;
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.action-form {
    display: inline-block;
    margin: 0;
}

.action-btn {
    border: none;
    padding: 8px 12px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
    white-space: nowrap;
}

.order-btn {
    background: #e4efff;
    color: #245aab;
}

.receive-btn {
    background: #dff7e7;
    color: #237c43;
}

.cancel-btn {
    background: #ffe4e4;
    color: #a52828;
}

.action-btn:hover {
    opacity: 0.85;
}

.history-controls {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.history-controls input,
.history-controls select {
    max-width: 280px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #777;
}

@media(max-width: 700px) {

    .container {
        padding: 12px;
        margin: 10px auto;
    }

    .card {
        padding: 16px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .total-box {
        font-size: 20px;
    }

    .history-controls {
        flex-direction: column;
    }

    .history-controls input,
    .history-controls select {
        max-width: 100%;
    }
}
</style>
</head>

<body>

<div class="container">

    <h1>Purchase Orders</h1>

    <p class="subtitle">
        Create supplier purchase orders and manage ingredient purchasing.
    </p>

    <?php if ($message): ?>
        <div class="alert success">
            <?= po_h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?= po_h($error) ?>
        </div>
    <?php endif; ?>

    <!-- =====================================
         CREATE PURCHASE ORDER
    ====================================== -->

    <div class="card">

        <h2>Create Purchase Order</h2>

        <form method="POST" id="poForm">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= po_h($_SESSION['po_csrf']) ?>">

            <div class="form-grid">

                <div>
                    <label>Supplier</label>

                    <select name="supplier_id" id="supplierSelect" required>

                        <option value="">Select Supplier</option>

                        <?php foreach ($suppliers as $supplier): ?>

                            <option value="<?= (int)$supplier['id'] ?>">
                                <?= po_h($supplier['supplier_name']) ?>
                                <?php if (!empty($supplier['company_name'])): ?>
                                    — <?= po_h($supplier['company_name']) ?>
                                <?php endif; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label>Order Status</label>

                    <select name="status" required>
                        <option value="Draft">Draft</option>
                        <option value="Ordered">Ordered</option>
                    </select>
                </div>

            </div>

            <h3>Order Ingredients</h3>

            <div class="items-scroll">

                <table class="items-table">

                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Quantity</th>
                            <th>Unit Cost (₹)</th>
                            <th>Subtotal (₹)</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="itemsBody"></tbody>

                </table>

            </div>

            <button type="button"
                    class="btn btn-secondary"
                    id="addItemBtn">
                + Add Ingredient
            </button>

            <div class="total-box">
                Grand Total: ₹<span id="grandTotal">0.00</span>
            </div>

            <div style="margin-top:20px;">
                <label>Order Notes</label>

                <textarea name="notes"
                          rows="3"
                          placeholder="Optional notes for this purchase order..."></textarea>
            </div>

            <button type="submit"
                    name="create_po"
                    value="1"
                    class="btn btn-primary">
                Create Purchase Order
            </button>

        </form>

    </div>

    <!-- =====================================
         PURCHASE ORDER HISTORY
    ====================================== -->

    <div class="card">

        <h2>Purchase Order History</h2>

        <div class="history-controls">

            <input type="text"
                   id="searchPO"
                   placeholder="Search PO number or supplier...">

            <select id="filterStatus">
                <option value="">All Statuses</option>
                <option value="Draft">Draft</option>
                <option value="Ordered">Ordered</option>
                <option value="Received">Received</option>
                <option value="Cancelled">Cancelled</option>
            </select>

        </div>

        <div class="table-wrapper">

            <table class="orders-table">

                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="ordersBody">

                <?php if (empty($orders)): ?>

                    <tr id="emptyOrdersRow">
                        <td colspan="6" class="empty-state">
                            No purchase orders created yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($orders as $order): ?>

                        <tr class="order-row"
                            data-status="<?= po_h($order['status']) ?>"
                            data-search="<?= po_h(strtolower(
                                $order['po_number'] . ' ' .
                                $order['supplier_name']
                            )) ?>">

                            <td>
                                <strong><?= po_h($order['po_number']) ?></strong>
                            </td>

                            <td><?= po_h($order['supplier_name']) ?></td>

                            <td><?= po_h($order['created_at']) ?></td>

                            <td>
                                <span class="status <?= po_h($order['status']) ?>">
                                    <?= po_h($order['status']) ?>
                                </span>
                            </td>

                            <td>
                                ₹<?= number_format(
                                    (float)$order['total_amount'],
                                    2
                                ) ?>
                            </td>

                            <!-- PO ACTIONS -->
                            <td>
                                <div class="action-buttons">

                                    <!-- VIEW -->
                                    <a class="view-btn"
                                       href="view_purchase_order.php?id=<?= (int)$order['id'] ?>">
                                        View
                                    </a>

                                    <?php if ($order['status'] === 'Draft'): ?>

                                        <!-- MARK ORDERED -->
                                        <form method="POST"
                                              action="update_po_status.php"
                                              class="action-form">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= po_h($_SESSION['po_csrf']) ?>">

                                            <input type="hidden"
                                                   name="po_id"
                                                   value="<?= (int)$order['id'] ?>">

                                            <input type="hidden"
                                                   name="new_status"
                                                   value="Ordered">

                                            <button type="submit"
                                                    class="action-btn order-btn">
                                                Mark Ordered
                                            </button>
                                        </form>

                                        <!-- CANCEL DRAFT -->
                                        <form method="POST"
                                              action="update_po_status.php"
                                              class="action-form"
                                              onsubmit="return confirm('Cancel this purchase order?');">

                                            <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= po_h($_SESSION['po_csrf']) ?>">

                                            <input type="hidden"
                                                       name="po_id"
                                                       value="<?= (int)$order['id'] ?>">

                                            <input type="hidden"
                                                       name="new_status"
                                                       value="Cancelled">

                                            <button type="submit"
                                                    class="action-btn cancel-btn">
                                                Cancel
                                            </button>
                                        </form>

                                    <?php elseif ($order['status'] === 'Ordered'): ?>

                                        <!-- RECEIVE STOCK -->
                                        <a class="action-btn receive-btn"
                                           href="receive_purchase_order.php?id=<?= (int)$order['id'] ?>">
                                            Receive Stock
                                        </a>

                                        <!-- CANCEL ORDER -->
                                        <form method="POST"
                                              action="update_po_status.php"
                                              class="action-form"
                                              onsubmit="return confirm('Cancel this ordered purchase order?');">

                                            <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= po_h($_SESSION['po_csrf']) ?>">

                                            <input type="hidden"
                                                       name="po_id"
                                                       value="<?= (int)$order['id'] ?>">

                                            <input type="hidden"
                                                       name="new_status"
                                                       value="Cancelled">

                                            <button type="submit"
                                                    class="action-btn cancel-btn">
                                                Cancel
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                <tr id="noResults" style="display:none;">
                    <td colspan="6" class="empty-state">
                        No purchase orders match your search.
                    </td>
                </tr>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>

// =========================================
// DATA FROM PHP
// =========================================

const ingredients = <?= json_encode($ingredients) ?>;
const supplierIngredients = <?= json_encode($supplierIngredients) ?>;

const itemsBody = document.getElementById("itemsBody");
const supplierSelect = document.getElementById("supplierSelect");
const grandTotal = document.getElementById("grandTotal");

// =========================================
// ADD INGREDIENT ROW
// =========================================

function addItemRow() {

    const row = document.createElement("tr");

    row.innerHTML = `
        <td>
            <select name="ingredient_id[]"
                    class="ingredient-select"
                    required>
                <option value="">Select Ingredient</option>
            </select>
        </td>

        <td>
            <input type="number"
                   name="quantity[]"
                   class="quantity-input"
                   min="0.001"
                   step="0.001"
                   value="1"
                   required>
        </td>

        <td>
            <input type="number"
                   name="unit_cost[]"
                   class="cost-input"
                   min="0"
                   step="0.01"
                   value="0"
                   required>
        </td>

        <td class="subtotal">0.00</td>

        <td>
            <button type="button" class="remove-btn">
                Remove
            </button>
        </td>
    `;

    itemsBody.appendChild(row);

    const ingredientSelect = row.querySelector(".ingredient-select");
    const quantityInput = row.querySelector(".quantity-input");
    const costInput = row.querySelector(".cost-input");

    populateIngredientDropdown(ingredientSelect);

    ingredientSelect.addEventListener("change", function() {

        const selected = ingredients.find(
            ingredient => String(ingredient.id) === String(this.value)
        );

        if (selected) {
            costInput.value = selected.cost_per_unit;
        }

        calculateTotals();
    });

    quantityInput.addEventListener("input", calculateTotals);
    costInput.addEventListener("input", calculateTotals);

    row.querySelector(".remove-btn").addEventListener("click", function() {
        row.remove();
        calculateTotals();
    });

    calculateTotals();
}

// =========================================
// FILTER INGREDIENTS BY SUPPLIER
// =========================================

function populateIngredientDropdown(select) {

    const supplierId = supplierSelect.value;

    select.innerHTML = '<option value="">Select Ingredient</option>';

    if (!supplierId) {
        return;
    }

    const mappedIds = supplierIngredients
        .filter(item => String(item.supplier_id) === String(supplierId))
        .map(item => String(item.ingredient_id));

    ingredients
        .filter(ingredient => mappedIds.includes(String(ingredient.id)))
        .forEach(ingredient => {

            const option = document.createElement("option");

            option.value = ingredient.id;

            option.textContent =
                ingredient.name + " (" + ingredient.unit + ")";

            select.appendChild(option);
        });
}

// =========================================
// SUPPLIER CHANGE
// =========================================

supplierSelect.addEventListener("change", function() {

    document.querySelectorAll(".ingredient-select").forEach(function(select) {
        populateIngredientDropdown(select);
    });

});

// =========================================
// CALCULATE TOTALS
// =========================================

function calculateTotals() {

    let total = 0;

    itemsBody.querySelectorAll("tr").forEach(function(row) {

        const quantity = parseFloat(
            row.querySelector(".quantity-input").value
        ) || 0;

        const cost = parseFloat(
            row.querySelector(".cost-input").value
        ) || 0;

        const subtotal = quantity * cost;

        row.querySelector(".subtotal").textContent = subtotal.toFixed(2);

        total += subtotal;
    });

    grandTotal.textContent = total.toFixed(2);
}

// =========================================
// PURCHASE ORDER SEARCH AND FILTER
// =========================================

const searchPO = document.getElementById("searchPO");
const filterStatus = document.getElementById("filterStatus");
const orderRows = document.querySelectorAll(".order-row");
const noResults = document.getElementById("noResults");
const emptyOrdersRow = document.getElementById("emptyOrdersRow");

function filterOrders() {

    const search = searchPO.value.trim().toLowerCase();
    const status = filterStatus.value;

    let visibleCount = 0;

    orderRows.forEach(function(row) {

        const searchText = row.dataset.search || "";
        const rowStatus = row.dataset.status || "";

        const matchesSearch = searchText.includes(search);
        const matchesStatus = !status || rowStatus === status;

        if (matchesSearch && matchesStatus) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    // Show "no matching results" only if orders exist
    if (noResults) {
        noResults.style.display =
            orderRows.length > 0 && visibleCount === 0 ? "" : "none";
    }

    // Hide the initial empty message if filters are being used
    if (emptyOrdersRow) {
        emptyOrdersRow.style.display =
            search || status ? "none" : "";
    }
}

searchPO.addEventListener("input", filterOrders);
filterStatus.addEventListener("change", filterOrders);

// =========================================
// INITIALIZE
// =========================================

document.getElementById("addItemBtn").addEventListener("click", addItemRow);

addItemRow();

</script>

</body>
</html>
