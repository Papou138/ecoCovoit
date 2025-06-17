document
  .getElementById("formRecherche")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const resultats = document.getElementById("resultats");

    // Afficher un message de chargement
    resultats.innerHTML =
      '<p class="loading"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</p>';

    // Préparer les données pour l'envoi
    const searchData = new URLSearchParams();
    searchData.append("depart", formData.get("depart"));
    searchData.append("arrivee", formData.get("arrivee"));
    searchData.append("date", formData.get("date"));

    fetch("../backend/trajets/rechercher.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: searchData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Erreur réseau");
        }
        return response.json();
      })
      .then(async (data) => {
        resultats.innerHTML = "";

        if (!data.success || !data.trajets || data.trajets.length === 0) {
          resultats.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-info-circle"></i>
                    <p>Aucun trajet trouvé pour votre recherche</p>
                </div>
            `;
          return;
        }

        // Étape 1 : enrichir chaque trajet avec sa note moyenne (en parallèle)
        const notesPromises = data.trajets.map(async (trajet) => {
          const res = await fetch(
            `../backend/avis/moyenne.php?chauffeur_id=${trajet.id_chauffeur}`
          );
          const noteData = await res.json();
          trajet.note_moyenne = noteData.moyenne ?? 0;
          trajet.nb_avis = noteData.total ?? 0;
          return trajet;
        });

        // Étape 2 : attendre toutes les notes
        const trajetsAvecNotes = await Promise.all(notesPromises);

        // Étape 3 : trier les trajets par note décroissante
        trajetsAvecNotes.sort((a, b) => b.note_moyenne - a.note_moyenne);

        // Étape 4 : afficher les trajets triés
        trajetsAvecNotes.forEach((trajet) => {
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
      })
      .catch((error) => {
        resultats.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p>Une erreur est survenue lors de la recherche</p>
            </div>
        `;
        console.error("Erreur:", error);
      });
  });
