// Etat de l'application
let allTrajets = [];
let filteredTrajets = [];

// Eléments du DOM
const trajetsContainer = document.getElementById('trajets-container');
const filterStatus = document.getElementById('filter-status');
const filterRole = document.getElementById('filter-role');
const filterDateDebut = document.getElementById('filter-date-debut');
const filterDateFin = document.getElementById('filter-date-fin');

// Chargement initial
document.addEventListener('DOMContentLoaded', function () {
  loadHistorique();
  setupEventListeners();
});

// Configuration des écouteurs d'événements
function setupEventListeners() {
  filterStatus.addEventListener('change', applyFilters);
  filterRole.addEventListener('change', applyFilters);
  filterDateDebut.addEventListener('change', applyFilters);
  filterDateFin.addEventListener('change', applyFilters);

  // Boutons d'action des filtres
  document
    .getElementById('btn-filtrer')
    .addEventListener('click', applyFilters);
  document.getElementById('btn-reset').addEventListener('click', resetFilters);
}

// Réinitialisation des filtres
function resetFilters() {
  filterStatus.value = 'tous';
  filterRole.value = 'tous';
  filterDateDebut.value = '';
  filterDateFin.value = '';
  applyFilters();
}

// Chargement de l'historique
async function loadHistorique() {
  try {
    const response = await fetch('../backend/trajets/historique.php');

    if (!response.ok) {
      throw new Error('Erreur lors du chargement');
    }

    const data = await response.json();

    if (data.success) {
      allTrajets = data.trajets || [];
      updateStats(data.stats || {});
      applyFilters();
    } else {
      showError(data.message || "Erreur lors du chargement de l'historique");
    }
  } catch (error) {
    console.error('Erreur:', error);
    showError("Impossible de charger l'historique. Veuillez réessayer.");
  }
}

// Mise à jour des statistiques
function updateStats(stats) {
  document.getElementById('stat-total').textContent = stats.total_trajets || 0;
  document.getElementById('stat-km').textContent =
    (stats.total_km || 0) + ' km';
  document.getElementById('stat-co2').textContent =
    (stats.co2_economise || 0) + ' kg';
  document.getElementById('stat-credits').textContent =
    stats.credits_gagnes || 0;
}

// Application des filtres
function applyFilters() {
  const statusFilter = filterStatus.value;
  const roleFilter = filterRole.value;
  const dateDebutFilter = filterDateDebut.value;
  const dateFinFilter = filterDateFin.value;

  filteredTrajets = allTrajets.filter((trajet) => {
    // Filtre par statut
    if (statusFilter !== 'tous' && trajet.statut !== statusFilter) {
      return false;
    }

    // Filtre par rôle
    if (roleFilter !== 'tous') {
      const isDriverRole = roleFilter === 'chauffeur';
      if (trajet.est_chauffeur !== isDriverRole) {
        return false;
      }
    }

    // Filtre par date de début
    if (dateDebutFilter && trajet.date < dateDebutFilter) {
      return false;
    }

    // Filtre par date de fin
    if (dateFinFilter && trajet.date > dateFinFilter) {
      return false;
    }

    return true;
  });

  renderTrajets();
}

// Rendu des trajets
function renderTrajets() {
  if (filteredTrajets.length === 0) {
    trajetsContainer.innerHTML = `
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Aucun trajet trouvé</h3>
                    <p>Essayez de modifier vos filtres ou <a href="add-voyage.html" class="btn btn-primary">proposez un nouveau trajet</a></p>
                </div>
            `;
    return;
  }

  const trajetsHTML = filteredTrajets
    .map((trajet) => createTrajetCard(trajet))
    .join('');
  trajetsContainer.innerHTML = `<div class="trajets-grid">${trajetsHTML}</div>`;
}

// Création d'une carte de trajet
function createTrajetCard(trajet) {
  const statusClass = `status-${trajet.statut.replace('_', '-')}`;
  const roleClass = trajet.est_chauffeur ? 'role-chauffeur' : 'role-passager';
  const roleText = trajet.est_chauffeur ? '🚗 Chauffeur' : '🧑‍🦱 Passager';

  return `
            <div class="trajet-card">
                <div class="trajet-header">
                    <div class="trajet-route">
                        <i class="fas fa-route"></i>
                        <span>${trajet.ville_depart} &rarr ${
    trajet.ville_arrivee
  }</span>
                    </div>
                    <span class="trajet-status ${statusClass}">
                        ${getStatusText(trajet.statut)}
                    </span>
                </div>

                <div class="trajet-details">
                    <div class="detail-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>${formatDate(trajet.date)} à ${
    trajet.heure
  }</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas ${
                          trajet.est_chauffeur ? 'fa-steering-wheel' : 'fa-user'
                        }"></i>
                        <span class="role-badge ${roleClass}">${roleText}</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-euro-sign"></i>
                        <span>${trajet.prix_total || trajet.prix} €</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-road"></i>
                        <span>${trajet.distance || 'N/A'} km</span>
                    </div>
                </div>

                <div class="trajet-actions">
                    ${generateActionButtons(trajet)}
                </div>
            </div>
        `;
}

// Génération des boutons d'action
function generateActionButtons(trajet) {
  let buttons = [];

  // Bouton voir détails (toujours disponible)
  buttons.push(`
            <a href="detail.html?id=${trajet.id}" class="btn btn-secondary">
                Voir détails
            </a>
        `);

  // Actions selon le statut et le rôle
  if (trajet.statut === 'a-venir') {
    // Trajet à venir - possibilité d'annuler
    if (trajet.annulable) {
      buttons.push(`
                    <button onclick="annulerTrajet(${trajet.id})" class="btn btn-danger">
                        Annuler
                    </button>
                `);
    }

    // Si chauffeur, possibilité de démarrer
    if (trajet.est_chauffeur) {
      buttons.push(`
                    <button onclick="demarrerTrajet(${trajet.id})" class="btn btn-success">
                        Démarrer
                    </button>
                `);
    }
  } else if (trajet.statut === 'en-cours' && trajet.est_chauffeur) {
    // Trajet en cours - chauffeur peut terminer
    buttons.push(`
                <button onclick="terminerTrajet(${trajet.id})" class="btn btn-warning">
                    Terminer
                </button>
            `);
  } else if (trajet.statut === 'termine') {
    // Trajet terminé - possibilité de laisser un avis
    if (!trajet.avis_laisse) {
      buttons.push(`
                    <a href="laisser-avis.html?trajet_id=${trajet.id}" class="btn btn-primary">
                        Laisser un avis
                    </a>
                `);
    }
  }

  return buttons.join('');
}

// Formatage de la date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

// Texte du statut
function getStatusText(statut) {
  const statusTexts = {
    'a-venir': 'A venir',
    'en-cours': 'En cours',
    termine: 'Terminé',
    annule: 'Annulé',
  };
  return statusTexts[statut] || statut;
}

// Actions sur les trajets
async function annulerTrajet(trajetId) {
  if (!confirm('Etes-vous sûr de vouloir annuler ce trajet ?')) {
    return;
  }

  try {
    const response = await fetch('../backend/trajets/annuler.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `trajet_id=${trajetId}`,
    });

    const result = await response.json();

    if (result.success) {
      alert('Trajet annulé avec succès');
      loadHistorique(); // Recharger la liste
    } else {
      alert(result.message || "Erreur lors de l'annulation");
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert("Erreur lors de l'annulation du trajet");
  }
}

async function demarrerTrajet(trajetId) {
  if (!confirm('Démarrer ce trajet maintenant ?')) {
    return;
  }

  try {
    const response = await fetch('../backend/trajets/demarrer.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `trajet_id=${trajetId}`,
    });

    const result = await response.json();

    if (result.success) {
      alert('Trajet démarré avec succès');
      loadHistorique(); // Recharger la liste
    } else {
      alert(result.message || 'Erreur lors du démarrage');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors du démarrage du trajet');
  }
}

async function terminerTrajet(trajetId) {
  if (!confirm("Confirmer l'arrivée à destination ?")) {
    return;
  }

  try {
    const response = await fetch('../backend/trajets/terminer.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `trajet_id=${trajetId}`,
    });

    const result = await response.json();

    if (result.success) {
      alert('Trajet terminé avec succès');
      loadHistorique(); // Recharger la liste
    } else {
      alert(result.message || 'Erreur lors de la finalisation');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors de la finalisation du trajet');
  }
}

// Affichage des erreurs
function showError(message) {
  trajetsContainer.innerHTML = `
            <div class="no-results">
                <div class="no-results-icon"></div>
                <h3>Erreur</h3>
                <p>${message}</p>
                <button onclick="loadHistorique()" class="btn btn-primary">
                    Réessayer
                </button>
            </div>
        `;
}
