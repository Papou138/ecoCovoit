# 📋 Guide d'optimisation des headers - Projet ecoCovoit

## 🎯 Objectif
Standardiser tous les headers HTML du projet pour garantir une navigation cohérente et maintenir une architecture CSS unifiée.

## ❌ Problèmes identifiés

### Structures incohérentes actuelles :
- **index.html/historique.html** : `<header><div class="header-content">...`
- **user-profile.html** : `<header><nav class="navbar"><div class="nav-container">...`
- **Pages admin** : `<header class="header">...`

### Classes CSS différentes :
- `.header-content` vs `.nav-container` vs `.header`
- `.logo-container` vs `.nav-logo`
- `.main-nav` vs `.navbar`

### Assets incohérents :
- `logo-ecoRide.png` vs `logo_ecoCovoit.jpg`

## ✅ Solution proposée

### Structure standardisée
```html
<header>
  <div class="header-content">
    <div class="logo-container">
      <img src="assets/img/logo-ecoRide.png" alt="Logo ecoCovoit" class="logo" width="50" height="50" loading="lazy" />
      <h1 class="app-title">ecoCovoit</h1>
    </div>

    <button id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu Principal" aria-expanded="false" aria-controls="main-nav" type="button">
      <span class="hamburger-line" aria-hidden="true"></span>
      <span class="hamburger-line" aria-hidden="true"></span>
      <span class="hamburger-line" aria-hidden="true"></span>
    </button>

    <nav id="main-nav" class="main-nav" role="navigation" aria-label="Menu principal de navigation">
      <ul class="nav-list">
        <!-- Links générés selon le type de page -->
      </ul>
    </nav>
  </div>
</header>
```

## 🔧 Plan de mise à jour

### Phase 1 : Pages prioritaires ✅
- [x] user-profile.html - **TERMINÉ**
- [x] historique.html - **TERMINÉ**

### Phase 2 : Pages utilisateur connecté
- [ ] rechercher-covoiturage.html
- [ ] add-voyage.html
- [ ] mes-reservations.html
- [ ] add-vehicule.html
- [ ] add-preferences.html
- [ ] detail.html
- [ ] laisser-avis.html

### Phase 3 : Pages publiques
- [ ] login.html
- [ ] register.html
- [ ] contact.html
- [ ] mentions.html

### Phase 4 : Pages admin
- [ ] admin-dashboard.html
- [ ] admin-comptes.html
- [ ] employe-avis.html
- [ ] employe-incidents.html

## 🎨 Configuration des liens par type de page

### Pages publiques (non connecté)
```html
<li><a href="index.html" class="nav-link">Accueil</a></li>
<li><a href="rechercher-covoiturage.html" class="nav-link">Rechercher</a></li>
<li><a href="login.html" class="nav-link">Connexion</a></li>
<li><a href="register.html" class="nav-link">Inscription</a></li>
<li><a href="contact.html" class="nav-link">Contact</a></li>
```

### Pages utilisateur connecté
```html
<li><a href="index.html" class="nav-link">Accueil</a></li>
<li><a href="rechercher-covoiturage.html" class="nav-link">Rechercher</a></li>
<li><a href="add-voyage.html" class="nav-link">Proposer</a></li>
<li><a href="mes-reservations.html" class="nav-link">Mes réservations</a></li>
<li><a href="historique.html" class="nav-link">Historique</a></li>
<li><a href="user-profile.html" class="nav-link">Mon profil</a></li>
<li><a href="#" class="nav-link" id="logout-nav">Déconnexion</a></li>
```

### Pages admin
```html
<li><a href="admin-dashboard.html" class="nav-link">Dashboard</a></li>
<li><a href="admin-comptes.html" class="nav-link">Comptes</a></li>
<li><a href="employe-avis.html" class="nav-link">Avis</a></li>
<li><a href="employe-incidents.html" class="nav-link">Incidents</a></li>
<li><a href="#" class="nav-link" id="logout-nav">Déconnexion</a></li>
```

## 📋 Checklist de mise à jour

Pour chaque page HTML :

1. **Remplacer la structure du header** par le template standardisé
2. **Ajouter `class="active"`** sur le lien correspondant à la page actuelle
3. **Configurer les liens** selon le type d'utilisateur (public/connecté/admin)
4. **Vérifier les dépendances CSS** :
   - `<link rel="stylesheet" href="assets/css/_header.css" />`
   - `<link rel="stylesheet" href="assets/css/_commun.css" />`
5. **Ajouter le script du menu** :
   - `<script src="assets/js/menu.js"></script>`
6. **Tester la responsivité** mobile et desktop

## 🎯 Avantages de cette standardisation

### Cohérence visuelle
- Navigation identique sur toutes les pages
- Expérience utilisateur unifiée
- Design professionnel et moderne

### Maintenabilité
- Une seule structure à maintenir
- Modifications centralisées dans `_header.css`
- Code HTML standardisé

### Performance
- CSS mutualisé et optimisé
- Chargement plus rapide
- Moins de code dupliqué

### Accessibilité
- Navigation par clavier améliorée
- Attributs ARIA appropriés
- Structure sémantique cohérente

## 🚀 Prochaines étapes

1. **Terminer la mise à jour** de toutes les pages selon ce guide
2. **Tester l'affichage** sur tous les types d'écrans
3. **Valider la navigation** entre les différentes sections
4. **Optimiser le JavaScript** de menu si nécessaire
5. **Documenter les changements** pour l'équipe
