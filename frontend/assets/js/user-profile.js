/**
 * Fonctions principales de gestion du profil utilisateur
 * Inclut le chargement, l'affichage et la modification des informations utilisateur, véhicules, préférences et trajets
 */

document.addEventListener('DOMContentLoaded', function () {
  // Variables globales
  let currentUser = null;

  // Initialisation
  init();

  function init() {
    loadUserData();
    loadVehicles();
    loadPreferences();
    loadCurrentTrips();
    loadStatistics();
    setupEventListeners();
  }

  // Chargement des données utilisateur
  async function loadUserData() {
    try {
      const response = await fetch('../backend/auth/get-user.php');
      const data = await response.json();

      const userInfoContainer = document.getElementById('user-info');
      const creditsAmount = document.getElementById('credits-amount');

      if (!data.success) {
        userInfoContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Erreur</h3>
                        <p>${data.message}</p>
                    </div>
                `;
        return;
      }

      currentUser = data.user;

      // Affichage des informations utilisateur
      userInfoContainer.innerHTML = `
                <div class="user-info">
                    <div class="info-item">
                        <span class="info-label">Pseudo</span>
                        <span class="info-value">${data.user.pseudo}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value">${data.user.email}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Rôle</span>
                        <span class="info-value">${getRoleLabel(
                          data.user.role
                        )}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Membre depuis</span>
                        <span class="info-value">${formatDate(
                          data.user.created_at || '2025-01-01'
                        )}</span>
                    </div>
                </div>
            `;

      // Mise à jour du solde de crédits
      creditsAmount.textContent = data.user.credit || 0;
    } catch (error) {
      console.error(
        'Erreur lors du chargement des données utilisateur:',
        error
      );
      document.getElementById('user-info').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur de connexion</h3>
                    <p>Impossible de charger vos informations</p>
                </div>
            `;
    }
  }

  // Chargement des véhicules
  async function loadVehicles() {
    try {
      const response = await fetch('../backend/vehicules/lister.php');
      const data = await response.json();

      const vehiclesContainer = document.getElementById('vehicles-list');

      if (!data.success || !data.vehicules || data.vehicules.length === 0) {
        vehiclesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-car"></i>
                        <h3>Aucun véhicule</h3>
                        <p>Ajoutez votre premier véhicule pour proposer des trajets</p>
                        <button onclick="window.location.href='add-vehicule.html'">
                            <i class="fas fa-plus"></i> Ajouter un véhicule
                        </button>
                    </div>
                `;
        return;
      }

      vehiclesContainer.innerHTML = data.vehicules
        .map(
          (vehicle) => `
                <div class="vehicle-item" data-vehicle-id="${vehicle.id}">
                    <div class="vehicle-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="vehicle-info">
                        <div class="vehicle-name">${vehicle.marque} ${vehicle.modele}</div>
                        <div class="vehicle-details">
                            ${vehicle.couleur} • ${vehicle.places} places • ${vehicle.immatriculation}
                        </div>
                    </div>
                    <div class="vehicle-actions">
                        <button onclick="editVehicle(${vehicle.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteVehicle(${vehicle.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `
        )
        .join('');
    } catch (error) {
      console.error('Erreur lors du chargement des véhicules:', error);
      document.getElementById('vehicles-list').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur de chargement</h3>
                    <p>Impossible de charger vos véhicules</p>
                </div>
            `;
    }
  }

  // Chargement des préférences
  async function loadPreferences() {
    try {
      // Simulation des préférences (à adapter selon l'API backend)
      const preferences = [
        {
          id: 'music',
          label: 'Musique autorisée',
          value: true,
          icon: 'fas fa-music',
        },
        {
          id: 'smoking',
          label: 'Fumeur',
          value: false,
          icon: 'fas fa-smoking-ban',
        },
        {
          id: 'pets',
          label: 'Animaux acceptés',
          value: true,
          icon: 'fas fa-paw',
        },
        {
          id: 'talking',
          label: 'Discussion',
          value: true,
          icon: 'fas fa-comments',
        },
        {
          id: 'air_conditioning',
          label: 'Climatisation',
          value: true,
          icon: 'fas fa-snowflake',
        },
      ];

      const preferencesContainer = document.getElementById('preferences-list');

      preferencesContainer.innerHTML = preferences
        .map(
          (pref) => `
                <div class="preference-item">
                    <span class="preference-label">
                        <i class="${pref.icon}"></i>
                        ${pref.label}
                    </span>
                    <div class="preference-toggle ${pref.value ? 'active' : ''}"
                         data-preference="${pref.id}"
                         onclick="togglePreference('${pref.id}')">
                    </div>
                </div>
            `
        )
        .join('');
    } catch (error) {
      console.error('Erreur lors du chargement des préférences:', error);
      document.getElementById('preferences-list').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur</h3>
                    <p>Impossible de charger les préférences</p>
                </div>
            `;
    }
  }

  // Chargement des trajets en cours
  async function loadCurrentTrips() {
    try {
      const response = await fetch(
        '../backend/trajets/mes-trajets.php?status=en_cours'
      );
      const data = await response.json();

      const tripsContainer = document.getElementById('current-trips');

      if (!data.success || !data.trajets || data.trajets.length === 0) {
        tripsContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-route"></i>
                        <h3>Aucun trajet en cours</h3>
                        <p>Vous n'avez actuellement aucun trajet en cours</p>
                        <button onclick="window.location.href='add-voyage.html'">
                            <i class="fas fa-plus"></i> Proposer un trajet
                        </button>
                    </div>
                `;
        return;
      }

      tripsContainer.innerHTML = data.trajets
        .map(
          (trip) => `
                <div class="trip-item" data-trip-id="${trip.id}">
                    <div class="trip-route">
                        <div class="trip-title">
                            <i class="fas fa-route"></i>
                            ${trip.ville_depart} → ${trip.ville_arrivee}
                        </div>
                        <div class="trip-details">
                            ${formatDateTime(trip.date_depart)} • ${
            trip.places_disponibles
          } places • ${trip.prix}€
                        </div>
                    </div>
                    <div class="trip-status ${trip.statut}">${getStatusLabel(
            trip.statut
          )}</div>
                    <div class="trip-actions">
                        ${
                          trip.statut === 'en_cours'
                            ? `
                            <button class="btn-success" onclick="completeTrip(${trip.id})">
                                <i class="fas fa-flag-checkered"></i> Terminer
                            </button>
                        `
                            : ''
                        }
                        <button class="btn-danger" onclick="cancelTrip(${
                          trip.id
                        })">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </div>
            `
        )
        .join('');
    } catch (error) {
      console.error('Erreur lors du chargement des trajets:', error);
      document.getElementById('current-trips').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur de chargement</h3>
                    <p>Impossible de charger vos trajets</p>
                </div>
            `;
    }
  }

  // Chargement des statistiques
  async function loadStatistics() {
    try {
      // Simulation des statistiques (à adapter selon l'API backend)
      const stats = {
        trips_count: 15,
        kilometers_saved: 1250,
        co2_saved: 180,
        rating_average: 4.7,
      };

      document.getElementById('trips-count').textContent = stats.trips_count;
      document.getElementById('kilometers-saved').textContent =
        stats.kilometers_saved;
      document.getElementById('co2-saved').textContent = stats.co2_saved;
      document.getElementById('rating-average').textContent =
        stats.rating_average.toFixed(1);
    } catch (error) {
      console.error('Erreur lors du chargement des statistiques:', error);
    }
  }

  // Configuration des événements
  function setupEventListeners() {
    // Modal d'édition du profil
    const editProfileBtn = document.getElementById('edit-profile-btn');
    const editProfileModal = document.getElementById('edit-profile-modal');
    const closeModal = editProfileModal.querySelector('.close');
    const cancelBtn = editProfileModal.querySelector('.cancel-btn');
    const editForm = document.getElementById('edit-profile-form');

    editProfileBtn.addEventListener('click', () => {
      openEditProfileModal();
    });

    closeModal.addEventListener('click', () => {
      editProfileModal.style.display = 'none';
    });

    cancelBtn.addEventListener('click', () => {
      editProfileModal.style.display = 'none';
    });

    editForm.addEventListener('submit', handleProfileUpdate);

    // Autres boutons
    document.getElementById('add-vehicle-btn').addEventListener('click', () => {
      window.location.href = 'add-vehicule.html';
    });

    document
      .getElementById('edit-preferences-btn')
      .addEventListener('click', () => {
        window.location.href = 'add-preferences.html';
      });

    document
      .getElementById('view-all-trips-btn')
      .addEventListener('click', () => {
        window.location.href = 'historique.html';
      });

    document.getElementById('add-credits-btn').addEventListener('click', () => {
      showCreditsModal();
    });

    document
      .getElementById('credits-history-btn')
      .addEventListener('click', () => {
        window.location.href = 'historique.html?filter=credits';
      });

    // Déconnexion
    document.getElementById('logout-nav').addEventListener('click', (e) => {
      e.preventDefault();
      logout();
    });

    // Fermeture des modals en cliquant à l'extérieur
    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
      }
    });
  }

  // Ouverture du modal d'édition du profil
  function openEditProfileModal() {
    if (!currentUser) return;

    document.getElementById('edit-pseudo').value = currentUser.pseudo;
    document.getElementById('edit-email').value = currentUser.email;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-profile-modal').style.display = 'block';
  }

  // Gestion de la mise à jour du profil
  async function handleProfileUpdate(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // Animation de chargement
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
    submitBtn.disabled = true;

    try {
      const response = await fetch('../backend/auth/update-profile.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Profil mis à jour avec succès !', 'success');
        document.getElementById('edit-profile-modal').style.display = 'none';
        loadUserData(); // Recharger les données
      } else {
        showNotification(
          data.message || 'Erreur lors de la mise à jour',
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur lors de la mise à jour:', error);
      showNotification('Erreur de connexion', 'error');
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  // Basculer une préférence
  window.togglePreference = async function (preferenceId) {
    const toggle = document.querySelector(
      `[data-preference="${preferenceId}"]`
    );
    const isActive = toggle.classList.contains('active');

    // Ajout d'un indicateur de chargement
    const originalContent = toggle.innerHTML;
    toggle.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    toggle.style.pointerEvents = 'none';

    try {
      const response = await fetch('../backend/preferences/enregistrer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `preference=${preferenceId}&value=${!isActive ? 1 : 0}`,
      });

      const data = await response.json();

      if (data.success) {
        toggle.classList.toggle('active');
        showNotification('Préférence mise à jour avec succès', 'success');
      } else {
        showNotification(
          data.message || 'Erreur lors de la mise à jour',
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur lors de la mise à jour de la préférence:', error);
      showNotification('Erreur de connexion au serveur', 'error');
    } finally {
      // Restauration du contenu original
      toggle.innerHTML = originalContent;
      toggle.style.pointerEvents = 'auto';
    }
  };

  // Terminer un trajet
  window.completeTrip = async function (tripId) {
    if (!confirm('Confirmez-vous que ce trajet est terminé ?')) return;

    try {
      const response = await fetch('../backend/trajets/terminer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `trajet_id=${tripId}`,
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Trajet terminé avec succès !', 'success');
        loadCurrentTrips(); // Recharger la liste
        loadStatistics(); // Mettre à jour les stats
      } else {
        showNotification(
          data.message || 'Erreur lors de la finalisation',
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur lors de la finalisation du trajet:', error);
      showNotification('Erreur de connexion', 'error');
    }
  };

  // Annuler un trajet
  window.cancelTrip = async function (tripId) {
    if (!confirm('Etes-vous sûr de vouloir annuler ce trajet ?')) return;

    try {
      const response = await fetch('../backend/trajets/annuler.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `trajet_id=${tripId}`,
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Trajet annulé', 'success');
        loadCurrentTrips(); // Recharger la liste
      } else {
        showNotification(
          data.message || "Erreur lors de l'annulation",
          'error'
        );
      }
    } catch (error) {
      console.error("Erreur lors de l'annulation du trajet:", error);
      showNotification('Erreur de connexion', 'error');
    }
  };

  // Supprimer un véhicule
  window.deleteVehicle = async function (vehicleId) {
    if (!confirm('Etes-vous sûr de vouloir supprimer ce véhicule ?')) return;

    try {
      const response = await fetch('../backend/vehicules/supprimer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `vehicule_id=${vehicleId}`,
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Véhicule supprimé', 'success');
        loadVehicles(); // Recharger la liste
      } else {
        showNotification(
          data.message || 'Erreur lors de la suppression',
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur lors de la suppression du véhicule:', error);
      showNotification('Erreur de connexion', 'error');
    }
  };

  // Modifier un véhicule
  window.editVehicle = function (vehicleId) {
    window.location.href = `add-vehicule.html?edit=${vehicleId}`;
  };

  // Afficher le modal des crédits
  function showCreditsModal() {
    // Simulation d'un modal de recharge (à implémenter)
    const amount = prompt('Montant à créditer (en euros) :');
    if (amount && !isNaN(amount) && amount > 0) {
      creditAccount(parseFloat(amount));
    }
  }

  // Créditer le compte
  async function creditAccount(amount) {
    try {
      const response = await fetch('../backend/credits/crediter.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `montant=${amount}`,
      });

      const data = await response.json();

      if (data.success) {
        showNotification(`${amount}€ ajoutés à votre compte !`, 'success');
        loadUserData(); // Recharger pour mettre à jour le solde
      } else {
        showNotification(data.message || 'Erreur lors du crédit', 'error');
      }
    } catch (error) {
      console.error('Erreur lors du crédit:', error);
      showNotification('Erreur de connexion', 'error');
    }
  }

  // Déconnexion
  async function logout() {
    if (!confirm('Etes-vous sûr de vouloir vous déconnecter ?')) return;

    try {
      await fetch('../backend/auth/logout.php');
      window.location.href = 'login.html';
    } catch (error) {
      console.error('Erreur lors de la déconnexion:', error);
      window.location.href = 'login.html';
    }
  }

  // Fonctions utilitaires
  function getRoleLabel(role) {
    const roles = {
      client: 'Utilisateur',
      employe: 'Employé',
      admin: 'Administrateur',
    };
    return roles[role] || role;
  }

  function getStatusLabel(status) {
    const statuses = {
      en_cours: 'En cours',
      en_attente: 'En attente',
      termine: 'Terminé',
      annule: 'Annulé',
    };
    return statuses[status] || status;
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }

  function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function showNotification(message, type = 'info') {
    // Créer une notification temporaire
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
        `;

    switch (type) {
      case 'success':
        notification.style.background = '#28a745';
        break;
      case 'error':
        notification.style.background = '#dc3545';
        break;
      default:
        notification.style.background = '#17a2b8';
    }

    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => {
        document.body.removeChild(notification);
      }, 300);
    }, 3000);
  }
});

// Animations CSS pour les notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(style);
