
// =====================================
// MOBILE NAVIGATION
// =====================================

const menuToggle = document.getElementById("menuToggle");
const navLinks = document.getElementById("navLinks");

if (menuToggle && navLinks) {

    menuToggle.addEventListener("click", function () {
        navLinks.classList.toggle("show");
    });

    navLinks.querySelectorAll("a").forEach(function (link) {

        link.addEventListener("click", function () {
            navLinks.classList.remove("show");
        });

    });
}


// =====================================
// MENU ELEMENTS
// =====================================

const menuSearch = document.getElementById("menuSearch");

const categoryFilter = document.getElementById("categoryFilter");

const sortFilter = document.getElementById("sortFilter");

const menuGrid = document.getElementById("menuGrid");

const menuCards = document.querySelectorAll(".menu-card");

const noProducts = document.getElementById("noProducts");


// Store original product order

const originalOrder = Array.from(menuCards);


// =====================================
// SEARCH, CATEGORY AND SORTING
// =====================================

function filterProducts() {

    const searchValue = menuSearch
        ? menuSearch.value.toLowerCase().trim()
        : "";

    const categoryValue = categoryFilter
        ? categoryFilter.value.toLowerCase().trim()
        : "all";

    const sortValue = sortFilter
        ? sortFilter.value
        : "default";


    // Start with original products

    let sortedCards = [...originalOrder];


    // =====================================
    // SORT PRODUCTS
    // =====================================

    if (sortValue === "price-low") {

        sortedCards.sort(function (a, b) {

            return Number(a.dataset.price) -
                   Number(b.dataset.price);

        });

    }

    else if (sortValue === "price-high") {

        sortedCards.sort(function (a, b) {

            return Number(b.dataset.price) -
                   Number(a.dataset.price);

        });

    }

    else if (sortValue === "name-asc") {

        sortedCards.sort(function (a, b) {

            return a.dataset.name.localeCompare(b.dataset.name);

        });

    }

    else if (sortValue === "name-desc") {

        sortedCards.sort(function (a, b) {

            return b.dataset.name.localeCompare(a.dataset.name);

        });

    }


    // =====================================
    // UPDATE PRODUCT ORDER
    // =====================================

    if (menuGrid) {

        sortedCards.forEach(function (card) {

            menuGrid.appendChild(card);

        });

    }


    // =====================================
    // APPLY SEARCH AND CATEGORY FILTER
    // =====================================

    let visibleCount = 0;

    sortedCards.forEach(function (card) {

        const name = (card.dataset.name || "").toLowerCase();

        const category = (card.dataset.category || "").toLowerCase();

        const details = card.textContent.toLowerCase();


        // Search matching

        const matchesSearch =
            name.includes(searchValue) ||
            category.includes(searchValue) ||
            details.includes(searchValue);


        // Category matching

        const matchesCategory =
            categoryValue === "all" ||
            category === categoryValue;


        // Display products

        if (matchesSearch && matchesCategory) {

            card.style.display = "";

            visibleCount++;

        }

        else {

            card.style.display = "none";

        }

    });


    // =====================================
    // NO PRODUCTS MESSAGE
    // =====================================

    if (noProducts) {

        noProducts.style.display =
            visibleCount === 0 ? "block" : "none";

    }

}


// =====================================
// SEARCH EVENT
// =====================================

if (menuSearch) {

    menuSearch.addEventListener("input", filterProducts);

}


// =====================================
// CATEGORY EVENT
// =====================================

if (categoryFilter) {

    categoryFilter.addEventListener("change", filterProducts);

}


// =====================================
// SORTING EVENT
// =====================================

if (sortFilter) {

    sortFilter.addEventListener("change", filterProducts);

}


// =====================================
// INITIALIZE MENU
// =====================================

filterProducts();
