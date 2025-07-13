// Variables globales
let selectedPhotos = [];
let ratings = {
  main: 0,
  ponctualite: 0,
  conduite: 0,
  communication: 0,
  confort: 0,
};

// Initialisation
document.addEventListener('DOMContentLoaded', function () {
  initializeAvisForm();
  loadTrajetInfo();
  setupEventListeners();
});

function initializeAvisForm() {
  // Récupérer l'ID du trajet depuis l'URL
  const urlParams = new URLSearchParams(window.location.search);
  const trajetId = urlParams.get('trajet_id');

  if (!trajetId) {
    showMessage('Aucun trajet spécifié. Redirection...', 'error');
    setTimeout(() => {
      window.location.href = 'historique.html';
    }, 2000);
    return;
  }

  document.getElementById('trajet_id').value = trajetId;
}

function loadTrajetInfo() {
  const trajetId = document.getElementById('trajet_id').value;
  if (!trajetId) return;

  // Simulation du chargement des détails du trajet
  setTimeout(() => {
    // Cacher le loading et afficher le contenu
    document.getElementById('trajet-loading').style.display = 'none';
    document.getElementById('trajet-content').style.display = 'block';

    // Mettre à jour les valeurs (simulation avec données d'exemple)
    document.getElementById('depart-value').textContent = 'Paris';
    document.getElementById('arrivee-value').textContent = 'Lyon';
    document.getElementById('date-value').textContent = '15 janvier 2024';
    document.getElementById('chauffeur-value').textContent = 'Marie Dupont';
    document.getElementById('prix-value').textContent = '35 €';
    document.getElementById('duree-value').textContent = '4h30';
  }, 1000);
}

function setupEventListeners() {
  // Gestion des étoiles
  setupStarRatings();

  // Compteur de caractères
  const textarea = document.getElementById('commentaire');
  const charCount = document.getElementById('char-count');

  textarea.addEventListener('input', function () {
    charCount.textContent = this.value.length;
  });

  // Gestion des photos
  document
    .getElementById('photos')
    .addEventListener('change', handlePhotoUpload);

  // Soumission du formulaire
  document.getElementById('avis-form').addEventListener('submit', submitAvis);
}

function setupStarRatings() {
  // Rating principal
  const mainStars = document.querySelectorAll('#main-rating .star');
  setupStarGroup(mainStars, 'main');

  // Ratings des critères
  document.querySelectorAll('.criteria-rating').forEach((criteriaGroup) => {
    const stars = criteriaGroup.querySelectorAll('.star');
    const criteriaName = criteriaGroup.dataset.criteria;
    setupStarGroup(stars, criteriaName);
  });
}

function setupStarGroup(stars, ratingType) {
  stars.forEach((star, index) => {
    star.addEventListener('click', () => {
      const rating = parseInt(star.dataset.rating);
      setRating(ratingType, rating);
      updateStarDisplay(stars, rating);
    });

    star.addEventListener('mouseenter', () => {
      const rating = parseInt(star.dataset.rating);
      highlightStars(stars, rating);
    });
  });

  // Réinitialiser la surbrillance
  const container = stars[0].parentElement;
  container.addEventListener('mouseleave', () => {
    const currentRating = ratings[ratingType];
    updateStarDisplay(stars, currentRating);
  });
}

function setRating(type, rating) {
  ratings[type] = rating;

  if (type === 'main') {
    document.getElementById('note').value = rating;
  } else {
    const input = document.querySelector(`input[name="${type}"]`);
    if (input) input.value = rating;
  }
}

function updateStarDisplay(stars, rating) {
  stars.forEach((star, index) => {
    star.classList.toggle('active', index < rating);
  });
}

function highlightStars(stars, rating) {
  stars.forEach((star, index) => {
    star.style.color = index < rating ? '#f39c12' : '#ddd';
  });
}

function handlePhotoUpload(event) {
  const files = Array.from(event.target.files);
  const preview = document.getElementById('photo-preview');

  files.forEach((file) => {
    if (selectedPhotos.length >= 5) {
      showMessage('Maximum 5 photos autorisées', 'error');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      showMessage('Fichier trop volumineux (max 5MB)', 'error');
      return;
    }

    selectedPhotos.push(file);

    const reader = new FileReader();
    reader.onload = function (e) {
      const photoItem = document.createElement('div');
      photoItem.className = 'photo-item';
      photoItem.innerHTML = `
                        <img src="${e.target.result}" alt="Photo du trajet">
                        <button type="button" class="photo-remove" onclick="removePhoto(${
                          selectedPhotos.length - 1
                        })">-</button>
                    `;
      preview.appendChild(photoItem);
    };
    reader.readAsDataURL(file);
  });
}

function removePhoto(index) {
  selectedPhotos.splice(index, 1);
  const preview = document.getElementById('photo-preview');
  preview.children[index].remove();
}

function submitAvis(event) {
  event.preventDefault();

  if (!validateForm()) return;

  const submitBtn = document.getElementById('submit-btn');
  const originalText = submitBtn.textContent;

  // Affichage du loading
  submitBtn.innerHTML = '<span class="spinner"></span>Envoi en cours...';
  submitBtn.disabled = true;
  document.querySelector('.avis-form').classList.add('loading');

  const formData = new FormData();
  formData.append('trajet_id', document.getElementById('trajet_id').value);
  formData.append('note', document.getElementById('note').value);
  formData.append('commentaire', document.getElementById('commentaire').value);

  // Ajout des ratings détaillés
  Object.keys(ratings).forEach((key) => {
    if (key !== 'main' && ratings[key] > 0) {
      formData.append(key, ratings[key]);
    }
  });

  // Ajout des photos
  selectedPhotos.forEach((photo, index) => {
    formData.append(`photos[${index}]`, photo);
  });

  fetch('../backend/avis/enregistrer.php', {
    method: 'POST',
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage('Votre avis a été publié avec succès !', 'success');
        setTimeout(() => {
          window.location.href = 'historique.html';
        }, 2000);
      } else {
        showMessage(
          data.message || "Erreur lors de l'envoi de l'avis",
          'error'
        );
      }
    })
    .catch((error) => {
      console.error('Erreur:', error);
      showMessage('Erreur réseau. Veuillez réessayer.', 'error');
    })
    .finally(() => {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
      document.querySelector('.avis-form').classList.remove('loading');
    });
}

function validateForm() {
  const note = document.getElementById('note').value;
  const commentaire = document.getElementById('commentaire').value.trim();

  if (!note) {
    showMessage('Veuillez donner une note générale', 'error');
    return false;
  }

  if (!commentaire) {
    showMessage('Veuillez rédiger un commentaire', 'error');
    return false;
  }

  if (commentaire.length < 10) {
    showMessage('Le commentaire doit contenir au moins 10 caractères', 'error');
    return false;
  }

  return true;
}

function showMessage(text, type) {
  const messageBox = document.getElementById('avis-message');
  messageBox.textContent = text;
  messageBox.className = `message-box ${type}`;
  messageBox.classList.remove('hidden');

  // Auto-hide après 5 secondes pour les messages de succès
  if (type === 'success') {
    setTimeout(() => {
      messageBox.classList.add('hidden');
    }, 5000);
  }
}
