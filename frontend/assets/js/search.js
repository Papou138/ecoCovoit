let trajetsGlobal = []; // Variable globale pour stocker les trajets
const selectTri = document.getElementById("tri-option");
const selectNote = document.getElementById("filtre-note");
const resultats = document.getElementById("resultats");
const formRecherche = document.getElementById("formRecherche");

// Gestion des filtres avancés - Doit être exécuté immédiatement
(function() {
  const toggleButton = document.getElementById('toggle-filters');
  const filtersContent = document.getElementById('filters-content');

  if (toggleButton && filtersContent) {
    toggleButton.addEventListener('click', function () {
      const isActive = filtersContent.classList.contains('active');

      if (isActive) {
        filtersContent.classList.remove('active');
        toggleButton.classList.remove('active');
        toggleButton.setAttribute('aria-expanded', 'false');
      } else {
        filtersContent.classList.add('active');
        toggleButton.classList.add('active');
        toggleButton.setAttribute('aria-expanded', 'true');
      }
    });
    
    // Initialiser l'attribut aria-expanded
    toggleButton.setAttribute('aria-expanded', 'false');
  }
})();

// Écouteur pour le formulaire de recherche (si présent)
if (formRecherche) {
  formRecherche.addEventListener("submit", function (e) {
    e.preventDefault();
    chargerTrajets(new FormData(this));
  });
}

// Fonction pour charger les trajets
async function chargerTrajets(formData) {
  afficherChargement();

  const searchData = new URLSearchParams();
  searchData.append("depart", formData.get("depart"));
  searchData.append("arrivee", formData.get("arrivee"));
  searchData.append("date", formData.get("date"));

  try {
    const response = await fetch("../backend/trajets/rechercher.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: searchData,
    });

    if (!response.ok) throw new Error("Erreur réseau");
    const data = await response.json();

    if (!data.success || !data.trajets || data.trajets.length === 0) {
      afficherAucunResultat();
      return;
    }

    // Enrichir avec les notes
    trajetsGlobal = await Promise.all(
      data.trajets.map(async (trajet) => {
        const noteRes = await fetch(
          `../backend/avis/moyenne.php?chauffeur_id=${trajet.id_chauffeur}`
        );
        const noteData = await noteRes.json();
        trajet.note_moyenne = noteData.moyenne ?? 0;
        trajet.nb_avis = noteData.total ?? 0;
        return trajet;
      })
    );

    trierEtAfficher();
  } catch (error) {
    afficherErreur();
    // ************ Log pour le debug : ***********
    resultats.innerHTML += `<div style="color:red;font-size:0.9em;">Erreur de connexion au backend : ${error.message}</div>`;
    console.error("Erreur:", error);
  }
}

// Fonction de tri et affichage
function trierEtAfficher() {
  const tri = selectTri.value;
  const noteMin = parseFloat(selectNote.value);

  // Cloner et filtrer les trajets selon la note minimale
  let trajets = [...trajetsGlobal].filter((t) => t.note_moyenne >= noteMin);

  // Appliquer le tri
  switch (tri) {
    case "note":
      trajets.sort((a, b) => b.note_moyenne - a.note_moyenne);
      break;
    case "prix":
      trajets.sort((a, b) => a.prix - b.prix);
      break;
    case "places":
      trajets.sort((a, b) => b.nb_places_dispo - a.nb_places_dispo);
      break;
  }

  afficherTrajets(trajets);
}

// Fonctions utilitaires pour l'affichage
function afficherChargement() {
  resultats.innerHTML =
    '<p class="loading"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</p>';
}

function afficherAucunResultat() {
  resultats.innerHTML = `
        <div class="no-results">
            <i class="fas fa-info-circle"></i>
            <p>Aucun trajet trouvé pour votre recherche</p>
        </div>
    `;
}

function afficherErreur() {
  resultats.innerHTML = `
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <p>Une erreur est survenue lors de la recherche</p>
        </div>
    `;
}

// Fonction d'affichage des trajets
function afficherTrajets(trajets) {
  const noteMin = parseFloat(selectNote.value);
  resultats.innerHTML = "";

  trajets.forEach((trajet) => {
    const trajetDiv = document.createElement("div");
    trajetDiv.classList.add("trajet-card");

    // Marquage visuel si la note est inférieure au filtre actuel
    if (trajet.note_moyenne < noteMin) {
      trajetDiv.classList.add("note-faible");
    }

    trajetDiv.innerHTML = `
            <div class="trajet-header">
                <h3><i class="fas fa-route"></i> ${trajet.ville_depart} → ${
      trajet.ville_arrivee
    }</h3>
                <span class="eco-badge">${
                  trajet.vehicule_electrique ? "🌱 Éco" : ""
                }</span>
            </div>
            <div class="trajet-details">
                <p><i class="fas fa-calendar"></i> ${trajet.date_depart}</p>
                <p><i class="fas fa-clock"></i> ${trajet.heure_depart}</p>
                <p><i class="fas fa-euro-sign"></i> ${trajet.prix} €</p>
                <p><i class="fas fa-user-friends"></i> ${
                  trajet.nb_places_dispo
                } place(s) disponible(s)</p>
                <p><i class="fas fa-user"></i> ${trajet.pseudo}</p>
                <p class="note-chauffeur">⭐ ${trajet.note_moyenne.toFixed(
                  1
                )} (${trajet.nb_avis} avis)</p>
            </div>
            <a href="detail.html?id=${trajet.id}" class="btn-details">
                <i class="fas fa-info-circle"></i> Voir détails
            </a>
        `;

    resultats.appendChild(trajetDiv);
  });
}

// Gestion du formulaire de recherche pour index.html
const searchForm = document.getElementById('search-form');
if (searchForm) {
  searchForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Récupérer les valeurs du formulaire
    const departure = document.getElementById('departure').value;
    const arrival = document.getElementById('arrival').value;
    const date = document.getElementById('date').value;
    const ecoFilter = document.getElementById('eco-filter').checked;
    const maxPrice = document.getElementById('max-price').value;
    const maxDuration = document.getElementById('max-duration').value;
    const minRating = document.getElementById('min-rating').value;
    
    // Construire l'URL avec les paramètres de recherche
    const params = new URLSearchParams();
    if (departure) params.append('depart', departure);
    if (arrival) params.append('arrivee', arrival);
    if (date) params.append('date', date);
    if (ecoFilter) params.append('eco', '1');
    if (maxPrice) params.append('max_prix', maxPrice);
    if (maxDuration) params.append('max_duree', maxDuration);
    if (minRating) params.append('min_note', minRating);
    
    // Rediriger vers la page de résultats
    window.location.href = `rechercher-covoiturage.html?${params.toString()}`;
  });
}

// Initialisation des écouteurs d'événements (si les éléments existent)
if (selectTri) {
  selectTri.addEventListener("change", trierEtAfficher);
}
if (selectNote) {
  selectNote.addEventListener("change", trierEtAfficher);
}
