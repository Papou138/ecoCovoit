/**
 * Gestion du formulaire de contact
 * Validation et soumission des messages de contact
 */

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  const submitBtn = form.querySelector('button[type="submit"]');
  const nameField = document.getElementById('name');
  const emailField = document.getElementById('email');
  const subjectField = document.getElementById('subject');
  const messageField = document.getElementById('message');

  // Validation en temps réel
  if (emailField) {
    emailField.addEventListener('blur', validateEmail);
  }

  if (messageField) {
    messageField.addEventListener('input', updateCharacterCount);
  }

  // Soumission du formulaire
  form.addEventListener('submit', handleSubmit);

  function validateEmail() {
    const email = emailField.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
      showFieldError(emailField, 'Format d\'email invalide');
    } else {
      clearFieldError(emailField);
    }
  }

  function updateCharacterCount() {
    const maxLength = messageField.getAttribute('maxlength') || 1000;
    const currentLength = messageField.value.length;
    
    let counter = document.getElementById('char-counter');
    if (!counter) {
      counter = document.createElement('div');
      counter.id = 'char-counter';
      counter.className = 'char-counter';
      messageField.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} caractères`;
    
    if (currentLength > maxLength * 0.9) {
      counter.style.color = '#ff4757';
    } else if (currentLength > maxLength * 0.7) {
      counter.style.color = '#ffa726';
    } else {
      counter.style.color = '#666';
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();

    if (!validateForm()) return;

    const formData = new FormData(form);

    // Animation de chargement
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
    submitBtn.disabled = true;

    try {
      const response = await fetch('../backend/contact/envoyer.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Message envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.', 'success');
        form.reset();
        updateCharacterCount();
      } else {
        showNotification(data.message || 'Erreur lors de l\'envoi du message', 'error');
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
    const requiredFields = [
      { field: nameField, message: 'Le nom est obligatoire' },
      { field: emailField, message: 'L\'email est obligatoire' },
      { field: subjectField, message: 'Le sujet est obligatoire' },
      { field: messageField, message: 'Le message est obligatoire' }
    ];

    requiredFields.forEach(({ field, message }) => {
      if (field && !field.value.trim()) {
        showFieldError(field, message);
        isValid = false;
      } else if (field) {
        clearFieldError(field);
      }
    });

    // Validation de l'email
    if (emailField && emailField.value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(emailField.value)) {
        showFieldError(emailField, 'Format d\'email invalide');
        isValid = false;
      }
    }

    // Validation de la longueur du message
    if (messageField && messageField.value.trim().length < 10) {
      showFieldError(messageField, 'Le message doit contenir au moins 10 caractères');
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

  // Initialisation
  if (messageField) {
    updateCharacterCount();
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
  
  let icon = 'info-circle';
  if (type === 'success') icon = 'check-circle';
  if (type === 'error') icon = 'exclamation-triangle';
  
  notification.innerHTML = `
    <i class="fas fa-${icon}"></i>
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
