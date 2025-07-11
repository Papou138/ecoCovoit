// Etat de l'application
let allUsers = [];
let filteredUsers = [];
let currentPage = 1;
let usersPerPage = 10;
let currentEditingUser = null;

// Eléments du DOM
const usersContainer = document.getElementById('users-container');
const paginationContainer = document.getElementById('pagination-container');
const userModal = document.getElementById('userModal');
const userForm = document.getElementById('userForm');

// Chargement initial
document.addEventListener('DOMContentLoaded', function () {
  loadUsers();
  setupEventListeners();
});

// Configuration des écouteurs d'événements
function setupEventListeners() {
  userForm.addEventListener('submit', handleUserSubmit);

  // Fermer le modal en cliquant à l'extérieur
  window.addEventListener('click', function (event) {
    if (event.target === userModal) {
      closeUserModal();
    }
  });
}

// Chargement des utilisateurs
async function loadUsers() {
  try {
    // Simulation des données utilisateurs
    const mockUsers = [
      {
        id: 1,
        nom: 'Dupont',
        prenom: 'Jean',
        email: 'jean.dupont@email.com',
        telephone: '0123456789',
        role: 'utilisateur',
        status: 'actif',
        date_inscription: '2024-12-15',
        derniere_connexion: '2025-01-06',
        nb_trajets: 15,
        note_moyenne: 4.8,
        credits: 120,
      },
      {
        id: 2,
        nom: 'Martin',
        prenom: 'Marie',
        email: 'marie.martin@email.com',
        telephone: '0987654321',
        role: 'chauffeur',
        status: 'actif',
        date_inscription: '2024-11-20',
        derniere_connexion: '2025-01-05',
        nb_trajets: 42,
        note_moyenne: 4.9,
        credits: 340,
      },
      {
        id: 3,
        nom: 'Durand',
        prenom: 'Pierre',
        email: 'pierre.durand@email.com',
        telephone: '0147258369',
        role: 'utilisateur',
        status: 'suspendu',
        date_inscription: '2024-10-10',
        derniere_connexion: '2024-12-20',
        nb_trajets: 3,
        note_moyenne: 2.1,
        credits: 25,
      },
    ];

    allUsers = mockUsers;
    applyFilters();
  } catch (error) {
    console.error('Erreur:', error);
    showError('Impossible de charger les utilisateurs. Veuillez réessayer.');
  }
}

// Application des filtres
function applyFilters() {
  const statusFilter = document.getElementById('filter-status').value;
  const roleFilter = document.getElementById('filter-role').value;
  const dateFilter = document.getElementById('filter-date-debut').value;
  const searchFilter = document
    .getElementById('filter-search')
    .value.toLowerCase();

  filteredUsers = allUsers.filter((user) => {
    // Filtre par statut
    if (statusFilter !== 'tous' && user.status !== statusFilter) {
      return false;
    }

    // Filtre par rôle
    if (roleFilter !== 'tous' && user.role !== roleFilter) {
      return false;
    }

    // Filtre par date d'inscription
    if (dateFilter && user.date_inscription < dateFilter) {
      return false;
    }

    // Filtre par recherche
    if (
      searchFilter &&
      !user.nom.toLowerCase().includes(searchFilter) &&
      !user.prenom.toLowerCase().includes(searchFilter) &&
      !user.email.toLowerCase().includes(searchFilter)
    ) {
      return false;
    }

    return true;
  });

  currentPage = 1;
  renderUsers();
  renderPagination();
}

// Réinitialisation des filtres
function resetFilters() {
  document.getElementById('filter-status').value = 'tous';
  document.getElementById('filter-role').value = 'tous';
  document.getElementById('filter-date-debut').value = '';
  document.getElementById('filter-search').value = '';
  applyFilters();
}

// Rendu des utilisateurs
function renderUsers() {
  const startIndex = (currentPage - 1) * usersPerPage;
  const endIndex = startIndex + usersPerPage;
  const pageUsers = filteredUsers.slice(startIndex, endIndex);

  if (pageUsers.length === 0) {
    usersContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <h3>Aucun utilisateur trouvé</h3>
                    <p>Aucun utilisateur ne correspond à vos critères de recherche</p>
                </div>
            `;
    return;
  }

  const usersHTML = pageUsers.map((user) => createUserRow(user)).join('');
  usersContainer.innerHTML = `<div class="users-grid">${usersHTML}</div>`;
}

// Création d'une ligne utilisateur
function createUserRow(user) {
  const statusClass = `status-${user.status}`;
  const initials = (user.prenom[0] + user.nom[0]).toUpperCase();

  return `
            <div class="user-row">
                <div class="user-avatar">${initials}</div>
                <div class="user-info">
                    <div class="user-name">${user.prenom} ${user.nom}</div>
                    <div class="user-email">${user.email}</div>
                    <div class="user-join-date">Inscrit le ${formatDate(
                      user.date_inscription
                    )}</div>
                </div>
                <div class="user-stats">
                    <div class="stat-item">
                        <span class="stat-value">${
                          user.nb_trajets
                        }</span> trajets
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${user.note_moyenne}/5</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${user.credits}</span> crédits
                    </div>
                </div>
                <div class="user-status ${statusClass}">
                    ${getStatusText(user.status)}
                </div>
                <div class="user-role">
                    ${getRoleText(user.role)}
                </div>
                <div class="user-actions">
                    <button onclick="editUser(${
                      user.id
                    })" class="btn btn-primary btn-small">
                        Editer
                    </button>
                    ${
                      user.status === 'actif'
                        ? `<button onclick="suspendUser(${user.id})" class="btn btn-warning btn-small">
                            Suspendre
                        </button>`
                        : `<button onclick="activateUser(${user.id})" class="btn btn-success btn-small">
                            Activer
                        </button>`
                    }
                    <button onclick="deleteUser(${
                      user.id
                    })" class="btn btn-danger btn-small">
                        Supprimer
                    </button>
                </div>
            </div>
        `;
}

// Rendu de la pagination
function renderPagination() {
  const totalPages = Math.ceil(filteredUsers.length / usersPerPage);

  if (totalPages <= 1) {
    paginationContainer.innerHTML = '';
    return;
  }

  let paginationHTML = '';

  // Bouton précédent
  paginationHTML += `
            <button onclick="changePage(${currentPage - 1})" ${
    currentPage === 1 ? 'disabled' : ''
  }>
                « précédent
            </button>
        `;

  // Numéros de page
  for (let i = 1; i <= totalPages; i++) {
    if (i === currentPage) {
      paginationHTML += `<button class="active">${i}</button>`;
    } else {
      paginationHTML += `<button onclick="changePage(${i})">${i}</button>`;
    }
  }

  // Bouton suivant
  paginationHTML += `
            <button onclick="changePage(${currentPage + 1})" ${
    currentPage === totalPages ? 'disabled' : ''
  }>
                Suivant »
            </button>
        `;

  paginationContainer.innerHTML = paginationHTML;
}

// Changement de page
function changePage(page) {
  const totalPages = Math.ceil(filteredUsers.length / usersPerPage);
  if (page >= 1 && page <= totalPages) {
    currentPage = page;
    renderUsers();
    renderPagination();
  }
}

// Gestion des modals
function showCreateUserModal() {
  currentEditingUser = null;
  document.getElementById('modal-title').textContent = 'créer un utilisateur';
  userForm.reset();
  userModal.style.display = 'block';
}

function editUser(userId) {
  currentEditingUser = allUsers.find((u) => u.id === userId);
  if (currentEditingUser) {
    document.getElementById('modal-title').textContent =
      'éditer un utilisateur';
    document.getElementById('user-nom').value = currentEditingUser.nom;
    document.getElementById('user-prenom').value = currentEditingUser.prenom;
    document.getElementById('user-email').value = currentEditingUser.email;
    document.getElementById('user-telephone').value =
      currentEditingUser.telephone;
    document.getElementById('user-role').value = currentEditingUser.role;
    document.getElementById('user-status').value = currentEditingUser.status;
    userModal.style.display = 'block';
  }
}

function closeUserModal() {
  userModal.style.display = 'none';
  userForm.reset();
  currentEditingUser = null;
}

// Soumission du formulaire
function handleUserSubmit(e) {
  e.preventDefault();

  const formData = new FormData(userForm);
  const userData = Object.fromEntries(formData.entries());

  if (currentEditingUser) {
    // Mise à jour
    Object.assign(currentEditingUser, userData);
    alert('Utilisateur mis à jour avec succès');
  } else {
    // Création
    userData.id = Math.max(...allUsers.map((u) => u.id)) + 1;
    userData.date_inscription = new Date().toISOString().split('T')[0];
    userData.derniere_connexion = 'Jamais';
    userData.nb_trajets = 0;
    userData.note_moyenne = 0;
    userData.credits = 0;
    allUsers.push(userData);
    alert('Utilisateur créé avec succès');
  }

  closeUserModal();
  applyFilters();
}

// Actions sur les utilisateurs
function suspendUser(userId) {
  if (confirm('Etes-vous sûr de vouloir suspendre cet utilisateur ?')) {
    const user = allUsers.find((u) => u.id === userId);
    if (user) {
      user.status = 'suspendu';
      alert('Utilisateur suspendu avec succès');
      applyFilters();
    }
  }
}

function activateUser(userId) {
  if (confirm('Etes-vous sûr de vouloir activer cet utilisateur ?')) {
    const user = allUsers.find((u) => u.id === userId);
    if (user) {
      user.status = 'actif';
      alert('Utilisateur activé avec succès');
      applyFilters();
    }
  }
}

function deleteUser(userId) {
  if (
    confirm(
      'Etes-vous sûr de vouloir supprimer définitivement cet utilisateur ?'
    )
  ) {
    const index = allUsers.findIndex((u) => u.id === userId);
    if (index !== -1) {
      allUsers.splice(index, 1);
      alert('Utilisateur supprimé avec succès');
      applyFilters();
    }
  }
}

// Autres actions
function refreshUsers() {
  loadUsers();
}

function exportUsers() {
  alert('Export des utilisateurs en cours...');
}

// Utilitaires
function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR');
}

function getStatusText(status) {
  const statusTexts = {
    actif: 'Actif',
    inactif: 'Inactif',
    suspendu: 'Suspendu',
    verifie: 'Vérifié',
  };
  return statusTexts[status] || status;
}

function getRoleText(role) {
  const roleTexts = {
    utilisateur: 'Utilisateur',
    chauffeur: 'Chauffeur',
    employe: 'Employé',
    admin: 'Admin',
  };
  return roleTexts[role] || role;
}

function showError(message) {
  usersContainer.innerHTML = `
            <div class="error-state">
                <div class="error-state-icon"></div>
                <h3>Erreur</h3>
                <p>${message}</p>
                <button onclick="loadUsers()" class="btn btn-primary">
                    Réessayer
                </button>
            </div>
        `;
}
