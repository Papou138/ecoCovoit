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
