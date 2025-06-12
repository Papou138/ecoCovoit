document
  .getElementById("formRecherche")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    const depart = e.target.depart.value;
    const arrivee = e.target.arrivee.value;
    const date = e.target.date.value;

    // Simuler résultats
    const resultats = document.getElementById("resultats");
    resultats.innerHTML = `
    <h3>Résultats :</h3>
    <div>
      <p><strong>Chauffeur :</strong> Alice (note : 4.5⭐)</p>
      <p><strong>Départ :</strong> ${depart} — <strong>Arrivée :</strong> ${arrivee}</p>
      <p><strong>Date :</strong> ${date} à 08:00</p>
      <p><strong>Prix :</strong> 10 €</p>
      <p><strong>Écologique :</strong> ✅</p>
      <a href="detail.html">Voir détails</a>
    </div>
  `;
  });

document.getElementById("search-form").addEventListener("submit", function (e) {
  e.preventDefault();

  const formData = new FormData(this);

  fetch("backend/trajets/rechercher.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      const results = document.getElementById("results");
      results.innerHTML = "";

      if (!data.success || data.trajets.length === 0) {
        results.innerHTML = "<p>Aucun trajet trouvé</p>";
        return;
      }

      data.trajets.forEach((trajet) => {
        results.innerHTML += `
          <div class="result-item">
            <p><strong>${trajet.ville_depart}</strong> → <strong>${trajet.ville_arrivee}</strong></p>
            <p>Départ : ${trajet.date_depart} à ${trajet.heure_depart}</p>
            <p>Prix : ${trajet.prix} €</p>
            <a href="detail.html?id=${trajet.id}">Voir détail</a>
          </div>
        `;
      });
    });
});
