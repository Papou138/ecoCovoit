// Chargement des données du dashboard
document.addEventListener('DOMContentLoaded', function () {
  loadDashboardData();
  startRealTimeUpdates();
});

// Chargement des données
async function loadDashboardData() {
  try {
    // Simulation du chargement des données (A remplacer par des appels API réels)
    updateStats();
    loadRecentActivity();
  } catch (error) {
    console.error('Erreur lors du chargement du dashboard:', error);
  }
}

// Mise à jour des statistiques
function updateStats() {
  // Simulation de données dynamiques
  const stats = {
    totalUsers: Math.floor(Math.random() * 100) + 2800,
    newUsers: Math.floor(Math.random() * 50) + 100,
    activeUsers: Math.floor(Math.random() * 200) + 1500,
    verifiedUsers: Math.floor(Math.random() * 100) + 2300,
    totalTrips: Math.floor(Math.random() * 100) + 8400,
    completedTrips: Math.floor(Math.random() * 50) + 7850,
    activeTrips: Math.floor(Math.random() * 20) + 80,
    cancelledTrips: Math.floor(Math.random() * 10) + 430,
  };

  // Mise à jour des éléments (les autres sont statiques pour la démo)
  if (document.getElementById('total-users')) {
    document.getElementById('total-users').textContent =
      stats.totalUsers.toLocaleString();
  }
  if (document.getElementById('active-trips')) {
    document.getElementById('active-trips').textContent = stats.activeTrips;
  }
}

// Chargement de l'activité récente
function loadRecentActivity() {
  // Les activités sont déjà en dur dans le HTML pour la démo
  // En production, elles seraient chargées depuis l'API
}

// Mise à jour en temps réel
function startRealTimeUpdates() {
  // Mise à jour toutes les 30 secondes
  setInterval(updateStats, 30000);
}

// Actions rapides
function generateReport() {
  if (confirm('Générer le rapport mensuel ?')) {
    alert(
      'Rapport généré avec succès !\nLe fichier sera envoyé par email dans quelques minutes.'
    );
  }
}

function sendNewsletter() {
  if (confirm('Envoyer la newsletter à tous les utilisateurs actifs ?')) {
    alert(
      'Newsletter programmée !\nEnvoi en cours vers 1,623 utilisateurs actifs.'
    );
  }
}

function systemMaintenance() {
  if (confirm('Programmer une maintenance système ?')) {
    alert(
      'Maintenance programmée !\nLa maintenance aura lieu dimanche à 2h du matin.'
    );
  }
}

// Gestion des alertes
function resolveAlert(alertType) {
  if (confirm('Marquer cette alerte comme résolue ?')) {
    alert('Alerte résolue avec succès !');
    // En production: supprimer l'alerte de l'interface
  }
}

function monitorAlert(alertType) {
  alert('Surveillance renforcée activée pour cette alerte.');
}

// Fonction pour rafraîchir le dashboard
function refreshDashboard() {
  location.reload();
}

// Raccourci clavier pour rafraîchir
document.addEventListener('keydown', function (e) {
  if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
    e.preventDefault();
    refreshDashboard();
  }
});
