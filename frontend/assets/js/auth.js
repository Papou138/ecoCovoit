/**
 * Gestion de l'authentification (connexion et inscription)
 * US7 - Création de compte avec validation de mot de passe sécurisé
 */

class AuthManager {
  constructor() {
    this.loginTab = document.getElementById('login-tab');
    this.registerTab = document.getElementById('register-tab');
    this.loginContainer = document.getElementById('login-form-container');
    this.registerContainer = document.getElementById('register-form-container');

    this.loginForm = document.getElementById('login-form');
    this.registerForm = document.getElementById('register-form');

    this.loginMessage = document.getElementById('login-message');
    this.registerMessage = document.getElementById('register-message');

    this.passwordField = document.getElementById('register-password');
    this.passwordConfirmField = document.getElementById(
      'register-password-confirm'
    );
    this.passwordStrength = document.getElementById('password-strength');
    this.passwordMatch = document.getElementById('password-match');

    this.initEventListeners();
    this.initPasswordToggles();
  }

  initEventListeners() {
    // Onglets
    this.loginTab.addEventListener('click', () => this.switchToLogin());
    this.registerTab.addEventListener('click', () => this.switchToRegister());

    // Formulaires
    this.loginForm.addEventListener('submit', (e) => this.handleLogin(e));
    this.registerForm.addEventListener('submit', (e) => this.handleRegister(e));

    // Validation en temps réel
    this.passwordField.addEventListener('input', () =>
      this.checkPasswordStrength()
    );
    this.passwordConfirmField.addEventListener('input', () =>
      this.checkPasswordMatch()
    );

    // Vérification de l'URL pour auto-switch
    this.checkUrlParams();
  }

  initPasswordToggles() {
    const toggles = document.querySelectorAll('.password-toggle');

    toggles.forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const input = toggle.previousElementSibling;
        const icon = toggle.querySelector('i');

        if (input.type === 'password') {
          input.type = 'text';
          icon.className = 'fas fa-eye-slash';
        } else {
          input.type = 'password';
          icon.className = 'fas fa-eye';
        }
      });
    });
  }

  checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    if (tab === 'register') {
      this.switchToRegister();
    }
  }

  switchToLogin() {
    this.loginTab.classList.add('active');
    this.registerTab.classList.remove('active');
    this.loginContainer.classList.add('active');
    this.registerContainer.classList.remove('active');
    this.clearMessages();
  }

  switchToRegister() {
    this.registerTab.classList.add('active');
    this.loginTab.classList.remove('active');
    this.registerContainer.classList.add('active');
    this.loginContainer.classList.remove('active');
    this.clearMessages();
  }

  clearMessages() {
    this.loginMessage.className = 'auth-message';
    this.registerMessage.className = 'auth-message';
  }

  async handleLogin(e) {
    e.preventDefault();

    const formData = new FormData(this.loginForm);
    const email = formData.get('email');
    const password = formData.get('password');
    const remember = formData.get('remember') === 'on';

    // Validation basique
    if (!this.validateEmail(email)) {
      this.showMessage(
        this.loginMessage,
        'Veuillez entrer un email valide',
        'error'
      );
      return;
    }

    if (password.length < 3) {
      this.showMessage(
        this.loginMessage,
        'Le mot de passe est requis',
        'error'
      );
      return;
    }

    try {
      this.setLoading(this.loginForm, true);

      // Simulation d'appel API - à remplacer par le vrai backend
      const result = await this.performLogin(email, password, remember);

      if (result.success) {
        // Stocker les informations de session
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userEmail', email);
        localStorage.setItem('userCredits', result.credits || 40);
        localStorage.setItem('userRole', result.role || 'user');
        localStorage.setItem('userName', result.name || email.split('@')[0]);

        if (remember) {
          localStorage.setItem('rememberMe', 'true');
        }

        this.showMessage(
          this.loginMessage,
          'Connexion réussie ! Redirection...',
          'success'
        );

        // Redirection
        setTimeout(() => {
          const redirectUrl =
            localStorage.getItem('redirectAfterLogin') || 'user-profile.html';
          localStorage.removeItem('redirectAfterLogin');
          window.location.href = redirectUrl;
        }, 1500);
      } else {
        this.showMessage(
          this.loginMessage,
          result.message || 'Email ou mot de passe incorrect',
          'error'
        );
      }
    } catch (error) {
      console.error('Erreur de connexion:', error);
      this.showMessage(
        this.loginMessage,
        'Une erreur est survenue. Veuillez réessayer.',
        'error'
      );
    } finally {
      this.setLoading(this.loginForm, false);
    }
  }

  async handleRegister(e) {
    e.preventDefault();

    const formData = new FormData(this.registerForm);
    const pseudo = formData.get('pseudo').trim();
    const email = formData.get('email').trim();
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');
    const acceptTerms = formData.get('terms') === 'on';

    // Validations
    if (
      !this.validateRegisterForm(
        pseudo,
        email,
        password,
        passwordConfirm,
        acceptTerms
      )
    ) {
      return;
    }

    try {
      this.setLoading(this.registerForm, true);

      // Simulation d'appel API - à remplacer par le vrai backend
      const result = await this.performRegister(pseudo, email, password);

      if (result.success) {
        this.showMessage(
          this.registerMessage,
          'Compte créé avec succès ! Vous pouvez maintenant vous connecter.',
          'success'
        );

        // Auto-remplir le formulaire de connexion et switcher
        setTimeout(() => {
          document.getElementById('login-email').value = email;
          this.switchToLogin();
          this.showMessage(
            this.loginMessage,
            'Votre compte a été créé avec 20 crédits offerts !',
            'info'
          );
        }, 2000);
      } else {
        this.showMessage(
          this.registerMessage,
          result.message || 'Erreur lors de la création du compte',
          'error'
        );
      }
    } catch (error) {
      console.error("Erreur d'inscription:", error);
      this.showMessage(
        this.registerMessage,
        'Une erreur est survenue. Veuillez réessayer.',
        'error'
      );
    } finally {
      this.setLoading(this.registerForm, false);
    }
  }

  validateRegisterForm(pseudo, email, password, passwordConfirm, acceptTerms) {
    // Pseudo
    if (pseudo.length < 3 || pseudo.length > 20) {
      this.showMessage(
        this.registerMessage,
        'Le pseudo doit contenir entre 3 et 20 caractères',
        'error'
      );
      return false;
    }

    if (!/^[a-zA-Z0-9_-]+$/.test(pseudo)) {
      this.showMessage(
        this.registerMessage,
        'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores',
        'error'
      );
      return false;
    }

    // Email
    if (!this.validateEmail(email)) {
      this.showMessage(
        this.registerMessage,
        'Veuillez entrer un email valide',
        'error'
      );
      return false;
    }

    // Mot de passe
    if (!this.isPasswordSecure(password)) {
      this.showMessage(
        this.registerMessage,
        'Le mot de passe ne respecte pas les critères de sécurité',
        'error'
      );
      return false;
    }

    // Confirmation mot de passe
    if (password !== passwordConfirm) {
      this.showMessage(
        this.registerMessage,
        'Les mots de passe ne correspondent pas',
        'error'
      );
      return false;
    }

    // Conditions d'utilisation
    if (!acceptTerms) {
      this.showMessage(
        this.registerMessage,
        "Vous devez accepter les conditions d'utilisation",
        'error'
      );
      return false;
    }

    return true;
  }

  validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  isPasswordSecure(password) {
    if (password.length < 8) return false;

    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    return hasLower && hasUpper && hasNumber && hasSpecial;
  }

  checkPasswordStrength() {
    const password = this.passwordField.value;

    if (password.length === 0) {
      this.passwordStrength.className = 'password-strength';
      this.passwordStrength.innerHTML = '';
      return;
    }

    let strength = 0;
    let feedback = [];

    // Critères de force
    if (password.length >= 8) strength++;
    else feedback.push('Au moins 8 caractères');

    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Au moins 1 minuscule');

    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Au moins 1 majuscule');

    if (/\d/.test(password)) strength++;
    else feedback.push('Au moins 1 chiffre');

    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('Au moins 1 caractère spécial');

    // Affichage
    let strengthClass = '';
    let strengthText = '';

    if (strength <= 2) {
      strengthClass = 'weak';
      strengthText = 'Faible';
    } else if (strength <= 4) {
      strengthClass = 'medium';
      strengthText = 'Moyen';
    } else {
      strengthClass = 'strong';
      strengthText = 'Fort';
    }

    this.passwordStrength.className = `password-strength ${strengthClass}`;
    this.passwordStrength.innerHTML = `
            <div class="password-strength-bar"></div>
            <div style="font-size: var(--font-size-xs); margin-top: var(--space-xs); color: var(--color-text-secondary);">
                Force: ${strengthText}
                ${
                  feedback.length > 0 ? ' - Manque: ' + feedback.join(', ') : ''
                }
            </div>
        `;
  }

  checkPasswordMatch() {
    const password = this.passwordField.value;
    const passwordConfirm = this.passwordConfirmField.value;

    if (passwordConfirm.length === 0) {
      this.passwordMatch.textContent = '';
      this.passwordMatch.className = 'password-match';
      return;
    }

    if (password === passwordConfirm) {
      this.passwordMatch.textContent = '✓ Les mots de passe correspondent';
      this.passwordMatch.className = 'password-match match';
    } else {
      this.passwordMatch.textContent =
        '✗ Les mots de passe ne correspondent pas';
      this.passwordMatch.className = 'password-match no-match';
    }
  }

  async performLogin(email, password, remember) {
    // Simulation d'appel API - à remplacer par le vrai backend
    return new Promise((resolve) => {
      setTimeout(() => {
        // Simulation de données utilisateur
        const mockUsers = [
          {
            email: 'marie@example.com',
            password: 'password123',
            name: 'Marie D.',
            credits: 45,
            role: 'user',
          },
          {
            email: 'admin@ecoride.fr',
            password: 'admin123',
            name: 'Admin',
            credits: 999,
            role: 'admin',
          },
          {
            email: 'employe@ecoride.fr',
            password: 'employe123',
            name: 'Employé',
            credits: 100,
            role: 'employee',
          },
        ];

        const user = mockUsers.find(
          (u) => u.email === email && u.password === password
        );

        if (user) {
          resolve({
            success: true,
            name: user.name,
            credits: user.credits,
            role: user.role,
          });
        } else {
          resolve({
            success: false,
            message: 'Email ou mot de passe incorrect',
          });
        }
      }, 1000);
    });
  }

  async performRegister(pseudo, email, password) {
    // Simulation d'appel API - à remplacer par le vrai backend
    return new Promise((resolve) => {
      setTimeout(() => {
        // Vérification d'email unique (simulation)
        const existingEmails = ['marie@example.com', 'admin@ecoride.fr'];

        if (existingEmails.includes(email)) {
          resolve({
            success: false,
            message: 'Cet email est déjà utilisé',
          });
        } else {
          resolve({
            success: true,
            message: 'Compte créé avec succès',
          });
        }
      }, 1500);
    });
  }

  showMessage(element, message, type) {
    element.textContent = message;
    element.className = `auth-message ${type} show`;

    // Auto-masquer après 5 secondes sauf pour les succès
    if (type !== 'success') {
      setTimeout(() => {
        element.classList.remove('show');
      }, 5000);
    }
  }

  setLoading(form, isLoading) {
    const submitBtn = form.querySelector('button[type="submit"]');

    if (isLoading) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i>Traitement...';
    } else {
      submitBtn.disabled = false;
      if (form === this.loginForm) {
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i>Se connecter';
      } else {
        submitBtn.innerHTML =
          '<i class="fas fa-user-plus"></i>Créer mon compte';
      }
    }
  }
}

/**
 * Fonction globale de déconnexion
 */
async function logout() {
  if (confirm('Etes-vous sûr de vouloir vous déconnecter ?')) {
    try {
      await fetch('../backend/auth/logout.php');
      window.location.href = 'login.html';
    } catch (error) {
      console.error('Erreur lors de la déconnexion:', error);
      window.location.href = 'login.html';
    }
  }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
  // Vérifier si l'utilisateur est déjà connecté
  if (localStorage.getItem('isLoggedIn') === 'true') {
    window.location.href = 'user-profile.html';
    return;
  }

  new AuthManager();

  // Pré-remplir le champ "Se souvenir de moi" si activé
  if (localStorage.getItem('rememberMe') === 'true') {
    document.getElementById('remember-me').checked = true;
    const savedEmail = localStorage.getItem('userEmail');
    if (savedEmail) {
      document.getElementById('login-email').value = savedEmail;
    }
  }
});
