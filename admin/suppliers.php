
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================
// CSRF TOKEN
// =====================================

if (empty($_SESSION["supplier_csrf"])) {
    $_SESSION["supplier_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["supplier_csrf"];

// =====================================
// NOTIFICATIONS
// =====================================

$message = "";
$messageType = "success";

if (isset($_GET["updated"])) {
    $message = "Supplier details updated successfully.";
}

if (isset($_GET["status_changed"])) {
    $message = "Supplier status updated successfully.";
}

if (isset($_GET["added"])) {
    $message = "Supplier added successfully.";
}

// =====================================
// ADD SUPPLIER
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_supplier"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["supplier_csrf"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $supplierName = trim($_POST["supplier_name"] ?? "");
    $companyName = trim($_POST["company_name"] ?? "");
    $contactPerson = trim($_POST["contact_person"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    if ($supplierName === "") {
        $message = "Supplier name is required.";
        $messageType = "error";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO suppliers
            (supplier_name, company_name, contact_person, phone, email, address, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssss",
            $supplierName,
            $companyName,
            $contactPerson,
            $phone,
            $email,
            $address,
            $notes
        );

        if ($stmt->execute()) {
            header("Location: suppliers.php?added=1");
            exit;
        } else {
            $message = "Unable to add supplier.";
            $messageType = "error";
        }
    }
}

// =====================================
// ACTIVATE / DEACTIVATE SUPPLIER
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_supplier"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["supplier_csrf"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $supplierId = (int)($_POST["supplier_id"] ?? 0);

    if ($supplierId > 0) {

        $stmt = $conn->prepare("
            UPDATE suppliers
            SET is_active = IF(is_active = 1, 0, 1)
            WHERE id = ?
        ");

        $stmt->bind_param("i", $supplierId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: suppliers.php?status_changed=1");
            exit;
        }
    }

    $message = "Unable to change supplier status.";
    $messageType = "error";
}

// =====================================
// SUPPLIER STATISTICS
// =====================================

$statsQuery = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(is_active = 1) AS active,
        SUM(is_active = 0) AS inactive
    FROM suppliers
");

$stats = $statsQuery->fetch_assoc();

$totalSuppliers = (int)($stats["total"] ?? 0);
$activeSuppliers = (int)($stats["active"] ?? 0);
$inactiveSuppliers = (int)($stats["inactive"] ?? 0);

// =====================================
// SEARCH
// =====================================

$search = trim($_GET["search"] ?? "");

$sql = "SELECT * FROM suppliers";

if ($search !== "") {
    $sql .= "
        WHERE supplier_name LIKE ?
        OR company_name LIKE ?
        OR contact_person LIKE ?
        OR phone LIKE ?
        OR email LIKE ?
    ";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if ($search !== "") {
    $searchTerm = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
}

$stmt->execute();
$suppliers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Suppliers | CafeNest</title>

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

.btn {
    display: inline-block;
    border: none;
    text-decoration: none;
    cursor: pointer;
    padding: 10px 15px;
    border-radius: 8px;
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

.btn-green {
    background: #dcfce7;
    color: #166534;
}

.btn-red {
    background: #fee2e2;
    color: #991b1b;
}

.btn-grey {
    background: #e2e8f0;
    color: #334155;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
    border: 1px solid #e8edf4;
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
    margin: 0 0 20px;
    font-size: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    font-size: 14px;
    font-weight: 600;
}

input,
textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    outline: none;
}

input:focus,
textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
}

textarea {
    min-height: 90px;
    resize: vertical;
}

.form-submit {
    margin-top: 18px;
}

.alert {
    padding: 14px 16px;
    margin-bottom: 20px;
    border-radius: 9px;
    font-size: 14px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.search-row {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-row input {
    flex: 1;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th {
    background: #f1f5f9;
    color: #475569;
    text-align: left;
    font-size: 13px;
    padding: 14px;
    white-space: nowrap;
}

td {
    padding: 14px;
    border-bottom: 1px solid #edf1f5;
    font-size: 14px;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f8fafc;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.actions form {
    margin: 0;
}

.actions .btn {
    padding: 8px 11px;
    font-size: 12px;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

.small-text {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}

@media (max-width: 768px) {

    .container {
        width: 93%;
        margin: 20px auto;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .header h1 {
        font-size: 23px;
    }

    .search-row {
        flex-direction: column;
    }

    .search-row .btn {
        text-align: center;
    }
}
</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">
        <div>
            <h1>Suppliers</h1>
            <p>Manage your cafe suppliers and contact information.</p>
        </div>

        <a href="ingredients.php" class="btn btn-light">
            ← Back to Ingredients
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
            <div class="stat-label">Total Suppliers</div>
            <div class="stat-number blue">
                <?= $totalSuppliers ?>
            </div>
        </div>

        <div class="card">
            <div class="stat-label">Active Suppliers</div>
            <div class="stat-number green">
                <?= $activeSuppliers ?>
            </div>
        </div>

        <div class="card">
            <div class="stat-label">Inactive Suppliers</div>
            <div class="stat-number orange">
                <?= $inactiveSuppliers ?>
            </div>
        </div>

    </div>

    <!-- ADD SUPPLIER -->

    <div class="card" style="margin-bottom:25px;">

        <h2 class="section-title">Add New Supplier</h2>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label>Supplier Name *</label>
                    <input type="text"
                           name="supplier_name"
                           placeholder="Enter supplier name"
                           required>
                </div>

                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text"
                           name="company_name"
                           placeholder="Company name">
                </div>

                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text"
                           name="contact_person"
                           placeholder="Contact person name">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text"
                           name="phone"
                           placeholder="Phone number">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email"
                           name="email"
                           placeholder="Email address">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text"
                           name="address"
                           placeholder="Supplier address">
                </div>

                <div class="form-group full">
                    <label>Notes</label>
                    <textarea name="notes"
                              placeholder="Additional supplier information"></textarea>
                </div>

            </div>

            <button type="submit"
                    name="add_supplier"
                    class="btn btn-blue form-submit">
                + Add Supplier
            </button>

        </form>

    </div>

    <!-- SUPPLIER DIRECTORY -->

    <div class="card">

        <h2 class="section-title">Supplier Directory</h2>

        <!-- SEARCH -->

        <form method="GET" class="search-row">

            <input type="text"
                   name="search"
                   placeholder="Search supplier, company, contact, phone or email..."
                   value="<?= htmlspecialchars($search) ?>">

            <button type="submit" class="btn btn-blue">
                Search
            </button>

            <?php if ($search !== ""): ?>
                <a href="suppliers.php" class="btn btn-grey">
                    Clear
                </a>
            <?php endif; ?>

        </form>

        <!-- TABLE -->

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Company</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($suppliers->num_rows > 0): ?>

                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($supplier["supplier_name"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($supplier["company_name"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($supplier["contact_person"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($supplier["phone"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($supplier["email"] ?? "") ?>
                            </td>

                            <td>
                                <?php if ($supplier["is_active"]): ?>

                                    <span class="status status-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status status-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= date("d M Y", strtotime($supplier["created_at"])) ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <!-- EDIT -->

                                    <a href="edit_supplier.php?id=<?= (int)$supplier["id"] ?>"
                                       class="btn btn-light">
                                        Edit
                                    </a>

                                    <!-- ACTIVATE / DEACTIVATE -->

                                    <form method="POST"
                                          onsubmit="return confirm('Are you sure you want to change this supplier status?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= htmlspecialchars($csrfToken) ?>">

                                        <input type="hidden"
                                               name="supplier_id"
                                               value="<?= (int)$supplier["id"] ?>">

                                        <button type="submit"
                                                name="toggle_supplier"
                                                class="btn <?= $supplier["is_active"] ? "btn-red" : "btn-green" ?>">

                                            <?= $supplier["is_active"] ? "Deactivate" : "Activate" ?>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No suppliers found.
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
