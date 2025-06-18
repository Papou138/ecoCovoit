let trajetsGlobal = []; // Variable globale pour stocker les trajets
const selectTri = document.getElementById("tri-option");
const selectNote = document.getElementById("filtre-note");

document
  .getElementById("formRecherche")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    chargerTrajets(new FormData(this));
  });

// Fonction pour charger les trajets
async function chargerTrajets(formData) {
  const resultats = document.getElementById("resultats");
  resultats.innerHTML =
    '<p class="loading"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</p>';

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
      resultats.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-info-circle"></i>
                    <p>Aucun trajet trouvé pour votre recherche</p>
                </div>
            `;
      return;
    }

    // Enrichir avec les notes
    const notesPromises = data.trajets.map(async (trajet) => {
      const noteRes = await fetch(
        `../backend/avis/moyenne.php?chauffeur_id=${trajet.id_chauffeur}`
      );
      const noteData = await noteRes.json();
      trajet.note_moyenne = noteData.moyenne ?? 0;
      trajet.nb_avis = noteData.total ?? 0;
      return trajet;
    });

    trajetsGlobal = await Promise.all(notesPromises);
    trierEtAfficher();
  } catch (error) {
    resultats.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p>Une erreur est survenue lors de la recherche</p>
            </div>
        `;
    console.error("Erreur:", error);
  }
}

// Fonction de tri et affichage
function trierEtAfficher() {
  const tri = document.getElementById("tri-option").value;
  const noteMin = parseFloat(document.getElementById("filtre-note").value);

  // Cloner et filtrer les trajets selon la note minimale
  let trajets = [...trajetsGlobal].filter((t) => t.note_moyenne >= noteMin);

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

// Fonction d'affichage
function afficherTrajets(trajets) {
  const resultats = document.getElementById("resultats");
  resultats.innerHTML = "";

  trajets.forEach((trajet) => {
    const trajetDiv = document.createElement("div");
    trajetDiv.classList.add("trajet-card");

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

// Écouteur d'événement pour le tri
document
  .getElementById("tri-option")
  .addEventListener("change", trierEtAfficher);

// Écouteur d'événement pour le filtre de note
document
  .getElementById("filtre-note")
  .addEventListener("change", trierEtAfficher);

// Initialisation des événements de tri et de filtres
selectNote.addEventListener("change", trierEtAfficher);
