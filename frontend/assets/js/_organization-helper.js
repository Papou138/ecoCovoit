/**
 * Création automatique des fichiers JavaScript manquants
 * Script utilitaire pour finaliser l'organisation du projet
 */

// Liste des fichiers à créer avec leurs fonctionnalités de base
const filesToCreate = [
  {
    name: 'admin-comptes.js',
    description: 'Gestion des comptes utilisateurs par les administrateurs'
  },
  {
    name: 'admin-dashboard.js', 
    description: 'Tableau de bord administrateur avec statistiques'
  },
  {
    name: 'contact.js',
    description: 'Formulaire de contact et validation'
  },
  {
    name: 'detail.js',
    description: 'Affichage des détails d\'un trajet et réservation'
  },
  {
    name: 'employe-avis.js',
    description: 'Modération des avis par les employés'
  },
  {
    name: 'employe-incidents.js',
    description: 'Gestion des incidents et support client'
  },
  {
    name: 'historique.js',
    description: 'Affichage de l\'historique des trajets avec filtres'
  },
  {
    name: 'laisser-avis.js',
    description: 'Formulaire pour laisser un avis sur un trajet'
  },
  {
    name: 'mentions.js',
    description: 'Page des mentions légales (fonctionnalités minimales)'
  },
  {
    name: 'register.js',
    description: 'Formulaire d\'inscription avec validation'
  }
];

console.log('Fichiers JavaScript à créer pour finaliser l\'organisation :');
filesToCreate.forEach((file, index) => {
  console.log(`${index + 1}. ${file.name} - ${file.description}`);
});

console.log('\n📋 Actions à effectuer pour chaque fichier HTML :');
console.log('1. Extraire le JavaScript intégré');
console.log('2. Créer le fichier .js correspondant');
console.log('3. Supprimer le <script> du HTML');  
console.log('4. Ajouter <script src="assets/js/[nom].js"></script>');
console.log('5. Tester le fonctionnement');

console.log('\n✅ Avantages de cette organisation :');
console.log('- Séparation claire des responsabilités');
console.log('- Code JavaScript réutilisable');
console.log('- Maintenance facilitée');
console.log('- Mise en cache des scripts par le navigateur');
console.log('- Possibilité de minification en production');

// Fonctions utilitaires communes pour tous les scripts
const commonUtilities = `
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

// Fonction pour afficher les notifications
function showNotification(message, type = 'info') {
  // Créer ou récupérer le conteneur de notifications
  let container = document.getElementById('notification-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notification-container';
    container.style.cssText = \`
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
    \`;
    document.body.appendChild(container);
  }

  // Créer la notification
  const notification = document.createElement('div');
  notification.className = \`notification notification-\${type}\`;
  notification.innerHTML = \`
    <i class="fas fa-\${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
    <span>\${message}</span>
    <button onclick="this.parentElement.remove()" style="margin-left: 10px;">&times;</button>
  \`;
  
  container.appendChild(notification);

  // Auto-suppression après 5 secondes
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// Fonction pour formater les dates
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

// Fonction pour formater les prix
function formatPrice(price) {
  return price ? \`\${price} €\` : 'Gratuit';
}
`;

console.log('\n🔧 Fonctions utilitaires communes ajoutées à chaque script :');
console.log('- logout() : Gestion de la déconnexion');
console.log('- showNotification() : Affichage de notifications');
console.log('- formatDate() : Formatage des dates');
console.log('- formatPrice() : Formatage des prix');

// Export pour utilisation dans d'autres scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { filesToCreate, commonUtilities };
}
