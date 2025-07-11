# GUIDE D'UTILISATION - NAVIGATION HARMONISEE

## 🎯 OBJECTIF

Ce guide explique comment utiliser et maintenir la navigation harmonisée sur l'ensemble du projet ecoCovoit.

## 📱 NAVIGATION PAR CONTEXTE

### 🌐 Navigation Publique

**Utilisée sur :** index.html, login.html, register.html, contact.html, rechercher-covoiturage.html, mentions.html

**Liens disponibles :**

- 🏠 **Accueil** → index.html
- 🔍 **Rechercher** → rechercher-covoiturage.html
- 📧 **Contact** → contact.html
- 🔑 **Connexion** → login.html
- 📝 **Inscription** → register.html

### 👤 Navigation Utilisateur Connecté

**Utilisée sur :** user-profile.html, mes-reservations.html, historique.html, add-voyage.html, add-vehicule.html, add-preferences.html, detail.html, laisser-avis.html

**Liens disponibles :**

- 🏠 **Accueil** → index.html
- 🔍 **Rechercher** → rechercher-covoiturage.html
- ➕ **Publier** → add-voyage.html
- 📅 **Mes réservations** → mes-reservations.html
- 📋 **Historique** → historique.html
- 👤 **Mon profil** → user-profile.html
- 🚗 **Mes véhicules** → add-vehicule.html
- ⚙️ **Préférences** → add-preferences.html
- 📧 **Contact** → contact.html
- 🚪 **Déconnexion** → Bouton logout()

### 🔧 Navigation Admin

**Utilisée sur :** admin-dashboard.html, admin-comptes.html, employe-avis.html, employe-incidents.html

**Liens disponibles :**

- 📊 **Dashboard** → admin-dashboard.html
- 👥 **Comptes** → admin-comptes.html
- ⭐ **Avis** → employe-avis.html
- ⚠️ **Incidents** → employe-incidents.html
- 📧 **Contact** → contact.html
- 🚪 **Déconnexion** → Bouton logout()

## 🎨 STYLE ET APPARENCE

### Icônes Font Awesome

Toutes les icônes utilisent Font Awesome 6.0.0 :

```html
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
/>
```

### Classe Active

La page courante est identifiée par la classe `active` :

```html
<a href="user-profile.html" class="nav-link active"></a>
```

### Bouton Déconnexion

Utilise un bouton pour une meilleure sécurité :

```html
<button class="nav-link logout-btn" onclick="logout()" type="button">
  <i class="fas fa-sign-out-alt"></i> Déconnexion
</button>
```

## 🔧 MAINTENANCE

### Ajouter une Nouvelle Page

1. **Copier le template** depuis `_header-template.html`
2. **Choisir le type de navigation** (publique/utilisateur/admin)
3. **Décommenter les liens appropriés**
4. **Ajouter la classe `active`** sur le lien correspondant

### Exemple pour une nouvelle page utilisateur :

```html
<!-- Décommenter la navigation utilisateur -->
<li>
  <a href="nouvelle-page.html" class="nav-link active">
    <i class="fas fa-new-icon"></i> Nouvelle Page
  </a>
</li>
```

### Modifier la Navigation

1. **Mettre à jour** `_header-template.html`
2. **Exécuter le script** `harmonize-navigation.ps1`
3. **Vérifier** avec `simple-nav-check.ps1`

## 🧪 TESTS

### Script de Vérification

```bash
cd c:\DEV\ecoCovoit\frontend
powershell -ExecutionPolicy Bypass -File simple-nav-check.ps1
```

### Tests Manuels

- [ ] Vérifier la navigation sur mobile
- [ ] Tester le menu hamburger
- [ ] Vérifier les liens actifs
- [ ] Tester la déconnexion
- [ ] Vérifier l'accessibilité

## 📝 BONNES PRATIQUES

### ✅ A Faire

- Utiliser Font Awesome pour les icônes
- Maintenir la cohérence des liens
- Respecter la hiérarchie des pages
- Tester sur différents appareils
- Garder le lien Contact accessible

### ❌ A Eviter

- Mélanger emojis et Font Awesome
- Oublier la classe `active`
- Créer des liens orphelins
- Négliger l'accessibilité
- Modifier directement les pages sans le template

## 🚀 DEPANNAGE

### Problèmes Courants

**Navigation ne s'affiche pas :**

- Vérifier que Font Awesome est chargé
- Contrôler les liens CSS \_header.css et \_commun.css

**Icônes manquantes :**

- Vérifier la connexion CDN Font Awesome
- Contrôler la syntaxe des classes fas

**Menu mobile ne fonctionne pas :**

- Vérifier que menu.js est chargé
- Contrôler les IDs et classes du menu hamburger

**Classe active ne fonctionne pas :**

- Vérifier la syntaxe de la classe `active`
- S'assurer qu'elle est sur le bon lien

## 📞 SUPPORT

Pour toute question ou problème :

- Consulter le template `_header-template.html`
- Utiliser les scripts de vérification
- Référer au rapport d'harmonisation
- Tester avec les pages existantes

## 🎉 RESULTAT

Une navigation harmonieuse, intuitive et efficace sur l'ensemble du projet ecoCovoit !

---

_Guide créé le 9 juillet 2025_
_Navigation harmonisée avec succès_
