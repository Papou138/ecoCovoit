/**
 * Gestion des préférences utilisateur
 * Fonctionnalités pour configurer les préférences de covoiturage
 */

let notifications = {
  nouvelles_demandes: false,
  confirmations: false,
  rappels: true,
  newsletter: false,
};

document.addEventListener('DOMContentLoaded', function () {
  loadPreferences();
  setupEventListeners();
});

function loadPreferences() {
  // Simulation du chargement des préférences depuis le serveur
  // En réalité, ces données viendraient d'une API
  setTimeout(() => {
    // Exemple de préférences pré-chargées
    document.getElementById('musique').checked = true;
    document.getElementById('conversation').checked = true;
    document.getElementById('climatisation').checked = true;
  }, 300);
}

function setupEventListeners() {
  const form = document.getElementById('preferences-form');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    savePreferences();
  });

  // Compteur de caractères pour le textarea
  const textarea = document.getElementById('autres');
  const maxLength = textarea.getAttribute('maxlength');

  // Création d'un compteur de caractères
  const charCounter = document.createElement('div');
  charCounter.className = 'char-counter';
  charCounter.textContent = `0/${maxLength} caractères`;
  textarea.parentNode.appendChild(charCounter);

  textarea.addEventListener('input', function () {
    const currentLength = this.value.length;
    charCounter.textContent = `${currentLength}/${maxLength} caractères`;

    // Changement de couleur si proche de la limite
    if (currentLength > maxLength * 0.8) {
      charCounter.style.color = 'var(--color-warning)';
    } else {
      charCounter.style.color = 'var(--color-text-secondary)';
    }
  });

  // Amélioration de l'accessibilité des toggles
  const toggles = document.querySelectorAll('.toggle-switch');
  toggles.forEach((toggle) => {
    toggle.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  });
}

function toggleNotification(toggle, type) {
  toggle.classList.toggle('active');
  const isActive = toggle.classList.contains('active');
  notifications[type] = isActive;

  // Mise à jour de l'attribut aria-checked pour l'accessibilité
  toggle.setAttribute('aria-checked', isActive);
}

function savePreferences() {
  const submitBtn = document.getElementById('save-btn');
  const originalText = submitBtn.innerHTML;

  // Validation basique
  const textarea = document.getElementById('autres');
  if (textarea.value.length > 500) {
    showMessage(
      'Le texte des préférences personnalisées est trop long (maximum 500 caractères).',
      'error'
    );
    return;
  }

  // Affichage du loading
  submitBtn.innerHTML =
    '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
  submitBtn.disabled = true;

  const formData = new FormData();

  // Collecter les préférences de voyage
  const checkboxes = document.querySelectorAll(
    'input[type="checkbox"]:not([type="hidden"])'
  );
  checkboxes.forEach((checkbox) => {
    formData.append(checkbox.name, checkbox.checked ? '1' : '0');
  });

  // Ajouter les préférences personnalisées
  formData.append('autres', textarea.value.trim());

  // Ajouter les notifications
  Object.keys(notifications).forEach((key) => {
    formData.append(
      `notification_${key}`,
      notifications[key] ? '1' : '0'
    );
  });

  // Simulation d'un délai de réseau pour améliorer l'UX
  setTimeout(() => {
    fetch('../backend/preferences/enregistrer.php', {
      method: 'POST',
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showMessage(
            'préférences sauvegardées avec succès !',
            'success'
          );

          // Effet visuel de confirmation
          submitBtn.style.background =
            'linear-gradient(135deg, var(--color-success), var(--color-success-dark))';
          setTimeout(() => {
            submitBtn.style.background = '';
          }, 2000);
        } else {
          showMessage(
            data.message ||
              'Erreur lors de la sauvegarde. Veuillez réessayer.',
            'error'
          );
        }
      })
      .catch((error) => {
        console.error('Erreur:', error);
        showMessage(
          'Erreur de connexion. Vérifiez votre connexion internet et réessayez.',
          'error'
        );
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
  }, 800);
}

function showMessage(text, type) {
  const messageBox = document.getElementById('preferences-message');
  messageBox.textContent = text;
  messageBox.className = `message-box ${type} visible`;

  // Auto-hide après 4 secondes pour les messages de succès
  if (type === 'success') {
    setTimeout(() => {
      messageBox.classList.remove('visible');
    }, 4000);
  }
}

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
