# CafeNest – Cafe Management System ☕

A web-based Cafe Management System developed using PHP, MySQL, HTML, CSS and JavaScript.

## About the Project

CafeNest is a web-based cafe management application designed to simplify daily cafe operations. It provides a customer-facing website for browsing menus and placing orders, along with an administrator dashboard for managing products, orders, sales and inventory.

The project includes inventory management tools to help manage ingredients, suppliers, stock movements, purchase orders and inventory reports.

## Features

### Customer Module
- Browse available food and beverages.
- Search and filter menu items.
- Add products to cart.
- Update quantities and remove cart items.
- Place orders.
- View order receipts.
- Track order status.

### Admin Module
- Secure admin login.
- Dashboard with order and sales statistics.
- Add, view, edit and delete products.
- Manage customer orders.
- Update order status.
- View sales reports.

### Inventory Management
- Central Inventory Management Center.
- Manage ingredients and suppliers.
- Monitor stock levels.
- Low-stock alerts.
- Restock ingredients.
- Record stock adjustments.
- Track inventory movement history.
- Manage purchase orders.
- Receive purchase orders.
- Track purchase history.
- Recipe management.
- Wastage tracking.
- Stock reconciliation.
- Supplier performance monitoring.
- Supplier restock planning.
- Inventory monthly analytics.
- Inventory reports and export.
- Inventory activity log.
- Inventory integrity audit.
- Smart restocking and restock recommendations.

## Technologies Used

- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL
- Local Server: XAMPP
- Version Control: Git and GitHub

## Installation and Setup

1. Install XAMPP.
2. Copy the CafeNest folder into `htdocs`.
3. Start Apache and MySQL.
4. Create a MySQL database named `cafenest_db`.
5. Import your database SQL backup.
6. Configure `config/db.php` with your database credentials.
7. Open the following URL in your browser:

   `http://localhost/CafeNest/`

### Admin Access

Open the login page:

`http://localhost/CafeNest/login.php`

Log in using your configured admin account to access the administration dashboard and management modules.

## Project Structure

- `admin/` – Admin dashboard and management modules.
- `assets/` – CSS, JavaScript and images.
- `config/` – Database configuration.
- `includes/` – Shared PHP files and authentication.
- `uploads/` – Uploaded product images.
- `index.php` – Customer homepage.
- `menu.php` – Menu page.
- `cart.php` – Shopping cart.
- `checkout.php` – Checkout process.
- `order_success.php` – Order confirmation.
- `track_order.php` – Order tracking.
- `login.php` – Admin login.

## Payment Method

The current system supports Cash on Delivery / Pay at Cafe. Online payment integration is not included.

## Project Status

CafeNest includes customer-facing ordering functionality, administrator management tools, and inventory management features.

The project is developed and tested in a local XAMPP environment. Further testing, refinements and enhancements may continue.

## Author

Developed by **Ishwari D Naik**

GitHub: [ishwarinaik78-tech](https://github.com/ishwarinaik78-tech)