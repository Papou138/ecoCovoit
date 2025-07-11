/**
 * Gestion de la recherche de covoiturages
 * US3 & US4 - Vue des covoiturages et filtres
 * Version mise à jour pour la nouvelle charte graphique
 */

class CovoiturageSearch {
  constructor() {
    // Récupération des éléments DOM
    this.searchForm = document.getElementById('search-form');
    this.resultsList = document.getElementById('covoiturages-list');
    this.resultsCount = document.getElementById('results-count');
    this.resultsTitle = document.getElementById('results-title');
    this.noSearchMessage = document.getElementById('no-search-message');
    this.noResultsMessage = document.getElementById('no-results-message');
    this.priceSlider = document.getElementById('max-price');
    this.priceDisplay = document.querySelector('.price-display');

    // Variables d'état
    this.currentResults = [];
    this.hasSearched = false;
    this.isLoading = false;

    // Vérifications de sécurité
    if (!this.priceSlider) {
      console.warn('Elément price-slider non trouvé');
    }
    if (!this.priceDisplay) {
      console.warn('Elément price-display non trouvé');
    }

    // Initialisation
    this.initEventListeners();
    this.initDateField();
    this.updatePriceDisplay();
    this.checkUrlParams();
  }

  initEventListeners() {
    // Vérifier l'existence des éléments avant d'ajouter les écouteurs
    if (this.searchForm) {
      this.searchForm.addEventListener('submit', (e) => this.handleSearch(e));
    }

    // Filtres
    const ecoFilter = document.getElementById('eco-filter');
    if (ecoFilter) {
      ecoFilter.addEventListener('change', () => this.applyFilters());
    }

    // Slider de prix - multiple événements pour garantir la mise à jour
    if (this.priceSlider) {
      // Evénement 'input' pour mise à jour en temps réel (le plus important)
      this.priceSlider.addEventListener('input', () => {
        this.updatePriceDisplay();
        this.applyFilters();
      });

      // Evénement 'change' pour compatibilité
      this.priceSlider.addEventListener('change', () => {
        this.updatePriceDisplay();
        this.applyFilters();
      });

      // Evénement 'mousemove' pour les navigateurs qui ne supportent pas 'input'
      this.priceSlider.addEventListener('mousemove', (e) => {
        if (e.buttons === 1) {
          // Bouton gauche enfoncé
          this.updatePriceDisplay();
        }
      });

      // Evénement 'touchmove' pour les appareils tactiles
      this.priceSlider.addEventListener('touchmove', () => {
        this.updatePriceDisplay();
      });
    }

    const maxDuration = document.getElementById('max-duration');
    if (maxDuration) {
      maxDuration.addEventListener('change', () => this.applyFilters());
    }

    // Rating filters
    document.querySelectorAll('input[name="min-rating"]').forEach((radio) => {
      radio.addEventListener('change', () => this.applyFilters());
    });

    // Reset filters - ajouter un événement plus robuste
    const resetButton = document.getElementById('reset-filters');
    if (resetButton) {
      resetButton.addEventListener('click', (e) => {
        e.preventDefault();
        this.resetFilters();
      });
    }
  }

  initDateField() {
    const dateInput = document.getElementById('date');
    if (dateInput) {
      const today = new Date().toISOString().split('T')[0];
      dateInput.min = today;
      if (!dateInput.value) {
        dateInput.value = today;
      }
    }
  }

  checkUrlParams() {
    // Pré-remplir les champs si on vient de la page d'accueil
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('depart')) {
      const departureInput = document.getElementById('departure');
      if (departureInput) departureInput.value = urlParams.get('depart');
    }
    if (urlParams.get('arrivee')) {
      const arrivalInput = document.getElementById('arrival');
      if (arrivalInput) arrivalInput.value = urlParams.get('arrivee');
    }
    if (urlParams.get('date')) {
      const dateInput = document.getElementById('date');
      if (dateInput) dateInput.value = urlParams.get('date');
    }

    // Déclencher une recherche automatique si tous les paramètres sont présents
    if (
      urlParams.get('depart') &&
      urlParams.get('arrivee') &&
      urlParams.get('date')
    ) {
      setTimeout(() => {
        if (this.searchForm) {
          this.searchForm.dispatchEvent(new Event('submit'));
        }
      }, 100);
    }
  }

  updatePriceDisplay() {
    if (this.priceDisplay && this.priceSlider) {
      const value = this.priceSlider.value;
      this.priceDisplay.textContent = `${value}€`;
    }
  }

  async handleSearch(e) {
    e.preventDefault();

    if (this.isLoading) return;

    const formData = new FormData(this.searchForm);
    const searchParams = {
      departure: formData.get('departure')?.trim(),
      arrival: formData.get('arrival')?.trim(),
      date: formData.get('date'),
    };

    // Validation des champs
    if (
      !searchParams.departure ||
      !searchParams.arrival ||
      !searchParams.date
    ) {
      this.showError('Veuillez remplir tous les champs de recherche');
      return;
    }

    try {
      this.isLoading = true;
      this.showLoading();

      // Appel API (simulation ou vrai backend)
      const results = await this.searchCovoiturages(searchParams);

      this.currentResults = results;
      this.hasSearched = true;
      this.displayResults();
    } catch (error) {
      console.error('Erreur lors de la recherche:', error);
      this.showError(
        'Une erreur est survenue lors de la recherche. Veuillez réessayer.'
      );
    } finally {
      this.isLoading = false;
    }
  }

  async searchCovoiturages(params) {
    // Simulation d'appel API avec délai réaliste
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        try {
          // Générer des données de test plus réalistes
          const mockResults = this.generateMockResults(params);
          resolve(mockResults);
        } catch (error) {
          reject(error);
        }
      }, 800 + Math.random() * 400); // Délai entre 800ms et 1200ms
    });
  }

  generateMockResults(params) {
    const drivers = [
      { name: 'Marie D.', avatar: 'MD', rating: 4.8, reviewCount: 23 },
      { name: 'Thomas L.', avatar: 'TL', rating: 4.2, reviewCount: 15 },
      { name: 'Sophie M.', avatar: 'SM', rating: 5.0, reviewCount: 47 },
      { name: 'Pierre J.', avatar: 'PJ', rating: 4.5, reviewCount: 31 },
      { name: 'Lucie B.', avatar: 'LB', rating: 4.9, reviewCount: 18 },
    ];

    const vehicles = [
      { brand: 'Tesla', model: 'Model 3', isElectric: true },
      { brand: 'Renault', model: 'Clio', isElectric: false },
      { brand: 'Nissan', model: 'Leaf', isElectric: true },
      { brand: 'Peugeot', model: '308', isElectric: false },
      { brand: 'BMW', model: 'i3', isElectric: true },
    ];

    const times = [
      { departure: '08:30', arrival: '10:45', duration: '2h15' },
      { departure: '14:00', arrival: '16:30', duration: '2h30' },
      { departure: '18:15', arrival: '20:30', duration: '2h15' },
      { departure: '07:00', arrival: '09:15', duration: '2h15' },
      { departure: '16:45', arrival: '19:00', duration: '2h15' },
    ];

    const numResults = Math.floor(Math.random() * 4) + 1; // 1 à 4 résultats
    const results = [];

    for (let i = 0; i < numResults; i++) {
      const driver = drivers[i % drivers.length];
      const vehicle = vehicles[i % vehicles.length];
      const time = times[i % times.length];

      results.push({
        id: i + 1,
        driver: driver,
        route: {
          departure: params.departure,
          arrival: params.arrival,
          departureTime: time.departure,
          arrivalTime: time.arrival,
          duration: time.duration,
        },
        vehicle: vehicle,
        price: Math.floor(Math.random() * 35) + 15, // Prix entre 15 et 50€
        availableSeats: Math.floor(Math.random() * 3) + 1, // 1 à 3 places
        date: params.date,
        preferences: this.generatePreferences(),
        isEco: vehicle.isElectric,
      });
    }

    return results;
  }

  generatePreferences() {
    const allPreferences = [
      'Non-fumeur',
      'Animaux acceptés',
      'Musique autorisée',
      'Silence préféré',
      'Climatisation',
    ];
    const numPrefs = Math.floor(Math.random() * 3) + 1;
    const shuffled = allPreferences.sort(() => 0.5 - Math.random());
    return shuffled.slice(0, numPrefs);
  }

  applyFilters() {
    if (!this.hasSearched || this.currentResults.length === 0) return;

    const ecoFilter = document.getElementById('eco-filter')?.checked || false;
    const maxPrice = parseInt(this.priceSlider?.value || 100);
    const maxDuration = document.getElementById('max-duration')?.value || '';
    const minRatingElement = document.querySelector(
      'input[name="min-rating"]:checked'
    );
    const minRating = minRatingElement ? minRatingElement.value : '';

    let filteredResults = this.currentResults.filter((result) => {
      // Filtre écologique
      if (ecoFilter && !result.isEco) return false;

      // Filtre prix
      if (result.price > maxPrice) return false;

      // Filtre durée
      if (maxDuration) {
        const durationHours = this.parseDuration(result.route.duration);
        if (durationHours > parseInt(maxDuration)) return false;
      }

      // Filtre rating
      if (minRating && result.driver.rating < parseFloat(minRating))
        return false;

      return true;
    });

    this.displayFilteredResults(filteredResults);
  }

  parseDuration(duration) {
    const match = duration.match(/(\d+)h(?:(\d+))?/);
    if (match) {
      const hours = parseInt(match[1]);
      const minutes = match[2] ? parseInt(match[2]) : 0;
      return hours + minutes / 60;
    }
    return 0;
  }

  displayResults() {
    this.hideAllMessages();

    if (this.currentResults.length === 0) {
      this.showNoResults();
      return;
    }

    this.displayFilteredResults(this.currentResults);
  }

  displayFilteredResults(results) {
    this.updateResultsCount(results.length);
    this.renderCovoiturageCards(results);

    if (results.length === 0 && this.hasSearched) {
      this.showNoResults();
    }
  }

  updateResultsCount(count) {
    if (!this.resultsCount) return;

    const text =
      count === 0
        ? 'Aucun trajet trouvé'
        : count === 1
        ? '1 trajet trouvé'
        : `${count} trajets trouvés`;
    this.resultsCount.textContent = text;
  }

  renderCovoiturageCards(results) {
    if (!this.resultsList) return;

    if (results.length === 0) {
      this.resultsList.innerHTML = '';
      return;
    }

    this.resultsList.innerHTML = results
      .map(
        (result) => `
            <div class="covoiturage-card fade-in" data-id="${result.id}">
                <div class="driver-profile">
                    <div class="driver-avatar">${result.driver.avatar}</div>
                    <div class="driver-name">${result.driver.name}</div>
                    <div class="driver-rating">
                        <span>${result.driver.rating}</span>
                        <i class="fas fa-star"></i>
                        <span>(${result.driver.reviewCount})</span>
                    </div>
                </div>

                <div class="trip-details">
                    <div class="trip-route">
                        <span>${result.route.departure}</span>
                        <i class="fas fa-arrow-right route-arrow"></i>
                        <span>${result.route.arrival}</span>
                    </div>

                    <div class="trip-info">
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>${result.route.departureTime} - ${
          result.route.arrivalTime
        }</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Durée: ${result.route.duration}</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-users"></i>
                            <span>${
                              result.availableSeats
                            } place(s) disponible(s)</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-car"></i>
                            <span>${result.vehicle.brand} ${
          result.vehicle.model
        }</span>
                        </div>
                    </div>

                    ${
                      result.isEco
                        ? '<div class="eco-badge"><i class="fas fa-leaf"></i> Voyage écologique</div>'
                        : ''
                    }
                </div>

                <div class="trip-actions">
                    <div class="trip-price">
                        <div>${result.price}€</div>
                        <div class="price-per-person">par personne</div>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-secondary" onclick="showDetails(${
                          result.id
                        })">
                            <i class="fas fa-info-circle"></i> Détails
                        </button>
                        <button class="btn btn-primary" onclick="participate(${
                          result.id
                        })">
                            <i class="fas fa-check"></i> Participer
                        </button>
                    </div>
                </div>
            </div>
        `
      )
      .join('');
  }

  showLoading() {
    this.hideAllMessages();
    if (this.resultsList) {
      this.resultsList.innerHTML = `
                <div class="no-search-message">
                    <div class="message-content">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <h3>Recherche en cours...</h3>
                        <p>Nous cherchons les meilleurs trajets pour vous</p>
                    </div>
                </div>
            `;
    }
  }

  showNoResults() {
    this.hideAllMessages();
    if (this.noResultsMessage) {
      this.noResultsMessage.classList.remove('hidden');
    }
    if (this.resultsList) {
      this.resultsList.innerHTML = '';
    }

    // Suggestion d'une date alternative
    this.suggestAlternativeDate();
  }

  suggestAlternativeDate() {
    const dateInput = document.getElementById('date');
    const alternativeSuggestion = document.getElementById(
      'alternative-suggestion'
    );

    if (!dateInput || !alternativeSuggestion) return;

    const currentDate = new Date(dateInput.value);
    const nextDay = new Date(currentDate);
    nextDay.setDate(nextDay.getDate() + 1);

    const options = {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    };

    alternativeSuggestion.innerHTML = `
            <h4><i class="fas fa-lightbulb"></i> Suggestion</h4>
            <p>Aucun trajet trouvé pour cette date. Essayez une autre date ou modifiez vos critères.</p>
            <button class="btn btn-outline" onclick="setAlternativeDate('${
              nextDay.toISOString().split('T')[0]
            }')">
                <i class="fas fa-calendar-alt"></i> Essayer le ${nextDay.toLocaleDateString(
                  'fr-FR',
                  options
                )}
            </button>
        `;
  }

  hideAllMessages() {
    if (this.noSearchMessage) {
      this.noSearchMessage.style.display = 'none';
    }
    if (this.noResultsMessage) {
      this.noResultsMessage.classList.add('hidden');
    }
  }

  showError(message) {
    this.hideAllMessages();
    if (this.resultsList) {
      this.resultsList.innerHTML = `
                <div class="no-results-message">
                    <div class="message-content">
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                        <h3>Erreur</h3>
                        <p>${message}</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh"></i> Réessayer
                        </button>
                    </div>
                </div>
            `;
    }
  }

  resetFilters() {
    // Réinitialiser le filtre écologique
    const ecoFilter = document.getElementById('eco-filter');
    if (ecoFilter) {
      ecoFilter.checked = false;
    }

    // Réinitialiser le slider de prix
    if (this.priceSlider) {
      this.priceSlider.value = 50;
      this.updatePriceDisplay();
    }

    // Réinitialiser la durée maximale
    const maxDuration = document.getElementById('max-duration');
    if (maxDuration) {
      maxDuration.value = '';
    }

    // Réinitialiser les filtres de note
    const ratingAll = document.getElementById('rating-all');
    if (ratingAll) {
      ratingAll.checked = true;
    }

    // Réappliquer les filtres si une recherche a été effectuée
    if (this.hasSearched) {
      this.applyFilters();
    }

    // Forcer une mise à jour de l'affichage
    this.updatePriceDisplay();
  }

  // Méthode pour gérer les paramètres d'URL avancés
  handleAdvancedUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);

    // Appliquer les filtres depuis l'URL
    if (urlParams.get('eco')) {
      const ecoFilter = document.getElementById('eco-filter');
      if (ecoFilter) ecoFilter.checked = true;
    }

    if (urlParams.get('max_prix')) {
      const maxPrice = urlParams.get('max_prix');
      if (this.priceSlider && maxPrice) {
        this.priceSlider.value = maxPrice;
        this.updatePriceDisplay();
      }
    }

    if (urlParams.get('max_duree')) {
      const maxDuration = document.getElementById('max-duration');
      const duration = urlParams.get('max_duree');
      if (maxDuration && duration) {
        maxDuration.value = duration;
      }
    }

    if (urlParams.get('min_note')) {
      const minRating = urlParams.get('min_note');
      const ratingInput = document.getElementById(`rating-${minRating}`);
      if (ratingInput) {
        ratingInput.checked = true;
      }
    }
  }
}

// Fonctions globales pour les boutons
function showDetails(tripId) {
  // Rediriger vers la page de détails avec animation
  window.location.href = `detail.html?id=${tripId}`;
}

function participate(tripId) {
  // Vérifier si l'utilisateur est connecté
  const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

  if (!isLoggedIn) {
    // Sauvegarder l'intention de participation
    localStorage.setItem(
      'redirectAfterLogin',
      `detail.html?id=${tripId}&action=participate`
    );
    // Rediriger vers la page de connexion
    window.location.href = 'login.html?redirect=participate';
  } else {
    // Rediriger vers la page de détails pour participer
    window.location.href = `detail.html?id=${tripId}&action=participate`;
  }
}

function setAlternativeDate(date) {
  const dateInput = document.getElementById('date');
  if (dateInput) {
    dateInput.value = date;
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
      searchForm.dispatchEvent(new Event('submit'));
    }
  }
}

// Fonction utilitaire pour formater les dates
function formatDate(dateString) {
  const date = new Date(dateString);
  const options = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  };
  return date.toLocaleDateString('fr-FR', options);
}

// Fonction pour gérer les notifications
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  notification.innerHTML = `
        <i class="fas fa-${
          type === 'success'
            ? 'check-circle'
            : type === 'error'
            ? 'exclamation-circle'
            : 'info-circle'
        }"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

  document.body.appendChild(notification);

  // Suppression automatique après 5 secondes
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
  console.log(
    '🚗 ecoCovoit - Initialisation de la recherche de covoiturage...'
  );

  // Vérifier que les éléments essentiels existent
  const priceSlider = document.getElementById('max-price');
  const priceDisplay = document.querySelector('.price-display');

  console.log('Eléments trouvés:', {
    priceSlider: !!priceSlider,
    priceDisplay: !!priceDisplay,
  });

  try {
    const searchInstance = new CovoiturageSearch();

    // Rendre l'instance accessible globalement pour le débogage
    window.covoiturageSearch = searchInstance;

    // Gérer les paramètres d'URL avancés
    searchInstance.handleAdvancedUrlParams();

    console.log('Système de recherche de covoiturage initialisé avec succès');
  } catch (error) {
    console.error("Erreur lors de l'initialisation:", error);
  }

  // Ajouter des écouteurs pour les interactions clavier
  document.addEventListener('keydown', (e) => {
    // Echap pour fermer les modales/notifications
    if (e.key === 'Escape') {
      const notifications = document.querySelectorAll('.notification');
      notifications.forEach((n) => n.remove());
    }

    // Ctrl+Enter pour déclencher la recherche
    if (e.ctrlKey && e.key === 'Enter') {
      const searchForm = document.getElementById('search-form');
      if (searchForm) {
        searchForm.dispatchEvent(new Event('submit'));
      }
    }
  });
});
