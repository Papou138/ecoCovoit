// Etat de l'application
let allIncidents = [];
let filteredIncidents = [];

// Eléments du DOM
const incidentsContainer = document.getElementById('incidents-container');
const filterStatus = document.getElementById('filter-status');
const filterPriority = document.getElementById('filter-priority');
const filterType = document.getElementById('filter-type');
const filterDateDebut = document.getElementById('filter-date-debut');

// Chargement initial
document.addEventListener('DOMContentLoaded', function () {
  loadIncidents();
});

// Chargement des incidents
async function loadIncidents() {
  try {
    // Simulation des données d'incidents (A remplacer par l'API réelle)
    const mockData = {
      success: true,
      incidents: [
        {
          id: 1,
          type: 'technique',
          titre: 'Impossible de se connecter',
          description:
            'Utilisateur ne peut pas se connecter malgré un mot de passe correct.',
          statut: 'ouvert',
          priorite: 'haute',
          utilisateur: 'Jean Dupont',
          email: 'jean.dupont@email.com',
          date_creation: '2025-01-06',
          date_mise_a_jour: '2025-01-06',
          assigne_a: null,
          responses: [],
        },
        {
          id: 2,
          type: 'paiement',
          titre: 'Problème de remboursement',
          description:
            "Le remboursement du trajet annulé n'a pas été effectué.",
          statut: 'en-cours',
          priorite: 'normale',
          utilisateur: 'Marie Martin',
          email: 'marie.martin@email.com',
          date_creation: '2025-01-05',
          date_mise_a_jour: '2025-01-06',
          assigne_a: 'Support Tech',
          responses: [
            {
              auteur: 'Support Tech',
              date: '2025-01-06',
              message:
                'Dossier pris en charge, vérification en cours avec la comptabilité.',
            },
          ],
        },
      ],
      stats: {
        ouverts: 15,
        en_cours: 8,
        resolus: 142,
        temps_moyen: 24,
      },
    };

    allIncidents = mockData.incidents || [];
    updateStats(mockData.stats || {});
    applyFilters();
  } catch (error) {
    console.error('Erreur:', error);
    showError('Impossible de charger les incidents. Veuillez réessayer.');
  }
}

// Mise à jour des statistiques
function updateStats(stats) {
  document.getElementById('stat-ouverts').textContent = stats.ouverts || 0;
  document.getElementById('stat-en-cours').textContent = stats.en_cours || 0;
  document.getElementById('stat-resolus').textContent = stats.resolus || 0;
  document.getElementById('stat-temps-moyen').textContent =
    stats.temps_moyen || 0;
}

// Application des filtres
function applyFilters() {
  const statusFilter = filterStatus.value;
  const priorityFilter = filterPriority.value;
  const typeFilter = filterType.value;
  const dateDebutFilter = filterDateDebut.value;

  filteredIncidents = allIncidents.filter((incident) => {
    // Filtre par statut
    if (statusFilter !== 'tous' && incident.statut !== statusFilter) {
      return false;
    }

    // Filtre par priorité
    if (priorityFilter !== 'tous' && incident.priorite !== priorityFilter) {
      return false;
    }

    // Filtre par type
    if (typeFilter !== 'tous' && incident.type !== typeFilter) {
      return false;
    }

    // Filtre par date de début
    if (dateDebutFilter && incident.date_creation < dateDebutFilter) {
      return false;
    }

    return true;
  });

  renderIncidents();
}

// Réinitialisation des filtres
function resetFilters() {
  filterStatus.value = 'ouvert';
  filterPriority.value = 'tous';
  filterType.value = 'tous';
  filterDateDebut.value = '';
  applyFilters();
}

// Rendu des incidents
function renderIncidents() {
  if (filteredIncidents.length === 0) {
    incidentsContainer.innerHTML = `
                    <div class="no-results">
                        <div class="no-results-icon"></div>
                        <h3>Aucun incident trouvé</h3>
                        <p>Aucun incident ne correspond Í  vos critères de recherche</p>
                    </div>
                `;
    return;
  }

  const incidentsHTML = filteredIncidents
    .map((incident) => createIncidentCard(incident))
    .join('');
  incidentsContainer.innerHTML = `<div class="incidents-container">${incidentsHTML}</div>`;
}

// Création d'une carte d'incident
function createIncidentCard(incident) {
  const statusClass = `status-${incident.statut.replace('_', '-')}`;
  const priorityClass = `priority-${incident.priorite}`;
  const priorityIcon = getPriorityIcon(incident.priorite);

  return `
                <div class="incident-card">
                    <div class="incident-header">
                        <div class="incident-meta">
                            <div class="incident-type">
                                ${getTypeIcon(incident.type)} ${incident.titre}
                            </div>
                            <div class="incident-date">
                                Créé le ${formatDate(
                                  incident.date_creation
                                )} par ${incident.utilisateur}
                            </div>
                        </div>
                        <div>
                            <span class="incident-status ${statusClass}">
                                ${getStatusText(incident.statut)}
                            </span>
                            <span class="incident-priority ${priorityClass}">
                                ${priorityIcon} ${incident.priorite}
                            </span>
                        </div>
                    </div>

                    <div class="incident-content">
                        <div class="incident-description">
                            ${incident.description}
                        </div>

                        <div class="incident-details">
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>${incident.utilisateur}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>${incident.email}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>${getTypeText(incident.type)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon"></span>
                                <span>${
                                  incident.assigne_a || 'Non assigné'
                                }</span>
                            </div>
                        </div>

                        ${
                          incident.responses && incident.responses.length > 0
                            ? createResponseHistory(incident.responses)
                            : ''
                        }
                    </div>

                    <div class="incident-actions">
                        ${generateActionButtons(incident)}
                    </div>

                    ${
                      incident.statut !== 'ferme'
                        ? generateResponseSection(incident.id)
                        : ''
                    }
                </div>
            `;
}

// Génération des boutons d'action
function generateActionButtons(incident) {
  let buttons = [];

  if (incident.statut === 'ouvert') {
    buttons.push(`
                    <button onclick="takeIncident(${incident.id})" class="btn btn-primary">
                        Prendre en charge
                    </button>
                `);
  }

  if (incident.statut === 'en-cours') {
    buttons.push(`
                    <button onclick="resolveIncident(${incident.id})" class="btn btn-success">
                        Marquer comme résolu
                    </button>
                `);
  }

  if (incident.statut === 'resolu') {
    buttons.push(`
                    <button onclick="closeIncident(${incident.id})" class="btn btn-warning">
                        Fermer l'incident
                    </button>
                `);
  }

  buttons.push(`
                <button onclick="escalateIncident(${incident.id})" class="btn btn-danger">
                    Escalader
                </button>
            `);

  return buttons.join('');
}

// Section de réponse
function generateResponseSection(incidentId) {
  return `
                <div class="response-section hidden" id="response-${incidentId}">
                    <h4>Répondre à l'incident</h4>
                    <textarea
                        id="response-text-${incidentId}"
                        class="response-textarea"
                        placeholder="Tapez votre réponse ici..."
                    ></textarea>
                    <div class="flex-gap">
                        <button onclick="sendResponse(${incidentId})" class="btn btn-primary">
                            Envoyer la réponse
                        </button>
                        <button onclick="hideResponseSection(${incidentId})" class="btn btn-warning">
                            Annuler
                        </button>
                    </div>
                </div>
            `;
}

// Historique des réponses
function createResponseHistory(responses) {
  const responsesHTML = responses
    .map(
      (response) => `
                <div class="response-item">
                    <div class="response-meta">
                        <strong>${response.auteur}</strong>
                        <span>${formatDate(response.date)}</span>
                    </div>
                    <div>${response.message}</div>
                </div>
            `
    )
    .join('');

  return `
                <div class="response-history">
                    <h4>Historique des réponses</h4>
                    ${responsesHTML}
                </div>
            `;
}

// Actions sur les incidents
async function takeIncident(incidentId) {
  if (!confirm('Prendre en charge cet incident ?')) {
    return;
  }

  try {
    // Simulation de la prise en charge
    const incident = allIncidents.find((i) => i.id === incidentId);
    if (incident) {
      incident.statut = 'en-cours';
      incident.assigne_a = 'Employé Support';
      applyFilters();
      alert('Incident pris en charge avec succès');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors de la prise en charge');
  }
}

async function resolveIncident(incidentId) {
  if (!confirm('Marquer cet incident comme résolu ?')) {
    return;
  }

  try {
    // Simulation de la résolution
    const incident = allIncidents.find((i) => i.id === incidentId);
    if (incident) {
      incident.statut = 'resolu';
      applyFilters();
      alert('Incident marqué comme résolu');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors de la résolution');
  }
}

async function closeIncident(incidentId) {
  if (!confirm('Fermer définitivement cet incident ?')) {
    return;
  }

  try {
    // Simulation de la fermeture
    const incident = allIncidents.find((i) => i.id === incidentId);
    if (incident) {
      incident.statut = 'ferme';
      applyFilters();
      alert('Incident fermé avec succès');
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert('Erreur lors de la fermeture');
  }
}

function escalateIncident(incidentId) {
  alert('Incident escaladé vers le niveau supérieur');
}

function showResponseSection(incidentId) {
  const section = document.getElementById(`response-${incidentId}`);
  if (section) {
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function hideResponseSection(incidentId) {
  const section = document.getElementById(`response-${incidentId}`);
  if (section) {
    section.style.display = 'none';
  }
}

async function sendResponse(incidentId) {
  const responseText = document.getElementById(
    `response-text-${incidentId}`
  ).value;

  if (!responseText.trim()) {
    alert('Veuillez saisir une réponse');
    return;
  }

  try {
    // Simulation de l'envoi de réponse
    alert('Réponse envoyée avec succès');
    hideResponseSection(incidentId);

    // Ajouter la réponse à l'historique
    const incident = allIncidents.find((i) => i.id === incidentId);
    if (incident) {
      if (!incident.responses) incident.responses = [];
      incident.responses.push({
        auteur: 'Employé Support',
        date: new Date().toISOString(),
        message: responseText,
      });
      applyFilters();
    }
  } catch (error) {
    console.error('Erreur:', error);
    alert("Erreur lors de l'envoi de la réponse");
  }
}

function exportIncidents() {
  alert('Export des incidents en cours...');
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
    ouvert: 'Ouvert',
    'en-cours': 'En cours',
    resolu: 'Résolu',
    ferme: 'Fermé',
  };
  return statusTexts[statut] || statut;
}

function getTypeText(type) {
  const typeTexts = {
    technique: 'problème technique',
    paiement: 'problème de paiement',
    comportement: 'Comportement inapproprié',
    autre: 'Autre',
  };
  return typeTexts[type] || type;
}

function getTypeIcon(type) {
  const typeIcons = {
    technique: '<i class="fas fa-tools"></i>',
    paiement: '<i class="fas fa-euro-sign"></i>',
    comportement: '<i class="fas fa-exclamation-triangle"></i>',
    autre: '<i class="fas fa-question-circle"></i>',
  };
  return typeIcons[type] || '<i class="fas fa-tag"></i>';
}

function getPriorityIcon(priority) {
  const priorityIcons = {
    critique: '🔴',
    haute: '🟠',
    normale: '🟡',
    basse: '🟢',
  };
  return priorityIcons[priority] || '⚪';
}

function showError(message) {
  incidentsContainer.innerHTML = `
            <div class="no-results">
            <div class="no-results-icon">❌</div>
            <h3>Erreur</h3>
            <p>${message}</p>
            <button onclick="loadIncidents()" class="btn btn-primary">
            🔄 Réessayer
            </button>
            </div>
        `;
}

// Ajouter l'écouteur pour la section de réponse
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('incident-card')) {
    const incidentId = e.target.dataset.incidentId;
    if (incidentId) {
      showResponseSection(incidentId);
    }
  }
});
