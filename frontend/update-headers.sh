#!/bin/bash
# Script de mise à jour automatique des headers pour toutes les pages HTML

echo "🔄 Mise à jour des headers du projet ecoCovoit..."

# Configuration des liens selon le type de page
declare -A page_configs=(
    # Pages publiques
    ["index.html"]="index.html:active rechercher-covoiturage.html login.html register.html contact.html"
    ["login.html"]="index.html rechercher-covoiturage.html login.html:active register.html contact.html"
    ["register.html"]="index.html rechercher-covoiturage.html login.html register.html:active contact.html"
    ["contact.html"]="index.html rechercher-covoiturage.html login.html register.html contact.html:active"
    ["mentions.html"]="index.html rechercher-covoiturage.html login.html register.html contact.html mentions.html:active"

    # Pages utilisateur connecté
    ["rechercher-covoiturage.html"]="index.html rechercher-covoiturage.html:active add-voyage.html mes-reservations.html historique.html user-profile.html logout"
    ["add-voyage.html"]="index.html rechercher-covoiturage.html add-voyage.html:active mes-reservations.html historique.html user-profile.html logout"
    ["mes-reservations.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html:active historique.html user-profile.html logout"
    ["historique.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html:active user-profile.html logout"
    ["user-profile.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html user-profile.html:active add-vehicule.html add-preferences.html logout"
    ["add-vehicule.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html user-profile.html add-vehicule.html:active add-preferences.html logout"
    ["add-preferences.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html user-profile.html add-vehicule.html add-preferences.html:active logout"
    ["detail.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html user-profile.html logout"
    ["laisser-avis.html"]="index.html rechercher-covoiturage.html add-voyage.html mes-reservations.html historique.html user-profile.html logout"

    # Pages admin
    ["admin-dashboard.html"]="admin-dashboard.html:active admin-comptes.html employe-avis.html employe-incidents.html logout"
    ["admin-comptes.html"]="admin-dashboard.html admin-comptes.html:active employe-avis.html employe-incidents.html logout"
    ["employe-avis.html"]="admin-dashboard.html admin-comptes.html employe-avis.html:active employe-incidents.html logout"
    ["employe-incidents.html"]="admin-dashboard.html admin-comptes.html employe-avis.html employe-incidents.html:active logout"
)

# Fonction pour générer les liens de navigation
generate_nav_links() {
    local page="$1"
    local config="${page_configs[$page]}"
    local links=""

    for link in $config; do
        if [[ "$link" == *":active"* ]]; then
            local href="${link%:active}"
            local label=$(get_page_label "$href")
            links="$links        <li><a href=\"$href\" class=\"nav-link active\">$label</a></li>\n"
        elif [[ "$link" == "logout" ]]; then
            links="$links        <li><a href=\"#\" class=\"nav-link\" id=\"logout-nav\">Déconnexion</a></li>\n"
        else
            local label=$(get_page_label "$link")
            links="$links        <li><a href=\"$link\" class=\"nav-link\">$label</a></li>\n"
        fi
    done

    echo -e "$links"
}

# Fonction pour obtenir le label d'une page
get_page_label() {
    case "$1" in
        "index.html") echo "Accueil" ;;
        "rechercher-covoiturage.html") echo "Rechercher" ;;
        "login.html") echo "Connexion" ;;
        "register.html") echo "Inscription" ;;
        "contact.html") echo "Contact" ;;
        "mentions.html") echo "Mentions légales" ;;
        "add-voyage.html") echo "Proposer" ;;
        "mes-reservations.html") echo "Mes réservations" ;;
        "historique.html") echo "Historique" ;;
        "user-profile.html") echo "Mon profil" ;;
        "add-vehicule.html") echo "Mes véhicules" ;;
        "add-preferences.html") echo "Préférences" ;;
        "detail.html") echo "Détail" ;;
        "laisser-avis.html") echo "Laisser un avis" ;;
        "admin-dashboard.html") echo "Dashboard" ;;
        "admin-comptes.html") echo "Comptes" ;;
        "employe-avis.html") echo "Avis" ;;
        "employe-incidents.html") echo "Incidents" ;;
        *) echo "Page" ;;
    esac
}

echo "✅ Configuration terminée. Utilisez ce script pour automatiser les mises à jour."
echo "📝 Exemple d'utilisation :"
echo "   - Copier le header standardisé dans chaque page"
echo "   - Adapter les liens selon le type d'utilisateur"
echo "   - Ajouter la classe 'active' sur le lien correspondant"
