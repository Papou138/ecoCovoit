class TripDetailManager {
  constructor() {
    this.tripId = this.getTripIdFromUrl();
    this.currentTrip = null;
    this.userCredits = this.getUserCredits();

    this.participateBtn = document.getElementById('participate-btn');
    this.modal = document.getElementById('participation-modal');
    this.modalClose = document.getElementById('modal-close');
    this.cancelBtn = document.getElementById('cancel-participation');
    this.confirmBtn = document.getElementById('confirm-participation');

    this.initEventListeners();
    this.loadTripDetails();
  }

  getTripIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id') || '1'; // Valeur par défaut pour test
  }

  getUserCredits() {
    // Simulation - en réalité, cela viendrait de l'API
    return parseInt(localStorage.getItem('userCredits')) || 40;
  }

  initEventListeners() {
    // Bouton de participation
    this.participateBtn.addEventListener('click', () =>
      this.handleParticipation()
    );

    // Modal
    this.modalClose.addEventListener('click', () => this.closeModal());
    this.cancelBtn.addEventListener('click', () => this.closeModal());
    this.confirmBtn.addEventListener('click', () =>
      this.confirmParticipation()
    );

    // Fermer modal en cliquant à l'extérieur
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.closeModal();
      }
    });

    // Bouton voir plus d'avis
    document
      .getElementById('show-more-reviews')
      .addEventListener('click', () => {
        this.loadMoreReviews();
      });
  }

  async loadTripDetails() {
    try {
      // Simulation d'appel API - A remplacer par le vrai backend
      const tripData = await this.fetchTripDetails(this.tripId);
      this.currentTrip = tripData;
      this.displayTripDetails(tripData);
      this.loadDriverReviews(tripData.driver.id);
    } catch (error) {
      console.error('Erreur lors du chargement des détails:', error);
      this.showError('Impossible de charger les détails du trajet');
    }
  }

  async fetchTripDetails(tripId) {
    // Simulation d'appel API - A remplacer par le vrai backend
    return new Promise((resolve) => {
      setTimeout(() => {
        const mockTrip = {
          id: tripId,
          route: {
            departure: 'Paris',
            arrival: 'Lyon',
            departureTime: '08:30',
            arrivalTime: '10:45',
            duration: '2h15',
            date: '2025-07-08',
          },
          driver: {
            id: 1,
            name: 'Marie D.',
            avatar: 'MD',
            rating: 4.8,
            reviewCount: 23,
          },
          vehicle: {
            brand: 'Tesla',
            model: 'Model 3',
            energy: 'Electrique',
            color: 'Bleu',
            isElectric: true,
          },
          price: 25,
          availableSeats: 2,
          totalSeats: 4,
          isEco: true,
          preferences: [
            { icon: 'fas fa-smoking-ban', text: 'Non-fumeur' },
            { icon: 'fas fa-paw', text: 'Animaux acceptés' },
            { icon: 'fas fa-music', text: 'Musique autorisée' },
          ],
        };
        resolve(mockTrip);
      }, 500);
    });
  }

  displayTripDetails(trip) {
    // Route et timing
    document.getElementById('departure-city').textContent =
      trip.route.departure;
    document.getElementById('arrival-city').textContent = trip.route.arrival;
    document.getElementById('departure-time').textContent =
      trip.route.departureTime;
    document.getElementById('arrival-time').textContent =
      trip.route.arrivalTime;
    document.getElementById('trip-duration').textContent = trip.route.duration;

    // Date formatée
    const date = new Date(trip.route.date);
    const formattedDate = date.toLocaleDateString('fr-FR', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
    document.getElementById('trip-date').textContent = formattedDate;

    // Places et prix
    document.getElementById(
      'available-seats'
    ).textContent = `${trip.availableSeats} sur ${trip.totalSeats}`;
    document.getElementById('trip-price').textContent = `${trip.price} €`;
    document.getElementById('total-price').textContent = `${trip.price} €`;

    // Véhicule
    document.getElementById(
      'vehicle-name'
    ).textContent = `${trip.vehicle.brand} ${trip.vehicle.model}`;
    document.getElementById('vehicle-color').textContent = trip.vehicle.color;

    const energyBadge = document.getElementById('vehicle-energy');
    energyBadge.className = `energy-badge ${
      trip.vehicle.isElectric ? 'electric' : 'fuel'
    }`;
    energyBadge.innerHTML = `
            <i class="fas fa-${
              trip.vehicle.isElectric ? 'bolt' : 'gas-pump'
            }"></i>
            ${trip.vehicle.energy}
        `;

    // Badge écologique
    const ecoStatus = document.getElementById('eco-status');
    if (!trip.isEco) {
      ecoStatus.style.display = 'none';
    }

    // Conducteur
    document.getElementById('driver-avatar').textContent = trip.driver.avatar;
    document.getElementById('driver-name').textContent = trip.driver.name;
    document.getElementById('driver-rating-value').textContent =
      trip.driver.rating;
    document.getElementById(
      'driver-rating-count'
    ).textContent = `(${trip.driver.reviewCount} avis)`;

    // Etoiles du conducteur
    this.displayStars('driver-stars', trip.driver.rating);

    // Préférences
    const preferencesList = document.getElementById('preferences-list');
    preferencesList.innerHTML = trip.preferences
      .map(
        (pref) => `
            <div class="preference-item">
                <i class="${pref.icon}"></i>
                <span>${pref.text}</span>
            </div>
        `
      )
      .join('');

    // Vérifier la disponibilité
    this.checkAvailability(trip);
  }

  displayStars(elementId, rating) {
    const starsContainer = document.getElementById(elementId);
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;

    let starsHtml = '';

    // Etoiles pleines
    for (let i = 0; i < fullStars; i++) {
      starsHtml += '<i class="fas fa-star"></i>';
    }

    // Demi-étoile
    if (hasHalfStar) {
      starsHtml += '<i class="fas fa-star-half-alt"></i>';
    }

    // Etoiles vides
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < emptyStars; i++) {
      starsHtml += '<i class="far fa-star"></i>';
    }

    starsContainer.innerHTML = starsHtml;
  }

  checkAvailability(trip) {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (!isLoggedIn) {
      this.participateBtn.innerHTML =
        '<i class="fas fa-sign-in-alt"></i>Se connecter pour participer';
      this.participateBtn.onclick = () => {
        localStorage.setItem('redirectAfterLogin', window.location.href);
        window.location.href = 'login.html';
      };
      return;
    }

    if (trip.availableSeats === 0) {
      this.participateBtn.innerHTML = '<i class="fas fa-times"></i>Complet';
      this.participateBtn.disabled = true;
      this.participateBtn.classList.add('btn-disabled');
      return;
    }

    if (this.userCredits < trip.price) {
      this.participateBtn.innerHTML =
        '<i class="fas fa-coins"></i>crédits insuffisants';
      this.participateBtn.disabled = true;
      this.participateBtn.classList.add('btn-disabled');

      // Afficher info crédits
      const creditInfo = document.getElementById('credit-info');
      creditInfo.style.display = 'block';
      creditInfo.innerHTML = `
                <p><i class="fas fa-exclamation-triangle"></i>
                Vous avez ${this.userCredits} crédits, mais ce trajet coûte ${trip.price} crédits.</p>
            `;
      return;
    }

    // Afficher les crédits actuels
    const creditInfo = document.getElementById('credit-info');
    creditInfo.style.display = 'block';
    document.getElementById('user-credits').textContent = this.userCredits;
  }

  handleParticipation() {
    if (!this.currentTrip || this.participateBtn.disabled) return;

    // Remplir le modal avec les détails
    document.getElementById(
      'modal-route'
    ).textContent = `${this.currentTrip.route.departure} â†' ${this.currentTrip.route.arrival}`;

    const date = new Date(this.currentTrip.route.date);
    const formattedDate = date.toLocaleDateString('fr-FR', {
      weekday: 'short',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
    document.getElementById(
      'modal-datetime'
    ).textContent = `${formattedDate} Í  ${this.currentTrip.route.departureTime}`;

    document.getElementById('modal-driver').textContent =
      this.currentTrip.driver.name;
    document.getElementById(
      'modal-price'
    ).textContent = `${this.currentTrip.price} €`;
    document.getElementById('modal-credit-cost').textContent =
      this.currentTrip.price;

    const remainingCredits = this.userCredits - this.currentTrip.price;
    document.getElementById('modal-remaining-credits').textContent =
      remainingCredits;

    // Styliser les crédits restants
    const remainingElement = document.getElementById('modal-remaining-credits');
    if (remainingCredits < 10) {
      remainingElement.style.color = 'var(--color-warning)';
    } else {
      remainingElement.style.color = 'var(--color-success)';
    }

    this.showModal();
  }

  showModal() {
    this.modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  closeModal() {
    this.modal.classList.remove('active');
    document.body.style.overflow = 'auto';
  }

  async confirmParticipation() {
    try {
      this.confirmBtn.disabled = true;
      this.confirmBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i>Traitement...';

      // Simulation d'appel API
      const result = await this.submitParticipation();

      if (result.success) {
        // Mettre à jour les crédits locaux
        this.userCredits -= this.currentTrip.price;
        localStorage.setItem('userCredits', this.userCredits);

        // Mettre à jour les places disponibles
        this.currentTrip.availableSeats--;
        document.getElementById(
          'available-seats'
        ).textContent = `${this.currentTrip.availableSeats} sur ${this.currentTrip.totalSeats}`;

        this.closeModal();
        this.showSuccessMessage();

        // Rediriger vers mes réservations après 3 secondes
        setTimeout(() => {
          window.location.href = 'mes-reservations.html';
        }, 3000);
      } else {
        throw new Error(result.message || 'Erreur lors de la participation');
      }
    } catch (error) {
      console.error('Erreur:', error);
      this.showErrorMessage(error.message);
    } finally {
      this.confirmBtn.disabled = false;
      this.confirmBtn.innerHTML =
        '<i class="fas fa-check"></i>Confirmer ma participation';
    }
  }

  async submitParticipation() {
    // Simulation d'appel API - A remplacer par le vrai backend
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({ success: true, message: 'Participation confirmée !' });
      }, 1500);
    });
  }

  showSuccessMessage() {
    const message = document.createElement('div');
    message.className = 'success-notification';
    message.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-check-circle"></i>
                <h3>Participation confirmée !</h3>
                <p>Vous allez être redirigé vers vos réservations...</p>
            </div>
        `;

    document.body.appendChild(message);

    // Styles pour la notification
    Object.assign(message.style, {
      position: 'fixed',
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      background: 'var(--color-success-light)',
      border: '2px solid var(--color-success)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-xl)',
      zIndex: '1001',
      textAlign: 'center',
      boxShadow: 'var(--shadow-lg)',
    });

    setTimeout(() => {
      document.body.removeChild(message);
    }, 3000);
  }

  showErrorMessage(message) {
    // Afficher message d'erreur
    console.error('Erreur de participation:', message);
  }

  async loadDriverReviews(driverId) {
    try {
      const reviews = await this.fetchDriverReviews(driverId);
      this.displayReviews(reviews);
    } catch (error) {
      console.error('Erreur lors du chargement des avis:', error);
    }
  }

  async fetchDriverReviews(driverId) {
    // Simulation d'appel API
    return new Promise((resolve) => {
      setTimeout(() => {
        const mockReviews = [
          {
            id: 1,
            rating: 5,
            comment:
              'Excellent conducteur ! Très ponctuel et conduite souple. Je recommande vivement !',
            date: '2025-06-28',
            passengerName: 'Jean M.',
          },
          {
            id: 2,
            rating: 4,
            comment:
              'Trajet agréable, conductrice sympathique. Voiture propre et confortable.',
            date: '2025-06-25',
            passengerName: 'Sophie L.',
          },
          {
            id: 3,
            rating: 5,
            comment:
              'Parfait ! Communication excellente et respect des horaires. A refaire !',
            date: '2025-06-20',
            passengerName: 'Pierre D.',
          },
        ];
        resolve(mockReviews);
      }, 300);
    });
  }

  displayReviews(reviews) {
    // Calculer et afficher la moyenne
    const average =
      reviews.reduce((sum, review) => sum + review.rating, 0) / reviews.length;
    document.getElementById('average-rating').textContent = average.toFixed(1);
    document.getElementById(
      'total-reviews'
    ).textContent = `Basé sur ${reviews.length} avis`;

    // Afficher les étoiles
    this.displayStars('stars-large', average);

    // Afficher les avis (limité à 3 initialement)
    const reviewsList = document.getElementById('reviews-list');
    const displayedReviews = reviews.slice(0, 3);

    reviewsList.innerHTML = displayedReviews
      .map(
        (review) => `
            <div class="review-item">
                <div class="review-header">
                    <div class="review-rating">
                        ${'★'.repeat(review.rating)}${'☆'.repeat(
          5 - review.rating
        )}
                    </div>
                    <div class="review-date">
                        ${new Date(review.date).toLocaleDateString('fr-FR')}
                    </div>
                </div>
                <div class="review-comment">${review.comment}</div>
                <div class="review-author">- ${review.passengerName}</div>
            </div>
        `
      )
      .join('');

    // Masquer le bouton "voir plus" s'il n'y a pas plus d'avis
    if (reviews.length <= 3) {
      document.getElementById('show-more-reviews').style.display = 'none';
    }
  }

  loadMoreReviews() {
    // Charger plus d'avis - A implémenter
    console.log("Charger plus d'avis...");
  }

  showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Erreur</h3>
            <p>${message}</p>
        `;

    const container = document.querySelector('.trip-detail-section .container');
    container.insertBefore(errorDiv, container.firstChild);
  }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
  new TripDetailManager();

  // Vérifier si on vient de la recherche avec l'action "participate"
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('action') === 'participate') {
    // Auto-ouvrir le modal de participation après chargement
    setTimeout(() => {
      const participateBtn = document.getElementById('participate-btn');
      if (participateBtn && !participateBtn.disabled) {
        participateBtn.click();
      }
    }, 1000);
  }
});
