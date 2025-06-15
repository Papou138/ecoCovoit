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
      .then((data) => {
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

        data.trajets.forEach((trajet) => {
          resultats.innerHTML += `
                <div class="trajet-card">
                    <div class="trajet-header">
                        <h3><i class="fas fa-route"></i> ${
                          trajet.ville_depart
                        } → ${trajet.ville_arrivee}</h3>
                        <span class="eco-badge">${
                          trajet.vehicule_electrique ? "🌱 Éco" : ""
                        }</span>
                    </div>
                    <div class="trajet-details">
                        <p><i class="fas fa-calendar"></i> ${
                          trajet.date_depart
                        }</p>
                        <p><i class="fas fa-clock"></i> ${
                          trajet.heure_depart
                        }</p>
                        <p><i class="fas fa-euro-sign"></i> ${trajet.prix} €</p>
                        <p><i class="fas fa-user-friends"></i> ${
                          trajet.nb_places_dispo
                        } place(s) disponible(s)</p>
                    </div>
                    <a href="detail.html?id=${trajet.id}" class="btn-details">
                        <i class="fas fa-info-circle"></i> Voir détails
                    </a>
                </div>
            `;
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
