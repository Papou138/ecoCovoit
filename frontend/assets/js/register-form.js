document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('register-form');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirm-password');

  // Validation du mot de passe en temps réel
  passwordInput.addEventListener('input', function () {
    const password = this.value;
    const strength = checkPasswordStrength(password);
    updatePasswordStrength(strength);
  });

  // Validation de la confirmation du mot de passe
  confirmPasswordInput.addEventListener('input', function () {
    const password = passwordInput.value;
    const confirmPassword = this.value;

    if (confirmPassword && password !== confirmPassword) {
      this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
      this.setCustomValidity('');
    }
  });

  // Soumission du formulaire
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validateForm()) return;

    const submitBtn = document.getElementById('register-btn');
    const originalText = submitBtn.innerHTML;

    // Affichage du loading
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
    submitBtn.disabled = true;

    const formData = new FormData(form);

    fetch('../backend/auth/register.php', {
      method: 'POST',
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showMessage('Compte créé avec succès ! Redirection...', 'success');
          setTimeout(() => {
            window.location.href = 'login.html?registered=1';
          }, 2000);
        } else {
          showMessage(
            data.message || 'Erreur lors de la création du compte',
            'error'
          );
        }
      })
      .catch((error) => {
        console.error('Erreur:', error);
        showMessage('Erreur réseau. Veuillez réessayer.', 'error');
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
  });
});

function checkPasswordStrength(password) {
  let score = 0;
  let feedback = [];

  if (password.length >= 8) score++;
  else feedback.push('Au moins 8 caractères');

  if (/[a-z]/.test(password)) score++;
  else feedback.push('Une minuscule');

  if (/[A-Z]/.test(password)) score++;
  else feedback.push('Une majuscule');

  if (/\d/.test(password)) score++;
  else feedback.push('Un chiffre');

  if (/[^A-Za-z0-9]/.test(password)) score++;
  else feedback.push('Un caractère spécial');

  return { score, feedback };
}

function updatePasswordStrength(strength) {
  const indicator = document.getElementById('password-strength');
  const { score, feedback } = strength;

  let className = '';
  let text = '';

  switch (score) {
    case 0:
    case 1:
      className = 'weak';
      text = 'Très faible - ' + feedback.join(', ');
      break;
    case 2:
      className = 'weak';
      text = 'Faible - ' + feedback.join(', ');
      break;
    case 3:
      className = 'medium';
      text = 'Moyen - ' + feedback.join(', ');
      break;
    case 4:
      className = 'strong';
      text = 'Fort - ' + feedback.join(', ');
      break;
    case 5:
      className = 'very-strong';
      text = 'Très fort';
      break;
  }

  indicator.className = `password-strength ${className}`;
  indicator.textContent = text;
}

function validateForm() {
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirm-password').value;
  const terms = document.getElementById('terms').checked;

  if (password !== confirmPassword) {
    showMessage('Les mots de passe ne correspondent pas', 'error');
    return false;
  }

  if (!terms) {
    showMessage("Vous devez accepter les conditions d'utilisation", 'error');
    return false;
  }

  if (password.length < 8) {
    showMessage('Le mot de passe doit contenir au moins 8 caractères', 'error');
    return false;
  }

  return true;
}

function showMessage(text, type) {
  const messageBox = document.getElementById('register-message');
  messageBox.textContent = text;
  messageBox.className = `form-feedback ${type}`;
  messageBox.style.display = 'block';

  // Auto-hide après 5 secondes pour les messages de succès
  if (type === 'success') {
    setTimeout(() => {
      messageBox.style.display = 'none';
    }, 5000);
  }
}
