document.addEventListener("DOMContentLoaded", function () {
  // Menu hamburger
  const menuToggle = document.getElementById("menu-toggle");
  const mainNav = document.getElementById("main-nav");

  if (menuToggle && mainNav) {
    menuToggle.addEventListener("click", function () {
      const expanded = menuToggle.getAttribute("aria-expanded") === "true";
      menuToggle.setAttribute("aria-expanded", !expanded);
      mainNav.classList.toggle("active");
    });
  }

  // Dropdowns (pour le menu principal)
  document.querySelectorAll(".dropdown-toggle").forEach(function (toggle) {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      const dropdownMenu = this.nextElementSibling;
      if (dropdownMenu && dropdownMenu.classList.contains("dropdown-menu")) {
        dropdownMenu.classList.toggle("active");
      }
    });
  });

  // Fermer le menu mobile si on clique en dehors
  document.addEventListener("click", function (e) {
    const isMenu = e.target.closest("#main-nav, #menu-toggle");
    if (!isMenu && mainNav && mainNav.classList.contains("active")) {
      mainNav.classList.remove("active");
      menuToggle.setAttribute("aria-expanded", "false");
    }
  });

  // Fermer les dropdowns si on clique ailleurs
  document.addEventListener("click", function (e) {
    document.querySelectorAll(".dropdown-menu.active").forEach(function (menu) {
      if (!e.target.closest(".dropdown")) {
        menu.classList.remove("active");
      }
    });
  });
});
