/**
 * Gestion de la recherche de covoiturages
 * US3 & US4 - Vue des covoiturages et filtres
 */

class CovoiturageSearch {
    constructor() {
        this.searchForm = document.getElementById('search-form');
        this.resultsList = document.getElementById('covoiturages-list');
        this.resultsCount = document.getElementById('results-count');
        this.noSearchMessage = document.getElementById('no-search-message');
        this.noResultsMessage = document.getElementById('no-results-message');
        this.priceSlider = document.getElementById('max-price');
        this.priceDisplay = document.querySelector('.price-display');

        this.currentResults = [];
        this.hasSearched = false;

        this.initEventListeners();
        this.initDateField();
        this.updatePriceDisplay();
    }

    initEventListeners() {
        // Recherche principale
        this.searchForm.addEventListener('submit', (e) => this.handleSearch(e));

        // Filtres
        document.getElementById('eco-filter').addEventListener('change', () => this.applyFilters());
        this.priceSlider.addEventListener('input', () => {
            this.updatePriceDisplay();
            this.applyFilters();
        });
        document.getElementById('max-duration').addEventListener('change', () => this.applyFilters());

        // Rating filters
        document.querySelectorAll('input[name="min-rating"]').forEach(radio => {
            radio.addEventListener('change', () => this.applyFilters());
        });

        // Reset filters
        document.getElementById('reset-filters').addEventListener('click', () => this.resetFilters());
    }

    initDateField() {
        const dateInput = document.getElementById('date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        dateInput.value = today;
    }

    updatePriceDisplay() {
        this.priceDisplay.textContent = `${this.priceSlider.value}€`;
    }

    async handleSearch(e) {
        e.preventDefault();

        const formData = new FormData(this.searchForm);
        const searchParams = {
            departure: formData.get('departure'),
            arrival: formData.get('arrival'),
            date: formData.get('date')
        };

        try {
            this.showLoading();

            // Simulation d'appel API - remplacer par vraie API
            const results = await this.searchCovoiturages(searchParams);

            this.currentResults = results;
            this.hasSearched = true;
            this.displayResults();

        } catch (error) {
            console.error('Erreur lors de la recherche:', error);
            this.showError('Une erreur est survenue lors de la recherche');
        }
    }

    async searchCovoiturages(params) {
        // Simulation d'appel API - à remplacer par le vrai backend
        return new Promise((resolve) => {
            setTimeout(() => {
                // Données de test
                const mockResults = [
                    {
                        id: 1,
                        driver: {
                            name: "Marie D.",
                            avatar: "MD",
                            rating: 4.8,
                            reviewCount: 23
                        },
                        route: {
                            departure: params.departure,
                            arrival: params.arrival,
                            departureTime: "08:30",
                            arrivalTime: "10:45",
                            duration: "2h15"
                        },
                        vehicle: {
                            brand: "Tesla",
                            model: "Model 3",
                            isElectric: true
                        },
                        price: 25,
                        availableSeats: 2,
                        date: params.date,
                        preferences: ["Non-fumeur", "Animaux acceptés"],
                        isEco: true
                    },
                    {
                        id: 2,
                        driver: {
                            name: "Thomas L.",
                            avatar: "TL",
                            rating: 4.2,
                            reviewCount: 15
                        },
                        route: {
                            departure: params.departure,
                            arrival: params.arrival,
                            departureTime: "14:00",
                            arrivalTime: "16:30",
                            duration: "2h30"
                        },
                        vehicle: {
                            brand: "Renault",
                            model: "Clio",
                            isElectric: false
                        },
                        price: 18,
                        availableSeats: 3,
                        date: params.date,
                        preferences: ["Non-fumeur", "Musique autorisée"],
                        isEco: false
                    },
                    {
                        id: 3,
                        driver: {
                            name: "Sophie M.",
                            avatar: "SM",
                            rating: 5.0,
                            reviewCount: 47
                        },
                        route: {
                            departure: params.departure,
                            arrival: params.arrival,
                            departureTime: "18:15",
                            arrivalTime: "20:30",
                            duration: "2h15"
                        },
                        vehicle: {
                            brand: "Nissan",
                            model: "Leaf",
                            isElectric: true
                        },
                        price: 30,
                        availableSeats: 1,
                        date: params.date,
                        preferences: ["Non-fumeur", "Silence préféré"],
                        isEco: true
                    }
                ];

                resolve(mockResults);
            }, 1000);
        });
    }

    applyFilters() {
        if (!this.hasSearched || this.currentResults.length === 0) return;

        const ecoFilter = document.getElementById('eco-filter').checked;
        const maxPrice = parseInt(this.priceSlider.value);
        const maxDuration = document.getElementById('max-duration').value;
        const minRating = document.querySelector('input[name="min-rating"]:checked').value;

        let filteredResults = this.currentResults.filter(result => {
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
            if (minRating && result.driver.rating < parseFloat(minRating)) return false;

            return true;
        });

        this.displayFilteredResults(filteredResults);
    }

    parseDuration(duration) {
        const match = duration.match(/(\d+)h(?:(\d+))?/);
        if (match) {
            const hours = parseInt(match[1]);
            const minutes = match[2] ? parseInt(match[2]) : 0;
            return hours + (minutes / 60);
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
        const text = count === 0 ? 'Aucun trajet trouvé' :
                    count === 1 ? '1 trajet trouvé' :
                    `${count} trajets trouvés`;
        this.resultsCount.textContent = text;
    }

    renderCovoiturageCards(results) {
        if (results.length === 0) {
            this.resultsList.innerHTML = '';
            return;
        }

        this.resultsList.innerHTML = results.map(result => `
            <div class="covoiturage-card" data-id="${result.id}">
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
                            <span>${result.route.departureTime} - ${result.route.arrivalTime}</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-hourglass-half"></i>
                            <span>${result.route.duration}</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-users"></i>
                            <span>${result.availableSeats} place(s) disponible(s)</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-car"></i>
                            <span>${result.vehicle.brand} ${result.vehicle.model}</span>
                        </div>
                    </div>

                    ${result.isEco ? '<div class="eco-badge"><i class="fas fa-leaf"></i>Voyage écologique</div>' : ''}
                </div>

                <div class="trip-actions">
                    <div class="trip-price">
                        <div>${result.price}€</div>
                        <div class="price-per-person">par personne</div>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-outline btn-sm detail-btn" onclick="showDetails(${result.id})">
                            Détails
                        </button>
                        <button class="btn btn-primary btn-sm participate-btn" onclick="participate(${result.id})">
                            Participer
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    showLoading() {
        this.hideAllMessages();
        this.resultsList.innerHTML = `
            <div class="loading-message">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Recherche en cours...</p>
            </div>
        `;
    }

    showNoResults() {
        this.hideAllMessages();
        this.noResultsMessage.style.display = 'block';
        this.resultsList.innerHTML = '';

        // Suggestion d'une date alternative
        this.suggestAlternativeDate();
    }

    suggestAlternativeDate() {
        const currentDate = new Date(document.getElementById('date').value);
        const nextDay = new Date(currentDate);
        nextDay.setDate(nextDay.getDate() + 1);

        const alternativeSuggestion = document.getElementById('alternative-suggestion');
        alternativeSuggestion.innerHTML = `
            <h4>💡 Suggestion</h4>
            <p>Essayez de modifier votre date de voyage. Par exemple, le ${nextDay.toLocaleDateString('fr-FR')} pourrait avoir plus d'options disponibles.</p>
            <button class="btn btn-primary btn-sm" onclick="setAlternativeDate('${nextDay.toISOString().split('T')[0]}')">
                Essayer le ${nextDay.toLocaleDateString('fr-FR')}
            </button>
        `;
    }

    hideAllMessages() {
        this.noSearchMessage.style.display = 'none';
        this.noResultsMessage.style.display = 'none';
    }

    showError(message) {
        this.resultsList.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
                <h3>Erreur</h3>
                <p>${message}</p>
            </div>
        `;
    }

    resetFilters() {
        document.getElementById('eco-filter').checked = false;
        this.priceSlider.value = 50;
        this.updatePriceDisplay();
        document.getElementById('max-duration').value = '';
        document.getElementById('rating-all').checked = true;

        if (this.hasSearched) {
            this.applyFilters();
        }
    }
}

// Fonctions globales pour les boutons
function showDetails(tripId) {
    // Rediriger vers la page de détails
    window.location.href = `detail.html?id=${tripId}`;
}

function participate(tripId) {
    // Vérifier si l'utilisateur est connecté
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (!isLoggedIn) {
        // Rediriger vers la page de connexion
        localStorage.setItem('redirectAfterLogin', `detail.html?id=${tripId}`);
        window.location.href = 'login.html';
    } else {
        // Rediriger vers la page de détails pour participer
        window.location.href = `detail.html?id=${tripId}&action=participate`;
    }
}

function setAlternativeDate(date) {
    document.getElementById('date').value = date;
    document.getElementById('search-form').dispatchEvent(new Event('submit'));
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new CovoiturageSearch();

    // Pré-remplir les champs si on vient de la page d'accueil
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from')) {
        document.getElementById('departure').value = urlParams.get('from');
        document.getElementById('arrival').value = urlParams.get('to');
        document.getElementById('date').value = urlParams.get('date');
    }
});
