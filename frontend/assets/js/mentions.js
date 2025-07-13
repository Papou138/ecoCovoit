document.addEventListener('DOMContentLoaded', function () {
  // Gestion des ancres pour la navigation interne
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach((link) => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const targetId = this.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        targetElement.scrollIntoView({
          behavior: 'smooth',
          block: 'start',
        });

        // Ajouter une classe pour highlighting temporaire
        targetElement.classList.add('highlight');
        setTimeout(() => {
          targetElement.classList.remove('highlight');
        }, 2000);
      }
    });
  });

  // Bouton de gestion des cookies (simulation)
  const cookieBtn = document.querySelector('.cookie-preferences');
  if (cookieBtn) {
    cookieBtn.addEventListener('click', function () {
      alert(
        'Interface de gestion des cookies (A implémenter selon la solution de consentement)'
      );
    });
  }

  // Navigation sticky pour le sommaire
  const summary = document.querySelector('.legal-summary');
  const summaryTop = summary.offsetTop;

  window.addEventListener('scroll', function () {
    if (window.pageYOffset >= summaryTop - 100) {
      summary.classList.add('sticky');
    } else {
      summary.classList.remove('sticky');
    }
  });
});
