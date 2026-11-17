<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid product ID.");
}

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    die("Product not found.");
}

if (empty($_SESSION['product_csrf'])) {
    $_SESSION['product_csrf'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? "";

    if (!hash_equals($_SESSION['product_csrf'], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $name = trim($_POST['name'] ?? "");
        $description = trim($_POST['description'] ?? "");
        $price = trim($_POST['price'] ?? "");
        $category = trim($_POST['category'] ?? "");
        $status = $_POST['status'] ?? "";

        if ($name === "" || $price === "" || $category === "") {
            $error = "Please fill in all required fields.";
        } elseif (!is_numeric($price) || (float)$price <= 0 || (float)$price > 99999999) {
            $error = "Enter a valid product price.";
        } elseif (!in_array($status, ["Available", "Unavailable"], true)) {
            $error = "Invalid product status.";
        } else {

            $imagePath = $product['image'];
            $newImageUploaded = false;

            if (
                isset($_FILES['image']) &&
                $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $error = "Image upload failed.";
                } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
                    $error = "Image must be 3 MB or less.";
                } else {

                    $imageInfo = getimagesize($_FILES['image']['tmp_name']);

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (
                        $imageInfo === false ||
                        !isset($allowedTypes[$imageInfo['mime']])
                    ) {
                        $error = "Only valid JPG, PNG and WEBP images are allowed.";
                    } else {

                        $uploadDirectory = __DIR__ . "/../uploads/products/";

                        if (!is_dir($uploadDirectory)) {
                            mkdir($uploadDirectory, 0755, true);
                        }

                        $fileName = bin2hex(random_bytes(16))
                            . "." . $allowedTypes[$imageInfo['mime']];

                        if (
                            move_uploaded_file(
                                $_FILES['image']['tmp_name'],
                                $uploadDirectory . $fileName
                            )
                        ) {
                            $imagePath = "uploads/products/" . $fileName;
                            $newImageUploaded = true;
                        } else {
                            $error = "Could not save the uploaded image.";
                        }
                    }
                }
            }

            if ($error === "") {

                $update = mysqli_prepare(
                    $conn,
                    "UPDATE products
                     SET name = ?, description = ?, price = ?,
                         category = ?, image = ?, status = ?
                     WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "ssdsssi",
                    $name,
                    $description,
                    $price,
                    $category,
                    $imagePath,
                    $status,
                    $id
                );

                if (mysqli_stmt_execute($update)) {

                    // Remove the previous image only after a successful update.
                    if (
                        $newImageUploaded &&
                        strpos($product['image'], 'uploads/products/') === 0
                    ) {
                        $oldFile = __DIR__ . "/../" . $product['image'];

                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }

                    header("Location: products.php?updated=1");
                    exit;

                } else {

                    if ($newImageUploaded) {
                        @unlink(__DIR__ . "/../" . $imagePath);
                    }

                    $error = "Could not update the product.";
                }
            }
        }
    }
}

$displayImage = $product['image'];

if (
    $displayImage !== "" &&
    strpos($displayImage, 'http://') !== 0 &&
    strpos($displayImage, 'https://') !== 0
) {
    $displayImage = "../" . $displayImage;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | CafeNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page">

<header class="admin-header">
    <a href="dashboard.php" class="admin-brand">CafeNest <span>Admin</span></a>
    <a href="products.php" class="admin-logout">Back to Products</a>
</header>

<main class="admin-main">

    <section class="admin-form-card">

        <h1>Edit Product</h1>
        <p>Update the details of this menu item.</p>

        <?php if ($error !== ""): ?>
            <div class="login-error"><?php echo clean($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                   value="<?php echo clean($_SESSION['product_csrf']); ?>">

            <label for="name">Product Name *</label>
            <input type="text" id="name" name="name" maxlength="150" required
                   value="<?php echo clean($_POST['name'] ?? $product['name']); ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description"
                      rows="4"><?php echo clean($_POST['description'] ?? $product['description']); ?></textarea>

            <label for="price">Price (₹) *</label>
            <input type="number" id="price" name="price"
                   min="0.01" max="99999999" step="0.01" required
                   value="<?php echo clean($_POST['price'] ?? $product['price']); ?>">

            <label for="category">Category *</label>
            <select id="category" name="category" required>

                <?php
                $categories = ["Coffee", "Beverages", "Food", "Bakery", "Desserts"];
                $selectedCategory = $_POST['category'] ?? $product['category'];

                foreach ($categories as $item):
                ?>
                    <option value="<?php echo clean($item); ?>"
                        <?php echo ($selectedCategory === $item) ? 'selected' : ''; ?>>
                        <?php echo clean($item); ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <label>Current Image</label>

            <?php if ($displayImage !== ""): ?>
                <img src="<?php echo clean($displayImage); ?>"
                     alt="Current product image"
                     class="edit-product-preview">
            <?php else: ?>
                <p>No image uploaded.</p>
            <?php endif; ?>

            <label for="image">Replace Image (optional)</label>
            <input type="file" id="image" name="image"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

            <small>JPG, PNG or WEBP. Maximum 3 MB.</small>

            <label for="status">Availability</label>
            <select id="status" name="status">
                <option value="Available"
                    <?php echo $product['status'] === 'Available' ? 'selected' : ''; ?>>
                    Available
                </option>

                <option value="Unavailable"
                    <?php echo $product['status'] === 'Unavailable' ? 'selected' : ''; ?>>
                    Unavailable
                </option>
            </select>

            <button type="submit" class="admin-primary-btn form-submit">
                Update Product
            </button>

        </form>

    </section>

</main>

</body>
</html>