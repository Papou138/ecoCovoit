/**
 * Gestion des véhicules - Ajout et modification
 * Fonctionnalités pour ajouter ou modifier un véhicule
 */

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('vehicle-form');
  const submitBtn = form.querySelector('button[type="submit"]');
  const submitText = document.getElementById('submit-text');
  const feedback = document.getElementById('form-feedback');
  const cancelBtn = document.getElementById('cancel-btn');

  // Vérifier si on est en mode édition
  const urlParams = new URLSearchParams(window.location.search);
  const editId = urlParams.get('edit');

  if (editId) {
    document.getElementById('form-title').textContent =
      'Modifier le véhicule';
    submitText.textContent = 'Mettre à jour le véhicule';
    loadVehicleData(editId);
  }

  // Gestion du formulaire
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!validateForm()) return;

    const formData = new FormData(form);
    if (editId) {
      formData.append('vehicule_id', editId);
    }

    // Animation de chargement
    const originalText = submitText.textContent;
    submitText.textContent = editId
      ? 'Mise à jour...'
      : 'Enregistrement...';
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> ' + submitText.textContent;
    submitBtn.disabled = true;

    try {
      const response = await fetch('../backend/vehicules/ajouter.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        showFeedback(
          editId
            ? 'véhicule mis à jour avec succès !'
            : 'véhicule ajouté avec succès !',
          'success'
        );
        setTimeout(() => {
          window.location.href = 'user-profile.html';
        }, 2000);
      } else {
        showFeedback(
          data.message || "Erreur lors de l'enregistrement",
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur:', error);
      showFeedback('Erreur de connexion', 'error');
    } finally {
      submitBtn.innerHTML = '<i class="fas fa-save"></i> ' + originalText;
      submitText.textContent = originalText;
      submitBtn.disabled = false;
    }
  });

  // Bouton annuler
  cancelBtn.addEventListener('click', function () {
    if (
      confirm(
        'Etes-vous sûr de vouloir annuler ? Les modifications ne seront pas sauvegardées.'
      )
    ) {
      window.location.href = 'user-profile.html';
    }
  });

  // Validation de l'immatriculation en temps réel
  document
    .getElementById('immatriculation')
    .addEventListener('input', function (e) {
      let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

      if (value.length > 2 && value.length <= 5) {
        value = value.slice(0, 2) + '-' + value.slice(2);
      } else if (value.length > 5) {
        value =
          value.slice(0, 2) +
          '-' +
          value.slice(2, 5) +
          '-' +
          value.slice(5, 7);
      }

      e.target.value = value;
    });

  // Déconnexion
  document
    .querySelector('.logout-btn')
    .addEventListener('click', async function (e) {
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

  // Charger les données du véhicule en mode édition
  async function loadVehicleData(vehicleId) {
    try {
      const response = await fetch(
        `../backend/vehicules/detail.php?id=${vehicleId}`
      );
      const data = await response.json();

      if (data.success && data.vehicule) {
        const vehicle = data.vehicule;
        document.getElementById('marque').value = vehicle.marque;
        document.getElementById('modele').value = vehicle.modele;
        document.getElementById('couleur').value = vehicle.couleur;
        document.getElementById('immatriculation').value =
          vehicle.immatriculation;
        document.getElementById('places').value = vehicle.places;
        document.getElementById('energie').value = vehicle.energie;
        document.getElementById('date_immatriculation').value =
          vehicle.date_immatriculation;
      }
    } catch (error) {
      console.error('Erreur lors du chargement du véhicule:', error);
      showFeedback('Erreur lors du chargement des données', 'error');
    }
  }

  // Validation du formulaire
  function validateForm() {
    let isValid = true;
    const requiredFields = [
      'marque',
      'modele',
      'couleur',
      'immatriculation',
      'places',
      'energie',
      'date_immatriculation',
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

    // Validation spécifique de l'immatriculation
    const immat = document.getElementById('immatriculation');
    const immatPattern = /^[A-Z]{2}-\d{3}-[A-Z]{2}$/;
    if (immat.value && !immatPattern.test(immat.value)) {
      showFieldError(immat, 'Format invalide (ex: AB-123-CD)');
      isValid = false;
    }

    // Validation de la date
    const dateImmat = document.getElementById('date_immatriculation');
    if (dateImmat.value) {
      const selectedDate = new Date(dateImmat.value);
      const today = new Date();
      if (selectedDate > today) {
        showFieldError(
          dateImmat,
          'La date ne peut pas être dans le futur'
        );
        isValid = false;
      }
    }

    return isValid;
  }

  function showFieldError(field, message) {
    field.classList.add('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
  }

  function clearFieldError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    errorDiv.style.display = 'none';
  }

  function showFeedback(message, type) {
    feedback.textContent = message;
    feedback.className = `form-feedback ${type}`;
    feedback.style.display = 'block';

    if (type === 'success') {
      feedback.innerHTML =
        '<i class="fas fa-check-circle"></i> ' + message;
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
