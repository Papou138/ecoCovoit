/**
 * Gestion de la page mes réservations
 * Fonctionnalités pour afficher et gérer les réservations de l'utilisateur
 */

const container = document.getElementById('reservations-cards');
const statusFilter = document.getElementById('status-filter');
const dateFilter = document.getElementById('date-filter');
const resetFilters = document.getElementById('reset-filters');

let allReservations = [];
let filteredReservations = [];

// Charger les réservations
function loadReservations() {
  fetch('../backend/reservations/mes-reservations.php')
    .then((res) => res.json())
    .then((data) => {
      allReservations = data;
      filteredReservations = data;
      updateStatistics();
      displayReservations();
    })
    .catch((error) => {
      console.error('Erreur lors du chargement des réservations:', error);
      container.innerHTML = `
        <div class="error-card">
          <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <h3>Erreur de chargement</h3>
          <p>Impossible de charger vos réservations. Veuillez réessayer.</p>
          <button onclick="loadReservations()" class="btn-primary">
            <i class="fas fa-redo"></i> Réessayer
          </button>
        </div>
      `;
    });
}

// Mettre à jour les statistiques
function updateStatistics() {
  const activeReservations = allReservations.filter(
    (r) => r.statut !== 'termine' && r.statut !== 'annule'
  );
  const completedTrips = allReservations.filter((r) => r.statut === 'termine');

  document.getElementById('total-reservations').textContent =
    activeReservations.length;
  document.getElementById('completed-trips').textContent =
    completedTrips.length;
  document.getElementById('co2-saved').textContent = Math.round(
    completedTrips.length * 2.1
  ); // Estimation CO2
  document.getElementById('money-saved').textContent = Math.round(
    completedTrips.length * 8.5
  ); // Estimation économies
}

// Afficher les réservations
function displayReservations() {
  container.innerHTML = '';

  if (filteredReservations.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
        <h3>Aucune réservation trouvée</h3>
        <p>Vous n'avez encore réservé aucun trajet ou aucun trajet ne correspond à vos critères.</p>
        <a href="rechercher-covoiturage.html" class="btn-primary">
          <i class="fas fa-search"></i> Rechercher un trajet
        </a>
      </div>
    `;
    return;
  }

  filteredReservations.forEach((trajet) => {
    const card = document.createElement('div');
    card.classList.add('reservation-card');
    card.classList.add(`status-${trajet.statut}`);

    const statusIcon = getStatusIcon(trajet.statut);
    const statusText = getStatusText(trajet.statut);

    card.innerHTML = `
      <div class="card-header">
        <div class="trip-route">
          <h4>
            <i class="fas fa-map-marker-alt"></i> ${trajet.ville_depart}
            <i class="fas fa-arrow-right"></i>
            <i class="fas fa-map-marker-alt"></i> ${trajet.ville_arrivee}
          </h4>
        </div>
        <div class="trip-status">
          <span class="status-badge status-${trajet.statut}">
            ${statusIcon} ${statusText}
          </span>
        </div>
      </div>
      <div class="card-body">
        <div class="trip-info">
          <div class="info-item">
            <i class="fas fa-calendar-alt"></i>
            <span><strong>Date :</strong> ${formatDate(trajet.date)}</span>
          </div>
          <div class="info-item">
            <i class="fas fa-clock"></i>
            <span><strong>Heure :</strong> ${
              trajet.heure || 'Non spécifiée'
            }</span>
          </div>
          <div class="info-item">
            <i class="fas fa-user"></i>
            <span><strong>Chauffeur :</strong> ${trajet.pseudo_chauffeur}</span>
          </div>
          <div class="info-item">
            <i class="fas fa-euro-sign"></i>
            <span><strong>Prix :</strong> ${trajet.prix || 'Gratuit'}</span>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <div class="action-buttons">
          ${getActionButtons(trajet)}
        </div>
      </div>
    `;

    container.appendChild(card);
  });
}

// Obtenir l'icône du statut
function getStatusIcon(status) {
  const icons = {
    en_attente: '<i class="fas fa-hourglass-half"></i>',
    confirme: '<i class="fas fa-check-circle"></i>',
    en_cours: '<i class="fas fa-car"></i>',
    termine: '<i class="fas fa-flag-checkered"></i>',
    annule: '<i class="fas fa-times-circle"></i>',
  };
  return icons[status] || '<i class="fas fa-question-circle"></i>';
}

// Obtenir le texte du statut
function getStatusText(status) {
  const texts = {
    en_attente: 'En attente',
    confirme: 'Confirmé',
    en_cours: 'En cours',
    termine: 'Terminé',
    annule: 'Annulé',
  };
  return texts[status] || 'Inconnu';
}

// Obtenir les boutons d'action
function getActionButtons(trajet) {
  let buttons = '';

  if (trajet.statut === 'en_attente' || trajet.statut === 'confirme') {
    buttons += `
      <button onclick="annuler(${trajet.id})" class="btn-danger">
        <i class="fas fa-times"></i> Annuler
      </button>
    `;
  }

  if (trajet.statut === 'termine') {
    buttons += `
      <button onclick="laisserAvis(${trajet.id})" class="btn-secondary">
        <i class="fas fa-star"></i> Laisser un avis
      </button>
    `;
  }

  buttons += `
    <button onclick="voirDetails(${trajet.id})" class="btn-primary">
      <i class="fas fa-eye"></i> Détails
    </button>
  `;

  return buttons;
}

// Formater la date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

// Filtrer les réservations
function filterReservations() {
  const statusValue = statusFilter.value;
  const dateValue = dateFilter.value;

  filteredReservations = allReservations.filter((trajet) => {
    const statusMatch = !statusValue || trajet.statut === statusValue;
    const dateMatch = !dateValue || trajet.date === dateValue;
    return statusMatch && dateMatch;
  });

  displayReservations();
}

// Réinitialiser les filtres
function resetFiltersHandler() {
  statusFilter.value = '';
  dateFilter.value = '';
  filteredReservations = allReservations;
  displayReservations();
}

// Annuler une réservation
function annuler(trajetId) {
  if (!confirm('Etes-vous sûr de vouloir annuler cette réservation ?')) return;

  const button = document.querySelector(
    `button[onclick="annuler(${trajetId})"]`
  );
  const originalText = button.innerHTML;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Annulation...';
  button.disabled = true;

  fetch('../backend/trajets/annuler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'trajet_id=' + trajetId,
  })
    .then((res) => res.json())
    .then((data) => {
      alert(data.message);
      if (data.success) {
        loadReservations(); // Recharger les données
      } else {
        button.innerHTML = originalText;
        button.disabled = false;
      }
    })
    .catch((error) => {
      console.error("Erreur lors de l'annulation:", error);
      alert("Erreur lors de l'annulation. Veuillez réessayer.");
      button.innerHTML = originalText;
      button.disabled = false;
    });
}

// Laisser un avis
function laisserAvis(trajetId) {
  window.location.href = `laisser-avis.html?trajet_id=${trajetId}`;
}

// Voir les détails
function voirDetails(trajetId) {
  window.location.href = `detail.html?id=${trajetId}`;
}

// Event listeners
statusFilter.addEventListener('change', filterReservations);
dateFilter.addEventListener('change', filterReservations);
resetFilters.addEventListener('click', resetFiltersHandler);

// Initialisation
loadReservations();

// Fonction de déconnexion (utilitaire)
async function logout() {
  try {
    const response = await fetch('../backend/auth/logout.php', {
      method: 'POST',
    });
    await response.json();
    window.location.href = 'login.html';
  } catch (error) {
    console.error('Erreur lors de la déconnexion:', error);
    window.location.href = 'login.html';
  }
}
