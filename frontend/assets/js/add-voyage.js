/**
 * Gestion des trajets - Ajout d'un nouveau voyage
 * Fonctionnalités pour publier un trajet de covoiturage
 */

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('trip-form');
  const submitBtn = form.querySelector('button[type="submit"]');
  const submitText = document.getElementById('submit-text');
  const feedback = document.getElementById('form-feedback');
  const cancelBtn = document.getElementById('cancel-btn');

  // Initialisation
  init();

  function init() {
    loadUserVehicles();
    setupEventListeners();
    setMinDate();
    setupCityAutocomplete();
    updateRecap();
  }

  // Définir la date minimum à aujourd'hui
  function setMinDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date_depart').min = today;
  }

  // Charger les véhicules de l'utilisateur
  async function loadUserVehicles() {
    try {
      const response = await fetch('../backend/vehicules/lister.php');
      const data = await response.json();

      const vehiculeSelect = document.getElementById('vehicule_id');
      const noVehicleMessage = document.querySelector('.no-vehicle-message');

      if (data.success && data.vehicules && data.vehicules.length > 0) {
        vehiculeSelect.innerHTML =
          '<option value="">Sélectionner un véhicule</option>';
        data.vehicules.forEach((vehicle) => {
          const option = document.createElement('option');
          option.value = vehicle.id;
          option.textContent = `${vehicle.marque} ${vehicle.modele} (${vehicle.couleur})`;
          option.dataset.places = vehicle.places;
          vehiculeSelect.appendChild(option);
        });

        // Mettre à jour les places disponibles quand on change de véhicule
        vehiculeSelect.addEventListener('change', updateAvailableSeats);
      } else {
        vehiculeSelect.style.display = 'none';
        noVehicleMessage.style.display = 'block';
      }
    } catch (error) {
      console.error('Erreur lors du chargement des véhicules:', error);
      showFeedback('Erreur lors du chargement des véhicules', 'error');
    }
  }

  // Mettre à jour les places disponibles selon le véhicule
  function updateAvailableSeats() {
    const vehiculeSelect = document.getElementById('vehicule_id');
    const placesSelect = document.getElementById('places_disponibles');

    if (vehiculeSelect.value) {
      const selectedOption =
        vehiculeSelect.options[vehiculeSelect.selectedIndex];
      const maxPlaces = parseInt(selectedOption.dataset.places) - 1; // -1 pour le conducteur

      placesSelect.innerHTML = '<option value="">Sélectionner</option>';
      for (let i = 1; i <= maxPlaces; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i === 1 ? '1 place' : `${i} places`;
        placesSelect.appendChild(option);
      }

      // Calculer le prix recommandé
      calculateRecommendedPrice();
    }
  }

  // Calculer le prix recommandé basé sur la distance
  function calculateRecommendedPrice() {
    const depart = document.getElementById('ville_depart').value;
    const arrivee = document.getElementById('ville_arrivee').value;

    if (depart && arrivee) {
      // Simulation du calcul (A remplacer par une vraie API de calcul de distance)
      const distance = Math.random() * 500 + 50; // Distance simulée entre 50 et 550 km
      const prixRecommande = Math.round(distance * 0.15 * 100) / 100; // 0.15€/km
      document.getElementById('prix-recommande').textContent =
        prixRecommande.toFixed(2);
    }
  }

  // Configuration de l'autocomplétion des villes
  function setupCityAutocomplete() {
    const cities = [
      'Paris',
      'Lyon',
      'Marseille',
      'Toulouse',
      'Nice',
      'Nantes',
      'Strasbourg',
      'Montpellier',
      'Bordeaux',
      'Lille',
      'Rennes',
      'Reims',
      'Le Havre',
      'Saint-Etienne',
      'Toulon',
      'Grenoble',
      'Dijon',
      'Angers',
      'Nîmes',
      'Villeurbanne',
    ];

    setupAutocomplete('ville_depart', cities);
    setupAutocomplete('ville_arrivee', cities);
  }

  function setupAutocomplete(inputId, cities) {
    const input = document.getElementById(inputId);
    const suggestions = document.getElementById(
      inputId.replace('ville_', '') + '-suggestions'
    );

    input.addEventListener('input', function () {
      const value = this.value.toLowerCase();
      suggestions.innerHTML = '';

      if (value.length > 1) {
        const filtered = cities
          .filter((city) => city.toLowerCase().includes(value))
          .slice(0, 5);

        filtered.forEach((city) => {
          const div = document.createElement('div');
          div.className = 'suggestion-item';
          div.textContent = city;
          div.addEventListener('click', () => {
            input.value = city;
            suggestions.innerHTML = '';
            calculateRecommendedPrice();
            updateRecap();
          });
          suggestions.appendChild(div);
        });
      }
    });

    // Fermer les suggestions en cliquant ailleurs
    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.innerHTML = '';
      }
    });
  }

  // Gestion des événements
  function setupEventListeners() {
    // Soumission du formulaire
    form.addEventListener('submit', handleSubmit);

    // Annulation
    cancelBtn.addEventListener('click', () => {
      if (
        confirm(
          'Etes-vous sûr de vouloir annuler ? Les informations saisies seront perdues.'
        )
      ) {
        window.location.href = 'index.html';
      }
    });

    // Mise à jour du récapitulatif en temps réel
    form.addEventListener('input', updateRecap);
    form.addEventListener('change', updateRecap);

    // Déconnexion
    document
      .querySelector('.logout-btn')
      .addEventListener('click', async (e) => {
        e.preventDefault();
        if (confirm('Etes-vous sûr de vouloir vous déconnecter ?')) {
          try {
            await fetch('../backend/auth/logout.php');
            window.location.href = 'login.html';
          } catch (error) {
            console.error('Erreur lors de la déconnexion:', error);
            window.location.href = 'login.html';
          }
        }
      });
  }

  // Mettre à jour le récapitulatif
  function updateRecap() {
    const recap = document.getElementById('trip-recap');
    const depart = document.getElementById('ville_depart').value;
    const arrivee = document.getElementById('ville_arrivee').value;
    const date = document.getElementById('date_depart').value;
    const heure = document.getElementById('heure_depart').value;
    const places = document.getElementById('places_disponibles').value;
    const prix = document.getElementById('prix').value;

    let html = '<div class="recap-grid">';

    if (depart && arrivee) {
      html += `
                <div class="recap-item">
                    <i class="fas fa-route"></i>
                    <span><strong>Trajet:</strong> ${depart} → ${arrivee}</span>
                </div>
            `;
    }

    if (date && heure) {
      const dateObj = new Date(date);
      const formattedDate = dateObj.toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
      });
      html += `
                <div class="recap-item">
                    <i class="fas fa-calendar-clock"></i>
                    <span><strong>Date:</strong> ${formattedDate} à ${heure}</span>
                </div>
            `;
    }

    if (places) {
      html += `
                <div class="recap-item">
                    <i class="fas fa-users"></i>
                    <span><strong>Places:</strong> ${places} passager${
        places > 1 ? 's' : ''
      }</span>
                </div>
            `;
    }

    if (prix) {
      html += `
                <div class="recap-item">
                    <i class="fas fa-euro-sign"></i>
                    <span><strong>Prix:</strong> ${prix}€ par personne</span>
                </div>
            `;
    }

    html += '</div>';
    recap.innerHTML =
      html ||
      '<p class="empty-recap">Complétez le formulaire pour voir le récapitulatif</p>';
  }

  // Soumission du formulaire
  async function handleSubmit(e) {
    e.preventDefault();

    if (!validateForm()) return;

    const formData = new FormData(form);

    // Animation de chargement
    const originalText = submitText.textContent;
    submitText.textContent = 'Publication...';
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> ' + submitText.textContent;
    submitBtn.disabled = true;

    try {
      const response = await fetch('../backend/trajets/ajouter.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        showFeedback('Trajet publié avec succès !', 'success');
        setTimeout(() => {
          window.location.href = 'user-profile.html';
        }, 2000);
      } else {
        showFeedback(data.message || 'Erreur lors de la publication', 'error');
      }
    } catch (error) {
      console.error('Erreur:', error);
      showFeedback('Erreur de connexion', 'error');
    } finally {
      submitBtn.innerHTML =
        '<i class="fas fa-paper-plane"></i> ' + originalText;
      submitText.textContent = originalText;
      submitBtn.disabled = false;
    }
  }

  // Validation du formulaire
  function validateForm() {
    let isValid = true;
    const requiredFields = [
      'ville_depart',
      'ville_arrivee',
      'date_depart',
      'heure_depart',
      'duree_estimee',
      'vehicule_id',
      'places_disponibles',
      'prix',
    ];

    requiredFields.forEach((fieldName) => {
      const field = document.getElementById(fieldName);
      if (!field.value.trim()) {
        showFieldError(field, 'Ce champ est obligatoire');
        isValid = false;
      } else {
        clearFieldError(field);
      }
    });

    // Validations spécifiques
    const dateDepart = document.getElementById('date_depart');
    if (dateDepart.value) {
      const selectedDate = new Date(dateDepart.value);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (selectedDate < today) {
        showFieldError(dateDepart, 'La date ne peut pas être dans le passé');
        isValid = false;
      }
    }

    const prix = document.getElementById('prix');
    if (
      prix.value &&
      (parseFloat(prix.value) < 0 || parseFloat(prix.value) > 100)
    ) {
      showFieldError(prix, 'Le prix doit être entre 0 et 100 €');
      isValid = false;
    }

    return isValid;
  }

  function showFieldError(field, message) {
    field.classList.add('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
      errorDiv.textContent = message;
      errorDiv.style.display = 'block';
    }
  }

  function clearFieldError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
      errorDiv.style.display = 'none';
    }
  }

  function showFeedback(message, type) {
    feedback.textContent = message;
    feedback.className = `form-feedback ${type}`;
    feedback.style.display = 'block';

    if (type === 'success') {
      feedback.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    } else {
      feedback.innerHTML =
        '<i class="fas fa-exclamation-triangle"></i> ' + message;
    }
  }
});

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
