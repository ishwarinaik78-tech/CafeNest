
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ==========================================
// VALIDATE PURCHASE ORDER ID
// ==========================================

$poId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$poId || $poId < 1) {
    die("Invalid purchase order ID.");
}

// ==========================================
// FETCH PURCHASE ORDER + SUPPLIER DETAILS
// ==========================================

$stmt = $conn->prepare("
    SELECT
        po.id,
        po.po_number,
        po.supplier_id,
        po.status,
        po.total_amount,
        po.notes,
        po.created_at,
        po.updated_at,
        s.supplier_name,
        s.company_name,
        s.contact_person,
        s.phone,
        s.email,
        s.address
    FROM purchase_orders po
    INNER JOIN suppliers s
        ON s.id = po.supplier_id
    WHERE po.id = ?
");

$stmt->bind_param("i", $poId);
$stmt->execute();

$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    die("Purchase order not found.");
}

// ==========================================
// FETCH PURCHASE ORDER ITEMS
// ==========================================

$stmt = $conn->prepare("
    SELECT
        poi.ingredient_id,
        poi.quantity,
        poi.unit_cost,
        poi.subtotal,
        i.name AS ingredient_name,
        i.unit
    FROM purchase_order_items poi
    INNER JOIN ingredients i
        ON i.id = poi.ingredient_id
    WHERE poi.purchase_order_id = ?
    ORDER BY poi.id ASC
");

$stmt->bind_param("i", $poId);
$stmt->execute();

$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ==========================================
// HELPER
// ==========================================

function vp_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function vp_money($value) {
    return number_format((float)$value, 2);
}

$poDate = !empty($po["created_at"])
    ? date("d M Y, h:i A", strtotime($po["created_at"]))
    : "-";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= vp_h($po["po_number"]) ?> | CafeNest Purchase Order
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f2f4f7;
    color: #252525;
    font-family: Arial, Helvetica, sans-serif;
}

.page {
    max-width: 950px;
    margin: 35px auto;
    padding: 35px;
    background: #ffffff;
    box-shadow: 0 4px 18px rgba(0,0,0,.08);
    border-radius: 12px;
}

.top-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
}

.btn {
    border: none;
    padding: 11px 17px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    display: inline-block;
}

.back-btn {
    background: #ededed;
    color: #333;
}

.print-btn {
    background: #6c3fc5;
    color: #fff;
}

.company-header {
    border-bottom: 3px solid #6c3fc5;
    padding-bottom: 22px;
    margin-bottom: 25px;
}

.brand {
    font-size: 32px;
    font-weight: bold;
    color: #6c3fc5;
    letter-spacing: 1px;
}

.brand-subtitle {
    margin-top: 6px;
    color: #777;
    font-size: 13px;
}

.document-title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 25px;
}

.document-title h1 {
    margin: 0;
    font-size: 26px;
    letter-spacing: 1px;
}

.po-meta {
    text-align: right;
}

.po-number {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
}

.status {
    display: inline-block;
    padding: 7px 13px;
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

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.details-box {
    border: 1px solid #e5e5e5;
    border-radius: 9px;
    padding: 18px;
}

.details-box h3 {
    margin: 0 0 14px;
    font-size: 14px;
    text-transform: uppercase;
    color: #6c3fc5;
    letter-spacing: .5px;
}

.details-box p {
    margin: 7px 0;
    font-size: 14px;
    line-height: 1.5;
}

.label {
    color: #777;
}

.items-title {
    margin: 25px 0 12px;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #6c3fc5;
    color: white;
    padding: 12px 10px;
    font-size: 13px;
    text-align: left;
}

.items-table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e9e9e9;
    font-size: 14px;
}

.items-table tr:nth-child(even) td {
    background: #faf9fc;
}

.text-right {
    text-align: right !important;
}

.text-center {
    text-align: center !important;
}

.total-section {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.total-box {
    width: 320px;
    border: 1px solid #e5e5e5;
    border-radius: 9px;
    overflow: hidden;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 13px 16px;
    font-size: 14px;
}

.grand-total {
    background: #6c3fc5;
    color: white;
    font-size: 19px;
    font-weight: bold;
}

.notes {
    margin-top: 30px;
    padding: 18px;
    border: 1px solid #e5e5e5;
    border-radius: 9px;
}

.notes h3 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #6c3fc5;
    text-transform: uppercase;
}

.notes p {
    margin: 0;
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.6;
}

.footer {
    margin-top: 40px;
    border-top: 1px solid #ddd;
    padding-top: 20px;
    text-align: center;
    color: #777;
    font-size: 12px;
    line-height: 1.7;
}

.signature-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    margin-top: 55px;
}

.signature {
    padding-top: 10px;
    border-top: 1px solid #555;
    text-align: center;
    color: #555;
    font-size: 13px;
}

@media(max-width: 650px) {

    .page {
        margin: 10px;
        padding: 18px;
    }

    .document-title {
        flex-direction: column;
    }

    .po-meta {
        text-align: left;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }

    .items-table {
        min-width: 650px;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .total-box {
        width: 100%;
    }

    .signature-section {
        gap: 25px;
    }
}

@media print {

    @page {
        size: A4;
        margin: 15mm;
    }

    body {
        background: white;
    }

    .page {
        max-width: none;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
    }

    .top-actions {
        display: none !important;
    }

    .items-table {
        min-width: 0;
    }

    .items-table th {
        background: #6c3fc5 !important;
        color: white !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .grand-total {
        background: #6c3fc5 !important;
        color: white !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .status {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .details-box,
    .notes,
    .total-box {
        break-inside: avoid;
    }

    .items-table tr {
        break-inside: avoid;
    }
}

</style>

</head>

<body>

<div class="page">

    <!-- ACTION BUTTONS -->

    <div class="top-actions">

        <a href="purchase_orders.php"
           class="btn back-btn">
            ← Back to Purchase Orders
        </a>

        <button onclick="window.print()"
                class="btn print-btn">
            Print / Save PDF
        </button>

    </div>

    <!-- COMPANY HEADER -->

    <div class="company-header">

        <div class="brand">
            CafeNest
        </div>

        <div class="brand-subtitle">
            Cafe Management & Inventory System
        </div>

    </div>

    <!-- PO TITLE -->

    <div class="document-title">

        <div>
            <h1>PURCHASE ORDER</h1>
            <p class="label">
                Supplier procurement document
            </p>
        </div>

        <div class="po-meta">

            <div class="po-number">
                <?= vp_h($po["po_number"]) ?>
            </div>

            <div style="margin-bottom:10px;">
                Date: <?= vp_h($poDate) ?>
            </div>

            <span class="status <?= vp_h($po["status"]) ?>">
                <?= vp_h($po["status"]) ?>
            </span>

        </div>

    </div>

    <!-- SUPPLIER DETAILS -->

    <div class="details-grid">

        <div class="details-box">

            <h3>Supplier Details</h3>

            <p>
                <strong>
                    <?= vp_h($po["supplier_name"]) ?>
                </strong>
            </p>

            <?php if (!empty($po["company_name"])): ?>
                <p>
                    <?= vp_h($po["company_name"]) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($po["contact_person"])): ?>
                <p>
                    <span class="label">Contact:</span>
                    <?= vp_h($po["contact_person"]) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($po["phone"])): ?>
                <p>
                    <span class="label">Phone:</span>
                    <?= vp_h($po["phone"]) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($po["email"])): ?>
                <p>
                    <span class="label">Email:</span>
                    <?= vp_h($po["email"]) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($po["address"])): ?>
                <p>
                    <span class="label">Address:</span><br>
                    <?= nl2br(vp_h($po["address"])) ?>
                </p>
            <?php endif; ?>

        </div>

        <div class="details-box">

            <h3>Order Information</h3>

            <p>
                <span class="label">PO Number:</span>
                <strong><?= vp_h($po["po_number"]) ?></strong>
            </p>

            <p>
                <span class="label">Created:</span>
                <?= vp_h($poDate) ?>
            </p>

            <p>
                <span class="label">Status:</span>
                <?= vp_h($po["status"]) ?>
            </p>

            <p>
                <span class="label">Items:</span>
                <?= count($items) ?>
            </p>

        </div>

    </div>

    <!-- ORDER ITEMS -->

    <h3 class="items-title">
        Ordered Items
    </h3>

    <div class="table-wrapper">

        <table class="items-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Ingredient</th>
                    <th>Unit</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($items)): ?>

                    <tr>
                        <td colspan="6" class="text-center">
                            No items found in this purchase order.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($items as $index => $item): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= vp_h($item["ingredient_name"]) ?>
                            </td>

                            <td>
                                <?= vp_h($item["unit"]) ?>
                            </td>

                            <td class="text-right">
                                <?= number_format((float)$item["quantity"], 3) ?>
                            </td>

                            <td class="text-right">
                                ₹<?= vp_money($item["unit_cost"]) ?>
                            </td>

                            <td class="text-right">
                                ₹<?= vp_money($item["subtotal"]) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- TOTAL -->

    <div class="total-section">

        <div class="total-box">

            <div class="total-row">
                <span>Items</span>
                <span><?= count($items) ?></span>
            </div>

            <div class="total-row grand-total">
                <span>Grand Total</span>
                <span>₹<?= vp_money($po["total_amount"]) ?></span>
            </div>

        </div>

    </div>

    <!-- NOTES -->

    <?php if (!empty($po["notes"])): ?>

        <div class="notes">

            <h3>Order Notes</h3>

            <p><?= vp_h($po["notes"]) ?></p>

        </div>

    <?php endif; ?>

    <!-- SIGNATURES -->

    <div class="signature-section">

        <div class="signature">
            Authorized By — CafeNest
        </div>

        <div class="signature">
            Supplier Acknowledgement
        </div>

    </div>

    <!-- FOOTER -->

    <div class="footer">

        <strong>CafeNest</strong><br>

        Thank you for your business and cooperation.<br>

        This is a system-generated purchase order.

    </div>

</div>

</body>
</html>
