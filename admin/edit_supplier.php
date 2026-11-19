
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";
$message = "";

// =====================================
// GET SUPPLIER ID
// =====================================

$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    die("Invalid supplier ID.");
}

// =====================================
// FETCH SUPPLIER
// =====================================

$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    die("Supplier not found.");
}

// =====================================
// UPDATE SUPPLIER
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_supplier"])) {

    $supplierName = trim($_POST["supplier_name"] ?? "");
    $companyName = trim($_POST["company_name"] ?? "");
    $contactPerson = trim($_POST["contact_person"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    if ($supplierName === "") {

        $error = "Supplier name is required.";

    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        try {

            $stmt = $conn->prepare("
                UPDATE suppliers
                SET
                    supplier_name = ?,
                    company_name = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    notes = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssssssi",
                $supplierName,
                $companyName,
                $contactPerson,
                $phone,
                $email,
                $address,
                $notes,
                $id
            );

            $stmt->execute();
            $stmt->close();

            header("Location: suppliers.php?updated=1");
            exit;

        } catch (mysqli_sql_exception $e) {

            $error = "Unable to update supplier. Please try again.";

        }
    }

    // Preserve entered data if validation fails
    $supplier["supplier_name"] = $supplierName;
    $supplier["company_name"] = $companyName;
    $supplier["contact_person"] = $contactPerson;
    $supplier["phone"] = $phone;
    $supplier["email"] = $email;
    $supplier["address"] = $address;
    $supplier["notes"] = $notes;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Supplier | CafeNest</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    background: #f8fafc;
}

.container {
    max-width: 950px;
    margin: auto;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
}

.header p {
    color: #64748b;
    margin-top: 8px;
}

.panel {
    background: #fff;
    padding: 25px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 7px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.full-width {
    grid-column: 1 / -1;
}

.btn {
    display: inline-block;
    padding: 11px 17px;
    border-radius: 8px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-blue {
    background: #2563eb;
    color: white;
}

.btn-light {
    background: #e2e8f0;
    color: #334155;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.message {
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 20px;
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 650px) {
    .container {
        padding: 15px;
    }

    .panel {
        padding: 17px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full-width {
        grid-column: auto;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Edit Supplier</h1>
            <p>Update supplier contact and business information.</p>
        </div>

        <a href="suppliers.php" class="btn btn-light">
            ← Back to Suppliers
        </a>
    </div>

    <?php if ($error): ?>
        <div class="message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="panel">

        <form method="POST">

            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label>Supplier Name *</label>
                    <input
                        type="text"
                        name="supplier_name"
                        maxlength="150"
                        value="<?= htmlspecialchars($supplier["supplier_name"]) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Company Name</label>
                    <input
                        type="text"
                        name="company_name"
                        maxlength="150"
                        value="<?= htmlspecialchars($supplier["company_name"] ?? "") ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Contact Person</label>
                    <input
                        type="text"
                        name="contact_person"
                        maxlength="100"
                        value="<?= htmlspecialchars($supplier["contact_person"] ?? "") ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input
                        type="tel"
                        name="phone"
                        maxlength="20"
                        value="<?= htmlspecialchars($supplier["phone"] ?? "") ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= htmlspecialchars($supplier["email"] ?? "") ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address"><?= htmlspecialchars($supplier["address"] ?? "") ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="notes"><?= htmlspecialchars($supplier["notes"] ?? "") ?></textarea>
                </div>

                <div class="full-width actions">
                    <button type="submit" name="update_supplier" class="btn btn-blue">
                        Save Changes
                    </button>

                    <a href="suppliers.php" class="btn btn-light">
                        Cancel
                    </a>
                </div>

            </div>

        </form>

    </div>

</div>

</body>
</html>
