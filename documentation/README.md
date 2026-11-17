\# CafeNest – Cafe Management System



\## 1. Project Overview

CafeNest is a web-based cafe management system designed to simplify cafe operations. It provides customers with an online menu, shopping cart, checkout, and order tracking facilities. Administrators can manage products, monitor orders, and view sales reports.



\## 2. Technologies Used

\- Frontend: HTML, CSS, JavaScript

\- Backend: PHP

\- Database: MySQL

\- Local Server: XAMPP

\- Database Management: phpMyAdmin



\## 3. Main Modules



\### Customer Module

\- View available food and beverages.

\- Browse and search menu items.

\- Add products to the shopping cart.

\- Update quantities and remove products.

\- Place orders.

\- View order receipts.

\- Track order status.



\### Admin Module

\- Secure administrator login.

\- Dashboard with order and revenue statistics.

\- Add, edit, and manage products.

\- View customer orders.

\- Update order statuses.

\- Generate sales reports.



\## 4. Database

Database name: `cafenest\_db`



Main tables:

\- admins

\- products

\- orders

\- order\_items



\## 5. Installation Instructions

1\. Install XAMPP.

2\. Start Apache and MySQL.

3\. Copy the CafeNest folder into `C:\\xampp\_new\\htdocs\\`.

4\. Open phpMyAdmin.

5\. Create or import the `cafenest\_db` database.

6\. Configure the database connection in `config/db.php`.

7\. Open `http://localhost/CafeNest/`.



\## 6. Admin Access

Admin login:

\- Email: `admin@cafenest.com`



Use the administrator password configured during setup.



\## 7. Project Objective

The objective of CafeNest is to make cafe operations more organized by combining customer ordering, product management, order processing, and sales reporting in one web application.



\## 8. Future Enhancements

\- Online payment gateway integration.

\- Inventory and stock management.

\- Customer registration and login.

\- Email and SMS order notifications.

\- Table reservation system.

\- Advanced analytics.









# Sensitive configuration
.env
.env.*
!.env.example

# Temporary files
*.log
*.tmp
*.bak
*.old

# Editor files
.vscode/
.idea/

# Dependencies
/vendor/
/node_modules/

# Database backups
*.sql

# Compressed backups
*.zip

# System files
.DS_Store
Thumbs.db
desktop.ini

