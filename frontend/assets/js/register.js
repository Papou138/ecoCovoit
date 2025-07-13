/**
 * Gestion du formulaire d'inscription
 * Validation et soumission du formulaire d'inscription utilisateur
 */

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('register-form');
  if (!form) return; // Si pas de formulaire, pas besoin d'initialiser

  const submitBtn = form.querySelector('button[type="submit"]');
  const passwordField = document.getElementById('register-password');
  const passwordConfirmField = document.getElementById('register-password-confirm');
  const emailField = document.getElementById('register-email');

  // Validation en temps réel du mot de passe
  if (passwordField) {
    passwordField.addEventListener('input', validatePasswordStrength);
  }

  // Validation de la confirmation du mot de passe
  if (passwordConfirmField) {
    passwordConfirmField.addEventListener('input', validatePasswordMatch);
  }

  // Validation de l'email
  if (emailField) {
    emailField.addEventListener('blur', validateEmail);
  }

  // Soumission du formulaire
  if (form) {
    form.addEventListener('submit', handleSubmit);
  }

  function validatePasswordStrength() {
    const password = passwordField.value;
    const strengthIndicator = document.getElementById('password-strength');
    
    if (!strengthIndicator) return;

    let strength = 0;
    let message = '';
    let color = '';

    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    switch (strength) {
      case 0:
      case 1:
        message = 'Très faible';
        color = '#ff4757';
        break;
      case 2:
        message = 'Faible';
        color = '#ff6b47';
        break;
      case 3:
        message = 'Moyen';
        color = '#ffa726';
        break;
      case 4:
        message = 'Fort';
        color = '#66bb6a';
        break;
      case 5:
        message = 'Très fort';
        color = '#4caf50';
        break;
    }

    strengthIndicator.textContent = message;
    strengthIndicator.style.color = color;
  }

  function validatePasswordMatch() {
    const password = passwordField.value;
    const confirmPassword = passwordConfirmField.value;
    const matchIndicator = document.getElementById('password-match');

    if (!matchIndicator) return;

    if (confirmPassword === '') {
      matchIndicator.textContent = '';
      return;
    }

    if (password === confirmPassword) {
      matchIndicator.textContent = '✓ Les mots de passe correspondent';
      matchIndicator.style.color = '#4caf50';
    } else {
      matchIndicator.textContent = '✗ Les mots de passe ne correspondent pas';
      matchIndicator.style.color = '#ff4757';
    }
  }

  function validateEmail() {
    const email = emailField.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
      showFieldError(emailField, 'Format d\'email invalide');
    } else {
      clearFieldError(emailField);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();

    if (!validateForm()) return;

    const formData = new FormData(form);

    // Animation de chargement
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inscription...';
    submitBtn.disabled = true;

    try {
      const response = await fetch('../backend/auth/register.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Inscription réussie ! Redirection vers la connexion...', 'success');
        setTimeout(() => {
          window.location.href = 'login.html';
        }, 2000);
      } else {
        showNotification(data.message || 'Erreur lors de l\'inscription', 'error');
      }
    } catch (error) {
      console.error('Erreur:', error);
      showNotification('Erreur de connexion', 'error');
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  function validateForm() {
    let isValid = true;
    const requiredFields = ['nom', 'prenom', 'email', 'password', 'password-confirm'];

    requiredFields.forEach(fieldName => {
      const field = document.getElementById('register-' + fieldName) || document.getElementById(fieldName);
      if (field && !field.value.trim()) {
        showFieldError(field, 'Ce champ est obligatoire');
        isValid = false;
      } else if (field) {
        clearFieldError(field);
      }
    });

    // Validation spécifique des mots de passe
    const password = passwordField.value;
    const confirmPassword = passwordConfirmField.value;

    if (password.length < 8) {
      showFieldError(passwordField, 'Le mot de passe doit contenir au moins 8 caractères');
      isValid = false;
    }

    if (password !== confirmPassword) {
      showFieldError(passwordConfirmField, 'Les mots de passe ne correspondent pas');
      isValid = false;
    }

    return isValid;
  }

  function showFieldError(field, message) {
    field.classList.add('error');
    let errorDiv = field.parentNode.querySelector('.error-message');
    if (!errorDiv) {
      errorDiv = document.createElement('div');
      errorDiv.className = 'error-message';
      field.parentNode.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
  }

  function clearFieldError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
      errorDiv.style.display = 'none';
    }
  }
});

// Fonction pour afficher les notifications
function showNotification(message, type = 'info') {
  let container = document.getElementById('notification-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notification-container';
    container.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
    `;
    document.body.appendChild(container);
  }

  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
    <span>${message}</span>
    <button onclick="this.parentElement.remove()" style="margin-left: 10px;">&times;</button>
  `;
  
  container.appendChild(notification);

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}
