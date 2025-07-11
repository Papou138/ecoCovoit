/**
 * Gestion de la page d'accueil
 * Fonctionnalités pour l'amélioration de l'UX sur la page principale
 */

// Amélioration de l'expérience utilisateur
document.addEventListener('DOMContentLoaded', function () {
  // Définir la date minimale à aujourd'hui
  const dateInput = document.getElementById('date');
  const today = new Date();
  const todayString = today.toISOString().split('T')[0];
  dateInput.setAttribute('min', todayString);

  // Définir par défaut la date d'aujourd'hui
  if (!dateInput.value) {
    dateInput.value = todayString;
  }

  // Amélioration des animations au scroll
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px',
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.style.animationPlayState = 'running';
      }
    });
  }, observerOptions);

  // Observer les cartes d'avantages
  const benefitCards = document.querySelectorAll('.benefit-card');
  benefitCards.forEach((card) => {
    observer.observe(card);
  });

  // Amélioration du formulaire de recherche
  const searchForm = document.getElementById('search-form');
  const departureInput = document.getElementById('departure');
  const arrivalInput = document.getElementById('arrival');

  // Validation améliorée
  searchForm.addEventListener('submit', function (e) {
    const departure = departureInput.value.trim();
    const arrival = arrivalInput.value.trim();

    if (departure === arrival) {
      e.preventDefault();
      alert(
        "La ville de départ ne peut pas être la même que la ville d'arrivée."
      );
      return false;
    }

    // Vérification de la longueur minimale
    if (departure.length < 2 || arrival.length < 2) {
      e.preventDefault();
      alert(
        'Veuillez saisir des noms de ville valides (minimum 2 caractères).'
      );
      return false;
    }
  });

  // Auto-complétion basique pour les villes françaises populaires
  const popularCities = [
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
    'Saint-Étienne',
    'Toulon',
    'Grenoble',
    'Dijon',
    'Angers',
    'Nîmes',
    'Villeurbanne',
    'Saint-Denis',
    'Le Mans',
    'Aix-en-Provence',
    'Clermont-Ferrand',
    'Brest',
    'Limoges',
    'Tours',
    'Amiens',
    'Perpignan',
    'Metz',
    'Besançon',
    'Boulogne-Billancourt',
    'Orléans',
    'Mulhouse',
    'Rouen',
    'Pau',
    'Caen',
    'La Rochelle',
    'Cannes',
    'Colmar',
    'Avignon',
    'Poitiers',
    'Dunkerque',
  ];

  function addAutocomplete(input) {
    input.addEventListener('input', function () {
      const value = this.value.toLowerCase();
      const suggestions = popularCities.filter(
        (city) => city.toLowerCase().includes(value) && value.length >= 2
      );

      // Créer une liste de suggestions simple
      let datalistId = this.id + '-suggestions';
      let datalist = document.getElementById(datalistId);

      if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = datalistId;
        this.setAttribute('list', datalistId);
        this.parentNode.appendChild(datalist);
      }

      datalist.innerHTML = '';
      suggestions.slice(0, 8).forEach((city) => {
        const option = document.createElement('option');
        option.value = city;
        datalist.appendChild(option);
      });
    });
  }

  addAutocomplete(departureInput);
  addAutocomplete(arrivalInput);
});
