
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CafeNest | Fresh Coffee, Fresh Moments</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<!-- NAVIGATION -->
<header class="navbar">
    <div class="nav-container">

        <a href="index.php" class="logo">
            <span class="logo-icon">☕</span>
            CafeNest<span class="logo-dot">.</span>
        </a>

        <button class="menu-toggle" id="menuToggle">☰</button>

        <nav id="navLinks">
            <a href="index.php" class="active">Home</a>
            <a href="menu.php">Menu</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <a href="track_order.php">Track Order</a>
        </nav>

        <div class="nav-buttons">
            <a href="cart.php" class="cart-btn">🛒 Cart</a>
            <a href="login.php" class="login-btn">Admin Login</a>
        </div>

    </div>
</header>


<!-- HERO SECTION -->
<section class="hero">

    <div class="hero-content">

        <p class="hero-tag">WELCOME TO CAFENEST</p>

        <h1>
            Your Daily Dose<br>
            of <span>Happiness.</span>
        </h1>

        <p class="hero-description">
            Great coffee, delicious food and beautiful moments.
            Discover your favourite place to relax and enjoy.
        </p>

        <div class="hero-buttons">
            <a href="menu.php" class="primary-btn">Explore Our Menu →</a>
            <a href="#about" class="secondary-btn">Discover More</a>
        </div>

        <div class="hero-stats">
            <div>
                <h3>100%</h3>
                <p>Fresh Ingredients</p>
            </div>
            <div>
                <h3>25+</h3>
                <p>Menu Options</p>
            </div>
            <div>
                <h3>Made</h3>
                <p>With Love</p>
            </div>
        </div>

    </div>

    <div class="hero-image">
        <img
            src="https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=1000"
            alt="Beautiful cafe interior">
    </div>

</section>


<!-- CATEGORY SECTION -->
<section class="categories">

    <div class="section-heading">
        <p class="small-heading">EXPLORE OUR COLLECTION</p>
        <h2>Something for Every Craving</h2>
        <p>Freshly prepared favourites, made just for you.</p>
    </div>

    <div class="category-grid">

        <div class="category-card">
            <img src="https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=500" alt="Coffee">
            <h3>Hot Coffee</h3>
            <p>Rich and freshly brewed</p>
        </div>

        <div class="category-card">
            <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=500" alt="Cold coffee">
            <h3>Cold Beverages</h3>
            <p>Cool and refreshing</p>
        </div>

        <div class="category-card">
            <img src="https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=500" alt="Bakery">
            <h3>Bakery</h3>
            <p>Freshly baked goodness</p>
        </div>

        <div class="category-card">
            <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=500" alt="Food">
            <h3>Delicious Food</h3>
            <p>Made to satisfy your cravings</p>
        </div>

    </div>

</section>


<!-- POPULAR ITEMS -->
<section class="popular">

    <div class="section-heading">
        <p class="small-heading">CUSTOMER FAVOURITES</p>
        <h2>Popular at CafeNest</h2>
        <p>A little taste of what makes us special.</p>
    </div>

    <div class="product-grid">

        <div class="product-card">
            <img src="https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=600" alt="Cappuccino">
            <div class="product-info">
                <span class="product-category">COFFEE</span>
                <h3>Cappuccino</h3>
                <p>Smooth espresso with creamy milk foam.</p>
                <div class="product-bottom">
                    <strong>₹149</strong>
                    <a href="menu.php">View Menu →</a>
                </div>
            </div>
        </div>

        <div class="product-card">
            <img src="https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600" alt="Chocolate milkshake">
            <div class="product-info">
                <span class="product-category">BEVERAGE</span>
                <h3>Chocolate Shake</h3>
                <p>A deliciously rich chocolate treat.</p>
                <div class="product-bottom">
                    <strong>₹179</strong>
                    <a href="menu.php">View Menu →</a>
                </div>
            </div>
        </div>

        <div class="product-card">
            <img src="https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600" alt="Croissant">
            <div class="product-info">
                <span class="product-category">BAKERY</span>
                <h3>Butter Croissant</h3>
                <p>Flaky, buttery and freshly baked.</p>
                <div class="product-bottom">
                    <strong>₹99</strong>
                    <a href="menu.php">View Menu →</a>
                </div>
            </div>
        </div>

    </div>

    <div class="menu-center">
        <a href="menu.php" class="primary-btn">View Full Menu →</a>
    </div>

</section>


<!-- ABOUT SECTION -->
<section class="about" id="about">

    <div class="about-image">
        <img src="https://images.unsplash.com/photo-1442512595331-e89e73853f31?w=900" alt="Coffee being prepared">
    </div>

    <div class="about-content">

        <p class="small-heading">OUR STORY</p>

        <h2>More Than Just a Cup of Coffee.</h2>

        <p>
            At CafeNest, we believe every cup tells a story.
            We bring together fresh ingredients, delicious flavours
            and a welcoming atmosphere to create memorable experiences.
        </p>

        <p>
            Whether you are catching up with friends, working
            or simply enjoying a quiet moment, CafeNest is your
            perfect little escape.
        </p>

        <a href="menu.php" class="primary-btn">Explore Our Menu →</a>

    </div>

</section>


<!-- CONTACT SECTION -->
<section class="contact" id="contact">

    <p class="small-heading">COME SAY HELLO</p>

    <h2>Your Favourite Coffee Spot Awaits.</h2>

    <p>Visit CafeNest and make your everyday moments special.</p>

    <a href="menu.php" class="primary-btn">Order Something Delicious →</a>

</section>


<!-- FOOTER -->
<footer class="footer">

    <div class="footer-content">

        <div>
            <a href="index.php" class="logo footer-logo">☕ CafeNest.</a>
            <p>Brewing happiness, one cup at a time.</p>
        </div>

        <div>
            <h3>Quick Links</h3>
            <a href="index.php">Home</a>
            <a href="menu.php">Menu</a>
            <a href="#about">About Us</a>
        </div>

        <div>
            <h3>Contact</h3>
            <p>Email: hello@cafenest.com</p>
            <p>Phone: +91 98765 43210</p>
        </div>

    </div>

    <div class="copyright">
        © 2026 CafeNest. All Rights Reserved.
    </div>

</footer>

<script src="assets/js/script.js"></script>

</body>
</html>
