
const menuToggle = document.getElementById("menuToggle");
const navLinks = document.getElementById("navLinks");

if (menuToggle && navLinks) {

    menuToggle.addEventListener("click", function () {
        navLinks.classList.toggle("show");
    });

    const links = navLinks.querySelectorAll("a");

    links.forEach(function(link) {
        link.addEventListener("click", function() {
            navLinks.classList.remove("show");
        });
    });
}

// MENU CATEGORY FILTER

const filterButtons = document.querySelectorAll(".filter-btn");

const menuProducts = document.querySelectorAll(
    ".menu-product-card"
);

const searchInput = document.getElementById("searchInput");

const noProducts = document.getElementById("noProducts");

let selectedCategory = "all";

function filterMenu() {

    const searchText = searchInput
        ? searchInput.value.toLowerCase().trim()
        : "";

    let visibleCount = 0;

    menuProducts.forEach(function(product) {

        const category = product.dataset.category;

        const name = product.dataset.name;

        const matchesCategory =
            selectedCategory === "all" ||
            category === selectedCategory;

        const matchesSearch =
            name.includes(searchText);

        if (matchesCategory && matchesSearch) {

            product.style.display = "block";
            visibleCount++;

        } else {

            product.style.display = "none";

        }

    });

    if (noProducts) {
        noProducts.style.display =
            visibleCount === 0 ? "block" : "none";
    }
}


// CATEGORY BUTTONS

filterButtons.forEach(function(button) {

    button.addEventListener("click", function() {

        filterButtons.forEach(function(btn) {
            btn.classList.remove("active");
        });

        button.classList.add("active");

        selectedCategory = button.dataset.category;

        filterMenu();

    });

});


// SEARCH

if (searchInput) {

    searchInput.addEventListener("input", filterMenu);

}
