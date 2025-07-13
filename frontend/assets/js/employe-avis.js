// Etat de l'application
let allAvis = [];
let filteredAvis = [];

// Eléments du DOM
const avisContainer = document.getElementById('avis-container');
const filterStatus = document.getElementById('filter-status');
const filterRating = document.getElementById('filter-rating');
const filterDateDebut = document.getElementById('filter-date-debut');
const filterDateFin = document.getElementById('filter-date-fin');

// Chargement initial
document.addEventListener('DOMContentLoaded', function () {
  loadAvis();
});

// Chargement des avis
async function loadAvis() {
  try {
    const response = await fetch('../backend/avis/lister_non_valides.php');

    if (!response.ok) {
      throw new Error('Erreur lors du chargement');
    }

    const data = await response.json();

    if (data.success) {
      allAvis = data.avis || [];
      updateStats(data.stats || {});
      applyFilters();
    } else {
      showError(data.message || 'Erreur lors du chargement des avis');
    }
  } catch (error) {
    console.error('Erreur:', error);
    showError('Impossible de charger les avis. Veuillez réessayer.');
  }
}

// Mise à jour des statistiques
function updateStats(stats) {
  document.getElementById('stat-attente').textContent = stats.en_attente || 0;
  document.getElementById('stat-valides').textContent = stats.valides || 0;
  document.getElementById('stat-rejetes').textContent = stats.rejetes || 0;
  document.getElementById('stat-moyenne').textContent = (
    stats.moyenne || 0
  ).toFixed(1);
}

// Application des filtres
function applyFilters() {
  const statusFilter = filterStatus.value;
  const ratingFilter = filterRating.value;
  const dateDebutFilter = filterDateDebut.value;
  const dateFinFilter = filterDateFin.value;

  filteredAvis = allAvis.filter((avis) => {
    // Filtre par statut
    if (statusFilter !== 'tous' && avis.statut !== statusFilter) {
      return false;
    }

    // Filtre par note
    if (ratingFilter !== 'tous' && avis.note != ratingFilter) {
      return false;
    }

    // Filtre par date de début
    if (dateDebutFilter && avis.date_creation < dateDebutFilter) {
      return false;
    }

    // Filtre par date de fin
    if (dateFinFilter && avis.date_creation > dateFinFilter) {
      return false;
    }

    return true;
  });

  renderAvis();
}

// Réinitialisation des filtres
function resetFilters() {
  filterStatus.value = 'en-attente';
  filterRating.value = 'tous';
  filterDateDebut.value = '';
  filterDateFin.value = '';
  applyFilters();
}

// Rendu des avis
function renderAvis() {
  if (filteredAvis.length === 0) {
    avisContainer.innerHTML = `
                    <div class="no-results">
                        <div class="no-results-icon"></div>
                        <h3>Aucun avis trouvé</h3>
                        <p>Aucun avis ne correspond à vos critères de recherche</p>
                    </div>
                `;
    return;
  }

  const avisHTML = filteredAvis.map((avis) => createAvisCard(avis)).join('');
  avisContainer.innerHTML = `<div class="avis-container">${avisHTML}</div>`;
}

// Création d'une carte d'avis
function createAvisCard(avis) {
  const statusClass = `status-${avis.statut.replace('_', '-')}`;
  const stars = '&starf'.repeat(avis.note) + '&starf'.repeat(5 - avis.note);

  return `
                <div class="avis-card">
                    <div class="avis-header">
                        <div class="avis-meta">
                            <div class="avis-trajet">
                                ${avis.ville_depart || 'N/A'} &rarr ${
    avis.ville_arrivee || 'N/A'
  }
                            </div>
                            <div class="avis-date">
                                Publié le ${formatDate(avis.date_creation)}
                            </div>
                            <div class="avis-rating">
                                <span class="stars">${stars}</span>
                                <span class="rating-value">${avis.note}/5</span>
                            </div>
                        </div>
                        <span class="avis-status ${statusClass}">
                            ${getStatusText(avis.statut)}
                        </span>
                    </div>

                    <div class="avis-content">
                        <div class="avis-comment">
                            "${avis.commentaire || 'Aucun commentaire'}"
                        </div>

                        <div class="avis-details">
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>Par ${
                                  avis.nom_evaluateur || 'Utilisateur'
                                }</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>Evalue ${
                                  avis.nom_evalue || 'Utilisateur'
                                }</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>Trajet du ${formatDate(
                                  avis.date_trajet
                                )}</span>
                            </div>
                        </div>
                    </div>

                    <div class="avis-actions">
                        ${generateActionButtons(avis)}
                    </div>

                    ${
                      avis.statut === 'en-attente'
                        ? generateModerateSection(avis.id)
                        : ''
                    }
                </div>
            `;
}

// Génération des boutons d'action
function generateActionButtons(avis) {
  let buttons = [];

  if (avis.statut === 'en-attente') {
    buttons.push(`
                    <button onclick="validateAvis(${avis.id})" class="btn btn-success">
                        Valider
                    </button>
                    <button onclick="rejectAvis(${avis.id})" class="btn btn-danger">
                        Rejeter
                    </button>
                `);
  }

  buttons.push(`
                <a href="detail.html?id=${avis.trajet_id}" class="btn btn-primary">
                    Voir trajet
                </a>
            `);

  return buttons.join('');
}

// Section de modération
function generateModerateSection(avisId) {
  return `
                <div class="moderate-section hidden" id="moderate-${avisId}">
                    <h4>Commentaire de modération</h4>
                    <textarea
                        id="comment-${avisId}"
                        class="moderate-textarea"
                        placeholder="Ajoutez un commentaire de modération (optionnel)..."
                    ></textarea>
                    <div class="flex-gap">
                        <button onclick="confirmValidation(${avisId})" class="btn btn-success">
                            Confirmer validation
                        </button>
                        <button onclick="confirmRejection(${avisId})" class="btn btn-danger">
                            Confirmer rejet
                        </button>
                        <button onclick="hideModerateSection(${avisId})" class="btn btn-warning">
                            Annuler
                        </button>
                    </div>
                </div>
            `;
}

// Actions de modération
function validateAvis(avisId) {
  showModerateSection(avisId);
}

function rejectAvis(avisId) {
  showModerateSection(avisId);
}

function showModerateSection(avisId) {
  const section = document.getElementById(`moderate-${avisId}`);
  if (section) {
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function hideModerateSection(avisId) {
  const section = document.getElementById(`moderate-${avisId}`);
  if (section) {
    section.style.display = 'none';
  }
}

async function confirmValidation(avisId) {
  const comment = document.getElementById(`comment-${avisId}`).value;

  try {
    const response = await fetch('../backend/avis/valider.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `avis_id=${avisId}&commentaire_moderation=${encodeURIComponent(
        comment
      )}`,
    });

    const result = await response.json();

    if (result.success) {
      alert('Avis validé avec succès');
      loadAvis(); // Recharger la liste
    } else {
      alert(result.message || 'Erreur lors de la validation');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert("Erreur lors de la validation de l'avis");
  }
}

async function confirmRejection(avisId) {
  const comment = document.getElementById(`comment-${avisId}`).value;

  if (!comment.trim()) {
    alert('Un commentaire de modération est requis pour rejeter un avis');
    return;
  }

  try {
    const response = await fetch('../backend/avis/rejeter.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `avis_id=${avisId}&commentaire_moderation=${encodeURIComponent(
        comment
      )}`,
    });

    const result = await response.json();

    if (result.success) {
      alert('Avis rejeté avec succès');
      loadAvis(); // Recharger la liste
    } else {
      alert(result.message || 'Erreur lors du rejet');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert("Erreur lors du rejet de l'avis");
  }
}

// Validation en lot
async function validateAll() {
  const pendingAvis = filteredAvis.filter(
    (avis) => avis.statut === 'en-attente'
  );

  if (pendingAvis.length === 0) {
    alert('Aucun avis en attente à valider');
    return;
  }

  if (!confirm(`Valider ${pendingAvis.length} avis en attente ?`)) {
    return;
  }

  try {
    const promises = pendingAvis.map((avis) =>
      fetch('../backend/avis/valider.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `avis_id=${avis.id}`,
      })
    );

    await Promise.all(promises);
    alert(`${pendingAvis.length} avis validés avec succès`);
    loadAvis(); // Recharger la liste
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors de la validation en lot');
  }
}

// Utilitaires
function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getStatusText(statut) {
  const statusTexts = {
    'en-attente': 'En attente',
    valide: 'Validé',
    rejete: 'Rejeté',
  };
  return statusTexts[statut] || statut;
}

function showError(message) {
  avisContainer.innerHTML = `
                <div class="no-results">
                    <div class="no-results-icon"></div>
                    <h3>Erreur</h3>
                    <p>${message}</p>
                    <button onclick="loadAvis()" class="btn btn-primary">
                        Réessayer
                    </button>
                </div>
            `;
}
