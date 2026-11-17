<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$error = "";

if (empty($_SESSION['product_csrf'])) {
    $_SESSION['product_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? "";

    if (!hash_equals($_SESSION['product_csrf'], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $name = trim($_POST['name'] ?? "");
        $description = trim($_POST['description'] ?? "");
        $price = trim($_POST['price'] ?? "");
        $category = trim($_POST['category'] ?? "");
        $status = $_POST['status'] ?? "Available";

        if ($name === "" || $price === "" || $category === "") {
            $error = "Please fill in all required fields.";
        } elseif (!is_numeric($price) || (float)$price <= 0 || (float)$price > 99999999) {
            $error = "Enter a valid product price.";
        } elseif (!in_array($status, ["Available", "Unavailable"], true)) {
            $error = "Invalid product status.";
        } else {

            $imagePath = "";

            if (
                isset($_FILES['image']) &&
                $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $error = "Image upload failed. Please try again.";
                } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
                    $error = "Image size must be 3 MB or less.";
                } else {

                    $imageInfo = getimagesize($_FILES['image']['tmp_name']);

                    if ($imageInfo === false) {
                        $error = "Please upload a valid image.";
                    } else {

                        $mime = $imageInfo['mime'];

                        $allowedTypes = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp'
                        ];

                        if (!isset($allowedTypes[$mime])) {
                            $error = "Only JPG, PNG and WEBP images are allowed.";
                        } else {

                            $uploadDirectory = __DIR__ . "/../uploads/products/";

                            if (!is_dir($uploadDirectory)) {
                                mkdir($uploadDirectory, 0755, true);
                            }

                            $fileName = bin2hex(random_bytes(16))
                                . "." . $allowedTypes[$mime];

                            if (
                                move_uploaded_file(
                                    $_FILES['image']['tmp_name'],
                                    $uploadDirectory . $fileName
                                )
                            ) {
                                $imagePath = "uploads/products/" . $fileName;
                            } else {
                                $error = "Could not save the uploaded image.";
                            }
                        }
                    }
                }
            }

            if ($error === "") {

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO products
                    (name, description, price, category, image, status)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssdsss",
                    $name,
                    $description,
                    $price,
                    $category,
                    $imagePath,
                    $status
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: products.php?added=1");
                    exit;
                } else {
                    if ($imagePath !== "") {
                        @unlink(__DIR__ . "/../" . $imagePath);
                    }

                    $error = "Could not add the product. Please try again.";
                }
            }
        }
    }
}

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | CafeNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page">

<header class="admin-header">
    <a href="dashboard.php" class="admin-brand">CafeNest <span>Admin</span></a>
    <a href="products.php" class="admin-logout">Back to Products</a>
</header>

<main class="admin-main">

    <section class="admin-form-card">

        <h1>Add New Product</h1>
        <p>Enter the details of your new cafe menu item.</p>

        <?php if ($error !== ""): ?>
            <div class="login-error"><?php echo clean($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                   value="<?php echo clean($_SESSION['product_csrf']); ?>">

            <label for="name">Product Name *</label>
            <input type="text" id="name" name="name"
                   maxlength="150" required
                   value="<?php echo clean($_POST['name'] ?? ''); ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description"
                      rows="4"><?php echo clean($_POST['description'] ?? ''); ?></textarea>

            <label for="price">Price (₹) *</label>
            <input type="number" id="price" name="price"
                   min="0.01" max="99999999" step="0.01" required
                   value="<?php echo clean($_POST['price'] ?? ''); ?>">

            <label for="category">Category *</label>
            <select id="category" name="category" required>
                <option value="">Select category</option>

                <?php
                $categories = ["Coffee", "Beverages", "Food", "Bakery", "Desserts"];

                foreach ($categories as $item):
                ?>
                    <option value="<?php echo clean($item); ?>"
                        <?php echo (($_POST['category'] ?? '') === $item) ? 'selected' : ''; ?>>
                        <?php echo clean($item); ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <label for="image">Product Image</label>
            <input type="file" id="image" name="image"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

            <small>Optional. JPG, PNG or WEBP. Maximum 3 MB.</small>

            <label for="status">Availability</label>
            <select id="status" name="status">
                <option value="Available">Available</option>
                <option value="Unavailable">Unavailable</option>
            </select>

            <button type="submit" class="admin-primary-btn form-submit">
                Save Product
            </button>

        </form>

    </section>

</main>

</body>
</html>