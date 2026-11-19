
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================
// CSRF TOKEN
// =====================================

if (empty($_SESSION["supplier_ingredient_csrf"])) {
    $_SESSION["supplier_ingredient_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["supplier_ingredient_csrf"];

$message = "";
$messageType = "success";

// =====================================
// ASSIGN SUPPLIER TO INGREDIENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["assign"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["supplier_ingredient_csrf"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $supplierId = (int)($_POST["supplier_id"] ?? 0);
    $ingredientId = (int)($_POST["ingredient_id"] ?? 0);

    if ($supplierId <= 0 || $ingredientId <= 0) {

        $message = "Please select a supplier and ingredient.";
        $messageType = "error";

    } else {

        // Verify supplier and ingredient are active

        $check = $conn->prepare("
            SELECT s.id, i.id
            FROM suppliers s
            CROSS JOIN ingredients i
            WHERE s.id = ?
              AND s.is_active = 1
              AND i.id = ?
              AND i.is_active = 1
        ");

        $check->bind_param("ii", $supplierId, $ingredientId);
        $check->execute();

        if ($check->get_result()->num_rows === 1) {

            // Avoid duplicate assignments

            $stmt = $conn->prepare("
                INSERT IGNORE INTO supplier_ingredients
                (supplier_id, ingredient_id)
                VALUES (?, ?)
            ");

            $stmt->bind_param("ii", $supplierId, $ingredientId);

            if ($stmt->execute()) {
                header("Location: manage_supplier_ingredients.php?assigned=1");
                exit;
            }

            $message = "Unable to assign supplier.";
            $messageType = "error";

        } else {

            $message = "Selected supplier or ingredient is inactive or unavailable.";
            $messageType = "error";
        }
    }
}

// =====================================
// REMOVE ASSIGNMENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_assignment"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["supplier_ingredient_csrf"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $assignmentId = (int)($_POST["assignment_id"] ?? 0);

    if ($assignmentId > 0) {

        $stmt = $conn->prepare("
            DELETE FROM supplier_ingredients
            WHERE id = ?
        ");

        $stmt->bind_param("i", $assignmentId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: manage_supplier_ingredients.php?removed=1");
            exit;
        }
    }

    $message = "Unable to remove supplier assignment.";
    $messageType = "error";
}

// =====================================
// NOTIFICATIONS
// =====================================

if (isset($_GET["assigned"])) {
    $message = "Supplier successfully assigned to ingredient.";
}

if (isset($_GET["removed"])) {
    $message = "Supplier assignment removed successfully.";
}

// =====================================
// ACTIVE SUPPLIERS
// =====================================

$supplierQuery = $conn->query("
    SELECT id, supplier_name, company_name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY supplier_name ASC
");

// =====================================
// ACTIVE INGREDIENTS
// =====================================

$ingredientQuery = $conn->query("
    SELECT id, name, unit, current_stock
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

// =====================================
// EXISTING ASSIGNMENTS
// =====================================

$assignments = $conn->query("
    SELECT
        si.id AS assignment_id,
        s.supplier_name,
        s.company_name,
        i.name AS ingredient_name,
        i.unit,
        si.created_at
    FROM supplier_ingredients si
    INNER JOIN suppliers s ON si.supplier_id = s.id
    INNER JOIN ingredients i ON si.ingredient_id = i.id
    ORDER BY i.name ASC, s.supplier_name ASC
");

// =====================================
// STATISTICS
// =====================================

$totalAssignmentsQuery = $conn->query("
    SELECT COUNT(*) AS total
    FROM supplier_ingredients
");

$totalAssignments = (int)$totalAssignmentsQuery->fetch_assoc()["total"];

$ingredientCountQuery = $conn->query("
    SELECT COUNT(DISTINCT ingredient_id) AS total
    FROM supplier_ingredients
");

$coveredIngredients = (int)$ingredientCountQuery->fetch_assoc()["total"];

$supplierCountQuery = $conn->query("
    SELECT COUNT(DISTINCT supplier_id) AS total
    FROM supplier_ingredients
");

$connectedSuppliers = (int)$supplierCountQuery->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supplier Ingredient Mapping | CafeNest</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8fafc;
    color: #1e293b;
}

.container {
    width: 95%;
    max-width: 1400px;
    margin: 30px auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    color: #64748b;
    margin-top: 8px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
    border: 1px solid #e8edf4;
    margin-bottom: 22px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-label {
    color: #64748b;
    font-size: 14px;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-top: 10px;
}

.blue {
    color: #2563eb;
}

.green {
    color: #16a34a;
}

.orange {
    color: #ea580c;
}

.section-title {
    font-size: 20px;
    margin: 0 0 20px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.btn-blue {
    background: #2563eb;
    color: white;
}

.btn-light {
    background: #eaf1ff;
    color: #2563eb;
}

.btn-red {
    background: #fee2e2;
    color: #991b1b;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

label {
    font-size: 14px;
    font-weight: 600;
}

select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    background: white;
    font-size: 14px;
}

select:focus {
    border-color: #2563eb;
    outline: none;
}

.submit-row {
    margin-top: 20px;
}

.alert {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 14px;
    background: #f1f5f9;
    color: #475569;
    font-size: 13px;
}

td {
    padding: 14px;
    border-bottom: 1px solid #edf1f5;
    font-size: 14px;
}

tbody tr:hover {
    background: #f8fafc;
}

.remove-btn {
    padding: 8px 11px;
    font-size: 12px;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 35px;
}

@media(max-width: 768px) {

    .container {
        width: 93%;
        margin: 20px auto;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .header h1 {
        font-size: 23px;
    }
}
</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>Supplier - Ingredient Mapping</h1>
            <p>Manage which suppliers provide your cafe ingredients.</p>
        </div>

        <a href="suppliers.php" class="btn btn-light">
            ← Back to Suppliers
        </a>

    </div>

    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="alert <?= $messageType === "error" ? "alert-error" : "alert-success" ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <!-- STATISTICS -->

    <div class="stats">

        <div class="card">
            <div class="stat-label">Total Assignments</div>
            <div class="stat-number blue">
                <?= $totalAssignments ?>
            </div>
        </div>

        <div class="card">
            <div class="stat-label">Ingredients Covered</div>
            <div class="stat-number green">
                <?= $coveredIngredients ?>
            </div>
        </div>

        <div class="card">
            <div class="stat-label">Connected Suppliers</div>
            <div class="stat-number orange">
                <?= $connectedSuppliers ?>
            </div>
        </div>

    </div>

    <!-- ASSIGN FORM -->

    <div class="card">

        <h2 class="section-title">Assign Supplier to Ingredient</h2>

        <?php if ($supplierQuery->num_rows > 0 && $ingredientQuery->num_rows > 0): ?>

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-grid">

                    <div class="form-group">

                        <label>Select Supplier</label>

                        <select name="supplier_id" required>

                            <option value="">-- Choose Supplier --</option>

                            <?php while ($supplier = $supplierQuery->fetch_assoc()): ?>

                                <option value="<?= (int)$supplier["id"] ?>">

                                    <?= htmlspecialchars($supplier["supplier_name"]) ?>

                                    <?php if (!empty($supplier["company_name"])): ?>
                                        - <?= htmlspecialchars($supplier["company_name"]) ?>
                                    <?php endif; ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Select Ingredient</label>

                        <select name="ingredient_id" required>

                            <option value="">-- Choose Ingredient --</option>

                            <?php while ($ingredient = $ingredientQuery->fetch_assoc()): ?>

                                <option value="<?= (int)$ingredient["id"] ?>">

                                    <?= htmlspecialchars($ingredient["name"]) ?>
                                    (<?= htmlspecialchars($ingredient["unit"]) ?>)

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                </div>

                <div class="submit-row">

                    <button type="submit"
                            name="assign"
                            class="btn btn-blue">
                        + Assign Supplier
                    </button>

                </div>

            </form>

        <?php else: ?>

            <p class="empty">
                You need at least one active supplier and one active ingredient to create an assignment.
            </p>

        <?php endif; ?>

    </div>

    <!-- ASSIGNMENT TABLE -->

    <div class="card">

        <h2 class="section-title">Existing Assignments</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Ingredient</th>
                        <th>Supplier</th>
                        <th>Company</th>
                        <th>Assigned On</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($assignments->num_rows > 0): ?>

                    <?php while ($row = $assignments->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <strong><?= htmlspecialchars($row["ingredient_name"]) ?></strong>
                                <div style="color:#64748b;font-size:12px;margin-top:4px;">
                                    <?= htmlspecialchars($row["unit"]) ?>
                                </div>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["supplier_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["company_name"] ?? "") ?>
                            </td>

                            <td>
                                <?= date("d M Y", strtotime($row["created_at"])) ?>
                            </td>

                            <td>

                                <form method="POST"
                                      onsubmit="return confirm('Remove this supplier from this ingredient?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= htmlspecialchars($csrfToken) ?>">

                                    <input type="hidden"
                                           name="assignment_id"
                                           value="<?= (int)$row["assignment_id"] ?>">

                                    <button type="submit"
                                            name="remove_assignment"
                                            class="btn btn-red remove-btn">
                                        Remove
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            No supplier-ingredient assignments yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
