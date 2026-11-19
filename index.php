<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CafeNest — fresh coffee, bakes and good food. Order online and track it live.">
    <title>CafeNest | Fresh Coffee, Fresh Moments</title>

    <!-- Fonts (Fraunces + Figtree) are loaded from style.css -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<!-- NAVIGATION -->
<header class="navbar">
    <div class="nav-container">

        <a href="index.php" class="logo">CafeNest<span class="logo-dot">.</span></a>

        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation">☰</button>

        <nav id="navLinks">
            <a href="index.php" class="active">Home</a>
            <a href="menu.php">Menu</a>
            <a href="#about">About</a>
            <a href="#visit">Visit</a>
            <a href="track_order.php">Track order</a>
        </nav>

        <div class="nav-buttons">
            <a href="cart.php" class="cart-btn">🛒 Cart</a>
            <a href="login.php" class="login-btn">Admin login</a>
        </div>

    </div>
</header>


<!-- HERO -->
<section class="hero">

    <div class="hero-content">

        <p class="hero-tag">Welcome to CafeNest</p>

        <h1>
            Your daily dose<br>
            of <span>happiness.</span>
        </h1>

        <p class="hero-description">
            Great coffee, fresh food and a table with your name on it.
            Pull up a chair and stay a while.
        </p>

        <div class="hero-buttons">
            <a href="menu.php" class="primary-btn">Explore our menu</a>
            <a href="#about" class="secondary-btn">Our story</a>
        </div>

        <div class="hero-stats">
            <div>
                <h3>100%</h3>
                <p>Fresh ingredients</p>
            </div>
            <div>
                <h3>25+</h3>
                <p>Menu options</p>
            </div>
            <div>
                <h3>Made</h3>
                <p>With love</p>
            </div>
        </div>

    </div>

    <div class="hero-image">
        <img
            src="https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=1000"
            alt="Warm cafe interior with wooden tables">

        <div class="hero-note">
            <div class="hero-note-dot">☕</div>
            <div>
                <strong>Cappuccino · ₹149</strong>
                <span>Today's favourite</span>
            </div>
        </div>
    </div>

</section>


<!-- INFO STRIP
     TODO: replace the placeholder hours / location with your real details. -->
<div class="info-strip">
    <div class="info-item">
        <div class="info-icon">🕗</div>
        <div>
            <strong>Open daily</strong>
            <span>8:00 am – 10:00 pm</span>
        </div>
    </div>
    <div class="info-item">
        <div class="info-icon">📍</div>
        <div>
            <strong>Find us</strong>
            <span>Your street, Your city</span>
        </div>
    </div>
    <div class="info-item">
        <div class="info-icon">🧾</div>
        <div>
            <strong>Order online</strong>
            <span>Track it from kitchen to table</span>
        </div>
    </div>
</div>


<!-- CATEGORIES -->
<section class="categories">

    <div class="section-heading">
        <h2>Something for every craving</h2>
        <p>Freshly prepared favourites, made just for you.</p>
    </div>

    <div class="category-grid">

        <a href="menu.php" class="category-card">
            <img src="https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=700" alt="Hot coffee">
            <h3>Hot coffee</h3>
            <p>Rich and freshly brewed</p>
        </a>

        <a href="menu.php" class="category-card">
            <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=700" alt="Cold beverages">
            <h3>Cold beverages</h3>
            <p>Cool and refreshing</p>
        </a>

        <a href="menu.php" class="category-card">
            <img src="https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=700" alt="Bakery">
            <h3>Bakery</h3>
            <p>Freshly baked goodness</p>
        </a>

        <a href="menu.php" class="category-card">
            <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=700" alt="Food">
            <h3>Food</h3>
            <p>Made to satisfy</p>
        </a>

    </div>

</section>


<!-- POPULAR -->
<section class="popular">

    <div class="section-heading">
        <h2>Popular at CafeNest</h2>
        <p>A little taste of what makes us special.</p>
    </div>

    <div class="fav-grid">

        <article class="fav-card fav-large">
            <div class="fav-media">
                <img src="https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=1000" alt="Cappuccino">
            </div>
            <div class="product-info">
                <span class="product-category">Coffee</span>
                <h3>Cappuccino</h3>
                <p>Smooth espresso with creamy milk foam.</p>
                <div class="product-bottom">
                    <strong>₹149</strong>
                    <a href="menu.php">View menu →</a>
                </div>
            </div>
        </article>

        <article class="fav-card fav-small">
            <div class="fav-media">
                <img src="https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=700" alt="Chocolate milkshake">
            </div>
            <div class="product-info">
                <span class="product-category">Beverage</span>
                <h3>Chocolate shake</h3>
                <p>A deliciously rich chocolate treat.</p>
                <div class="product-bottom">
                    <strong>₹179</strong>
                    <a href="menu.php">View menu →</a>
                </div>
            </div>
        </article>

        <article class="fav-card fav-small">
            <div class="fav-media">
                <img src="https://images.unsplash.com/photo-1509440159596-0249088772ff?w=700" alt="Butter croissant">
            </div>
            <div class="product-info">
                <span class="product-category">Bakery</span>
                <h3>Butter croissant</h3>
                <p>Flaky, buttery and freshly baked.</p>
                <div class="product-bottom">
                    <strong>₹99</strong>
                    <a href="menu.php">View menu →</a>
                </div>
            </div>
        </article>

    </div>

    <div class="menu-center">
        <a href="menu.php" class="primary-btn">View full menu</a>
    </div>

</section>


<!-- HOW IT WORKS -->
<section class="how">

    <div class="section-heading">
        <h2>Order in three easy steps</h2>
        <p>No queue, no guesswork.</p>
    </div>

    <ol class="steps">
        <li>
            <h3>Choose your favourites</h3>
            <p>Browse the menu, search or filter by category and add items to your cart.</p>
        </li>
        <li>
            <h3>Check out in a minute</h3>
            <p>Enter your name and phone number, review your order and place it.</p>
        </li>
        <li>
            <h3>Track it live</h3>
            <p>Use your order ID and phone number to follow it from pending to ready.</p>
        </li>
    </ol>

</section>


<!-- ABOUT -->
<section class="about" id="about">

    <div class="about-image">
        <img src="https://images.unsplash.com/photo-1442512595331-e89e73853f31?w=900" alt="Coffee being prepared">
    </div>

    <div class="about-content">

        <h2>More than just a cup of coffee.</h2>

        <p>
            At CafeNest, we believe every cup tells a story.
            We bring together fresh ingredients, honest flavours
            and a welcoming room to create moments worth remembering.
        </p>

        <p>
            Catching up with friends, getting some work done or
            simply enjoying a quiet moment, CafeNest is your
            perfect little escape.
        </p>

        <a href="menu.php" class="primary-btn">Explore our menu</a>

    </div>

</section>


<!-- VISIT
     TODO: replace the placeholder address and hours with your real details. -->
<section class="visit" id="visit">

    <div class="visit-grid">

        <div>
            <h2>Come say hello.</h2>
            <p class="visit-lead">
                Drop in for a coffee, or order ahead and walk straight to your table.
            </p>
            <address class="visit-address">
                <strong>CafeNest</strong>
                <span>Your street address</span>
                <span>Your city, State</span>
                <span>+91 98765 43210</span>
            </address>
            <a href="menu.php" class="primary-btn">Order ahead</a>
        </div>

        <div class="hours" aria-label="Opening hours">
            <div class="hours-row"><span>Monday – Friday</span><span>8:00 am – 10:00 pm</span></div>
            <div class="hours-row"><span>Saturday</span><span>8:00 am – 11:00 pm</span></div>
            <div class="hours-row"><span>Sunday</span><span>9:00 am – 10:00 pm</span></div>
        </div>

    </div>

</section>


<!-- CLOSING BAND -->
<section class="contact" id="contact">

    <h2>Your favourite coffee spot awaits.</h2>

    <p>Make your everyday moments a little more special.</p>

    <a href="menu.php" class="primary-btn">Order something delicious</a>

</section>


<!-- FOOTER -->
<footer class="footer">

    <div class="footer-content">

        <div>
            <a href="index.php" class="logo footer-logo">CafeNest<span class="logo-dot">.</span></a>
            <p>Brewing happiness, one cup at a time.</p>
        </div>

        <div>
            <h3>Quick links</h3>
            <a href="index.php">Home</a>
            <a href="menu.php">Menu</a>
            <a href="track_order.php">Track order</a>
        </div>

        <div>
            <h3>Contact</h3>
            <p>hello@cafenest.com</p>
            <p>+91 98765 43210</p>
        </div>

    </div>

    <div class="copyright">
        © 2026 CafeNest. All rights reserved.
    </div>

</footer>

<script src="assets/js/script.js"></script>

</body>
</html>
