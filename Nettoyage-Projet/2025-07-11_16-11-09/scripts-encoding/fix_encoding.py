#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pour corriger les problèmes d'encodage de caractères dans les fichiers HTML
Ce script remplace les caractères mal encodés par leurs équivalents UTF-8 corrects
"""

import os
import glob
import re


def fix_encoding_issues():
    """Corrige les problèmes d'encodage dans tous les fichiers HTML"""

    # Définir les remplacements de caractères
    replacements = {
        "Ã©": "é",
        "Ã¨": "è",
        "Ã ": "à",
        "Ã¡": "á",
        "Ã¢": "â",
        "Ã´": "ô",
        "Ã¹": "ù",
        "Ã»": "û",
        "Ã®": "î",
        "Ã¯": "ï",
        "Ã§": "ç",
        "Ã‰": "E",
        "Ã€": "A",
        "Ã‚": "Â",
        'Ã"': "Ô",
        "Ã™": "Ù",
        "Ã›": "Û",
        "Ã‡": "Ç",
        "Ã±": "ñ",
        "Ãª": "ê",
        "Ã¤": "ä",
        "Ã¶": "ö",
        "Ã¼": "ü",
        "Ã„": "Ä",
        "Ã–": "Ö",
        "Ãœ": "Ü",
        "Ã³": "ó",
        "Ã²": "ò",
        "Ã­": "í",
        "Ã¬": "ì",
        "Ã": "Í",
        "ÃŒ": "Ì",
        "Ã‹": "Ë",
        "Ã«": "ë",
        "Ã¢â‚¬â„¢": "'",
        "Ã¢â‚¬Â": "'",
        'Ã¢â‚¬Å"': '"',
        "Ã¢â‚¬Â": '"',
        "Ã¢â‚¬â€œ": "–",
        'Ã¢â‚¬â€"': "—",
        "Ã¢â‚¬Â¦": "…",
        "Ã¢â‚¬Â°": "°",
        "Ã¢â‚¬Â«": "«",
        "Ã¢â‚¬Â»": "»",
        "Ã¢â‚¬": "€",
        "Ãªtre": "être",
        "Ã©cologique": "écologique",
        "Ã©lectriques": "électriques",
        "Ã©conomies": "économies",
        "Ã©coresponsable": "écoresponsable",
        "rÃ©duisant": "réduisant",
        "rÃ©duisez": "réduisez",
        "rÃ©alisez": "réalisez",
        "rÃ©servations": "réservations",
        "rÃ©servÃ©s": "réservés",
        "prÃ©fÃ©rences": "préférences",
        "privilÃ©giant": "privilégiant",
        "sÃ©curitÃ©": "sécurité",
        "sÃ©curisÃ©": "sécurisé",
        "sÃ©curisÃ©e": "sécurisée",
        "vÃ©hicules": "véhicules",
        "vÃ©rifiÃ©s": "vérifiés",
        "crÃ©ez": "créez",
        "crÃ©dits": "crédits",
        "coÃ»ts": "coûts",
        "dÃ©part": "départ",
        "dÃ©placement": "déplacement",
        "dÃ©placements": "déplacements",
        "dÃ©marrage": "démarrage",
        "dÃ©connexion": "déconnexion",
        "mobilitÃ©": "mobilité",
        "itinÃ©raire": "itinéraire",
        "caractÃ¨res": "caractères",
        "lÃ©gales": "légales",
        "durÃ©e": "durée",
        "numÃ©riques": "numériques",
        "avancÃ©s": "avancés",
        "Ã©toile": "étoile",
        "Ã©toiles": "étoiles",
        "Ã©vitÃ©s": "évités",
        "Ã©conomisÃ©s": "économisés",
        "rÃ©alisÃ©s": "réalisés",
        "communautÃ©": "communauté",
        "systÃ¨me": "système",
        "Ã©valuation": "évaluation",
        "gÃ©rez": "gérez",
        "gÃ©rer": "gérer",
        "protÃ©gez": "protégez",
        "RÃ©duisons": "Réduisons",
        "DÃ©finir": "Définir",
        "VÃ©rification": "Vérification",
        "amÃ©lioration": "amélioration",
        "amÃ©liorÃ©e": "améliorée",
        "crÃ©er": "créer",
        "complÃ©tion": "complétion",
        "franÃ§aises": "françaises",
        "oÃ¹": "où",
        "D'oÃ¹": "D'où",
        "OÃ¹": "Où",
        "Ã¤": "ä",
    }

    # Trouver tous les fichiers HTML
    html_files = glob.glob("frontend/**/*.html", recursive=True)

    total_files = len(html_files)
    modified_files = 0

    print(f"Correction des caractères d'encodage dans {total_files} fichiers HTML...")

    for file_path in html_files:
        try:
            # Lire le fichier
            with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()

            original_content = content

            # Appliquer tous les remplacements
            for bad_char, good_char in replacements.items():
                content = content.replace(bad_char, good_char)

            # Vérifier si le fichier a été modifié
            if content != original_content:
                # Ecrire le contenu corrigé
                with open(file_path, "w", encoding="utf-8") as f:
                    f.write(content)
                modified_files += 1
                print(f"✓ Corrigé: {os.path.basename(file_path)}")
            else:
                print(f"- Aucune modification: {os.path.basename(file_path)}")

        except Exception as e:
            print(f"✗ Erreur lors du traitement de {file_path}: {str(e)}")

    print(f"\nTerminé! {modified_files} fichiers sur {total_files} ont été corrigés.")
    print("Les caractères d'encodage ont été convertis en UTF-8 correct.")


if __name__ == "__main__":
    fix_encoding_issues()
