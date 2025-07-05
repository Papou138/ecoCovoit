document.addEventListener("DOMContentLoaded", function () {
  // Menu hamburger
  const menuToggle = document.getElementById("menu-toggle");
  const mainNav = document.getElementById("main-nav");
  let menuOverlay;

  // Créer un overlay (superposition) pour le menu mobile
  function createMenuOverlay() {
    if (window.innerWidth <= 768 && !menuOverlay) {
      menuOverlay = document.createElement("div");
      menuOverlay.classList.add("menu-overlay");
      document.body.appendChild(menuOverlay);

      menuOverlay.addEventListener("click", closeMenu);
    }
  }

  // Fonction pour ouvrir le menu mobile
  function openMenu() {
    if (mainNav) {
      mainNav.classList.add("active");
      menuToggle.setAttribute("aria-expanded", "true");
      document.body.style.overflow = "hidden"; // Empêche le scroll

      if (menuOverlay) {
        menuOverlay.classList.add("active");
      }
    }
  }

  // Fonction pour fermer le menu mobile
  function closeMenu() {
    if (mainNav) {
      mainNav.classList.remove("active");
      menuToggle.setAttribute("aria-expanded", "false");
      document.body.style.overflow = ""; // Rétablit le scroll

      if (menuOverlay) {
        menuOverlay.classList.remove("active");
      }

      // Fermer tous les dropdowns ouverts
      document.querySelectorAll(".dropdown-menu.active").forEach((menu) => {
        menu.classList.remove("active");
      });
      document.querySelectorAll(".dropdown.open").forEach((dropdown) => {
        dropdown.classList.remove("open");
      });
    }
  }

  // Toggle du menu mobile
  if (menuToggle && mainNav) {
    menuToggle.addEventListener("click", function () {
      const expanded = menuToggle.getAttribute("aria-expanded") === "true";
      if (expanded) {
        closeMenu();
      } else {
        openMenu();
      }
    });
  }

  // Fermer le menu si on clique sur l'overlay du menu
  if (mainNav) {
    mainNav.addEventListener("click", function (e) {
      // Fermer SEULEMENT si on clique sur l’arrière plan du menu (pas sur les liens)
      if (e.target === mainNav) {
        closeMenu();
      }
    });
  }

  // Dropdowns (pour le menu principal)
  document.querySelectorAll(".dropdown-toggle").forEach(function (toggle) {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      const dropdown = this.closest(".dropdown");
      const dropdownMenu = this.nextElementSibling;

      if (dropdownMenu && dropdownMenu.classList.contains("dropdown-menu")) {
        // Sur mobile, utiliser la classe 'open' pour un meilleur contrôle
        if (window.innerWidth <= 768) {
          dropdown.classList.toggle("open");
        } else {
          dropdownMenu.classList.toggle("active");
        }
      }
    });
  });

  // Fermer le menu mobile si on clique en dehors
  document.addEventListener("click", function (e) {
    const isMenu = e.target.closest("#main-nav, #menu-toggle");
    if (!isMenu && mainNav && mainNav.classList.contains("active")) {
      closeMenu();
    }
  });

  // Fermer les dropdowns desktop si on clique ailleurs
  document.addEventListener("click", function (e) {
    if (window.innerWidth > 768) {
      document.querySelectorAll(".dropdown-menu.active").forEach(function (menu) {
      if (!e.target.closest(".dropdown")) {
        menu.classList.remove("active");
      }
    });
  }
});

// Gestion du redimensionnement de la fenêtre
window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      // Nettoyer l'état du menu mobile
      closeMenu(); // Ferme le menu mobile si on passe en desktop
      if (menuOverlay) {
        menuOverlay.remove(); // Supprime l'overlay
        menuOverlay = null;
      }
    } else {
      // Créer l'overlay si nécessaire
      createMenuOverlay();
    }
  });

// Gestion des touches du clavier
document.addEventListener("keydown", function (e) {
  if(e.key === "Escape" && mainNav && mainNav.classList.contains("active")) {
      closeMenu();
      menuToggle.focus(); // Revenir au bouton de menu
      }
    });

    // Piégeage du focus dans le menu mobile
    if (mainNav) {
      const focusableElements = mainNav.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');

      if (focusableElements.length > 0) {
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        mainNav.addEventListener("keydown", function (e) {
          if (e.key === "Tab" && mainNav.classList.contains("active")) {
            if (e.shiftKey && document.activeElement === firstElement) {
              e.preventDefault();
              lastElement.focus(); // Boucle vers le dernier élément
            } else if (!e.shiftKey && document.activeElement === lastElement) {
              e.preventDefault();
              firstElement.focus(); // Boucle vers le premier élément
            }
          }
        });
      }
    }

    // Initialiser l'overlay sur mobile
    createMenuOverlay();
  });
