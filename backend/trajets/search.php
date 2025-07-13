<?php

/**
 * API de recherche de trajets
 *
 * Endpoint: GET /backend/trajets/search.php
 * Paramètres: depart, arrivee, date, prix_max, ecologique, note_min, duree_max
 *
 * Cette API correspond aux US 3 et 4 (vue et filtres des covoiturages)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier que c'est une requête GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  jsonResponse(false, 'Méthode non autorisée', null, 405);
}

try {
  // === RECUPERATION DES PARAMETRES ===

  $depart = trim($_GET['depart'] ?? '');
  $arrivee = trim($_GET['arrivee'] ?? '');
  $date = $_GET['date'] ?? '';

  // Filtres optionnels
  $prixMax = isset($_GET['prix_max']) ? (float)$_GET['prix_max'] : null;
  $ecologique = isset($_GET['ecologique']) ? (bool)$_GET['ecologique'] : null;
  $noteMin = isset($_GET['note_min']) ? (float)$_GET['note_min'] : null;
  $dureeMax = isset($_GET['duree_max']) ? (int)$_GET['duree_max'] : null; // en minutes

  // === VALIDATION DES PARAMETRES OBLIGATOIRES ===

  if (empty($depart)) {
    jsonResponse(false, 'La ville de départ est requise');
  }

  if (empty($arrivee)) {
    jsonResponse(false, 'La ville d\'arrivée est requise');
  }

  if (empty($date)) {
    jsonResponse(false, 'La date est requise');
  }

  // Validation du format de date
  $dateObj = DateTime::createFromFormat('Y-m-d', $date);
  if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    jsonResponse(false, 'Format de date invalide (YYYY-MM-DD attendu)');
  }

  // Vérifier que la date n'est pas dans le passé
  $today = new DateTime('today');
  if ($dateObj < $today) {
    jsonResponse(false, 'Impossible de rechercher des trajets dans le passé');
  }

  // === RECHERCHE DANS LA BASE DE DONNEES ===

  $trajets = DB::searchTrajets($depart, $arrivee, $date);

  // === APPLICATION DES FILTRES ===

  $trajetsFiltered = [];
  foreach ($trajets as $trajet) {
    // Filtre prix maximum
    if ($prixMax !== null && $trajet['prix'] > $prixMax) {
      continue;
    }

    // Filtre écologique (véhicule électrique)
    if ($ecologique !== null && $ecologique && !$trajet['est_ecologique']) {
      continue;
    }

    // Filtre note minimum du chauffeur
    if ($noteMin !== null && $trajet['note_moyenne'] < $noteMin) {
      continue;
    }

    // Filtre durée maximum
    if ($dureeMax !== null && isset($trajet['duree_estimee']) && $trajet['duree_estimee'] > $dureeMax) {
      continue;
    }

    // Enrichir les données du trajet
    $trajet['date_depart_formatted'] = date('d/m/Y H:i', strtotime($trajet['date_depart']));
    $trajet['prix_formatted'] = number_format($trajet['prix'], 2, ',', ' ') . ' €';

    // Calculer le temps de trajet estimé si pas défini
    if (!isset($trajet['duree_estimee']) || !$trajet['duree_estimee']) {
      $trajet['duree_estimee'] = estimateJourneyDuration($depart, $arrivee);
    }

    $trajet['duree_formatted'] = formatDuration($trajet['duree_estimee']);

    // Statut de disponibilité
    $trajet['disponible'] = $trajet['nombre_places_restantes'] > 0 && $trajet['statut'] === 'planifie';

    // Badge écologique
    $trajet['badge_eco'] = $trajet['est_ecologique'] ? '🌱 Ecologique' : null;

    $trajetsFiltered[] = $trajet;
  }

  // === TRI DES RESULTATS ===

  $sortBy = $_GET['sort'] ?? 'date'; // date, prix, note, duree
  $sortOrder = $_GET['order'] ?? 'asc'; // asc, desc

  usort($trajetsFiltered, function ($a, $b) use ($sortBy, $sortOrder) {
    $valueA = $a[$sortBy] ?? 0;
    $valueB = $b[$sortBy] ?? 0;

    if ($sortBy === 'date_depart') {
      $valueA = strtotime($a['date_depart']);
      $valueB = strtotime($b['date_depart']);
    }

    $comparison = $valueA <=> $valueB;
    return $sortOrder === 'desc' ? -$comparison : $comparison;
  });

  // === SUGGESTION D'ALTERNATIVES ===

  $suggestions = [];
  if (empty($trajetsFiltered)) {
    // Chercher des dates alternatives (±3 jours)
    for ($i = 1; $i <= 3; $i++) {
      $dateAlternative = clone $dateObj;
      $dateAlternative->modify("+$i day");
      $altTrajets = DB::searchTrajets($depart, $arrivee, $dateAlternative->format('Y-m-d'));

      if (!empty($altTrajets)) {
        $suggestions[] = [
          'date' => $dateAlternative->format('Y-m-d'),
          'date_formatted' => $dateAlternative->format('d/m/Y'),
          'nombre_trajets' => count($altTrajets),
          'prix_min' => min(array_column($altTrajets, 'prix'))
        ];
      }

      // Aussi vérifier les jours précédents si on n'est pas aujourd'hui
      if ($i <= 2) {
        $dateAlternative = clone $dateObj;
        $dateAlternative->modify("-$i day");

        if ($dateAlternative >= $today) {
          $altTrajets = DB::searchTrajets($depart, $arrivee, $dateAlternative->format('Y-m-d'));

          if (!empty($altTrajets)) {
            $suggestions[] = [
              'date' => $dateAlternative->format('Y-m-d'),
              'date_formatted' => $dateAlternative->format('d/m/Y'),
              'nombre_trajets' => count($altTrajets),
              'prix_min' => min(array_column($altTrajets, 'prix'))
            ];
          }
        }
      }
    }

    // Trier les suggestions par date
    usort($suggestions, function ($a, $b) {
      return strtotime($a['date']) <=> strtotime($b['date']);
    });
  }

  // === STATISTIQUES DE RECHERCHE ===

  $stats = [
    'total_trouve' => count($trajetsFiltered),
    'prix_moyen' => !empty($trajetsFiltered) ? round(array_sum(array_column($trajetsFiltered, 'prix')) / count($trajetsFiltered), 2) : 0,
    'nb_ecologiques' => count(array_filter($trajetsFiltered, function ($t) {
      return $t['est_ecologique'];
    })),
    'places_totales' => array_sum(array_column($trajetsFiltered, 'nombre_places_restantes'))
  ];

  // === REPONSE ===

  $responseData = [
    'criteres_recherche' => [
      'depart' => $depart,
      'arrivee' => $arrivee,
      'date' => $date,
      'date_formatted' => $dateObj->format('d/m/Y'),
      'filtres_appliques' => array_filter([
        'prix_max' => $prixMax,
        'ecologique' => $ecologique,
        'note_min' => $noteMin,
        'duree_max' => $dureeMax
      ])
    ],
    'trajets' => $trajetsFiltered,
    'statistiques' => $stats,
    'suggestions_alternatives' => $suggestions,
    'message' => empty($trajetsFiltered)
      ? 'Aucun trajet trouvé pour ces critères'
      : count($trajetsFiltered) . ' trajet(s) trouvé(s)'
  ];

  jsonResponse(true, $responseData['message'], $responseData);
} catch (Exception $e) {
  error_log("Erreur search API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la recherche', null, 500);
}

/**
 * Estime la durée d'un trajet en minutes (fonction simplifiée)
 */
function estimateJourneyDuration($depart, $arrivee)
{
  // Estimation basique basée sur des distances approximatives
  $distances = [
    'paris-lyon' => 120,
    'paris-marseille' => 180,
    'lyon-marseille' => 80,
    'paris-bordeaux' => 150,
    'lyon-bordeaux' => 140,
    'toulouse-bordeaux' => 60
  ];

  $key = strtolower($depart) . '-' . strtolower($arrivee);
  $reverseKey = strtolower($arrivee) . '-' . strtolower($depart);

  if (isset($distances[$key])) {
    return $distances[$key];
  } elseif (isset($distances[$reverseKey])) {
    return $distances[$reverseKey];
  }

  // Estimation par défaut basée sur la distance entre les villes
  return 90; // 1h30 par défaut
}

/**
 * Formate une durée en minutes en format lisible
 */
function formatDuration($minutes)
{
  if ($minutes < 60) {
    return $minutes . ' min';
  }

  $hours = intval($minutes / 60);
  $remainingMinutes = $minutes % 60;

  if ($remainingMinutes == 0) {
    return $hours . 'h';
  }

  return $hours . 'h' . sprintf('%02d', $remainingMinutes);
}
