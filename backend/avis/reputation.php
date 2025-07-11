<?php

/**
 * Jour 7 - Système d'évaluations et avis
 * API de réputation et classements
 * Développé le 11 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/DB.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

/**
 * Calcul du score de réputation avancé
 */
function calculateAdvancedReputationScore($userId)
{
  $user = DB::findById('utilisateurs', $userId);
  if (!$user) {
    return 0;
  }

  $avis = DB::findAll('avis', ['evalue_id' => $userId, 'valide' => true]);

  if (empty($avis)) {
    return 0;
  }

  $score = 0;
  $nombreAvis = count($avis);
  $moyenneNotes = array_sum(array_column($avis, 'note')) / $nombreAvis;

  // Base : moyenne des notes (40% du score)
  $score += ($moyenneNotes / 5) * 400;

  // Bonus nombre d'avis (30% du score) - plafonné à 50 avis
  $bonusAvis = min($nombreAvis, 50) * 6;
  $score += $bonusAvis;

  // Bonus régularité (15% du score)
  $avisChauffeur = array_filter($avis, function ($a) {
    return $a['type'] === 'chauffeur';
  });
  $avisPassager = array_filter($avis, function ($a) {
    return $a['type'] === 'passager';
  });

  if (!empty($avisChauffeur) && !empty($avisPassager)) {
    $score += 75; // Bonus pour avoir les deux types d'avis
  }

  // Bonus récence (10% du score)
  $avisRecents = array_filter($avis, function ($a) {
    return strtotime($a['date_creation']) > strtotime('-3 months');
  });

  if (count($avisRecents) >= 3) {
    $score += 50;
  }

  // Malus pour notes très basses (5% du score)
  $notesTresBasses = array_filter($avis, function ($a) {
    return $a['note'] <= 2;
  });
  $pourcentageNotesBasses = count($notesTresBasses) / $nombreAvis;

  if ($pourcentageNotesBasses > 0.2) { // Plus de 20% de notes <= 2
    $score -= 25;
  }

  // Score final sur 1000
  return max(0, min(1000, round($score)));
}

/**
 * Récupération du classement des utilisateurs
 */
function getUserRankings($type = 'global', $limit = 50)
{
  $users = DB::findAll('utilisateurs');
  $usersWithRatings = [];

  foreach ($users as $user) {
    if ($user['statut'] !== 'actif') {
      continue;
    }

    $avis = DB::findAll('avis', ['evalue_id' => $user['id'], 'valide' => true]);

    if (empty($avis)) {
      continue; // Ignorer les utilisateurs sans avis
    }

    $avisChauffeur = array_filter($avis, function ($a) {
      return $a['type'] === 'chauffeur';
    });
    $avisPassager = array_filter($avis, function ($a) {
      return $a['type'] === 'passager';
    });

    $moyenneChauffeur = !empty($avisChauffeur) ?
      round(array_sum(array_column($avisChauffeur, 'note')) / count($avisChauffeur), 2) : null;

    $moyennePassager = !empty($avisPassager) ?
      round(array_sum(array_column($avisPassager, 'note')) / count($avisPassager), 2) : null;

    // Filtrage par type
    $include = false;
    $scoreReference = 0;

    switch ($type) {
      case 'chauffeur':
        $include = $moyenneChauffeur !== null && count($avisChauffeur) >= 3;
        $scoreReference = $moyenneChauffeur ?? 0;
        break;

      case 'passager':
        $include = $moyennePassager !== null && count($avisPassager) >= 3;
        $scoreReference = $moyennePassager ?? 0;
        break;

      case 'global':
      default:
        $include = count($avis) >= 3;
        $scoreReference = $user['note_moyenne'] ?? 0;
        break;
    }

    if (!$include) {
      continue;
    }

    $userRanking = [
      'id' => $user['id'],
      'nom' => $user['nom'],
      'prenom' => $user['prenom'],
      'note_moyenne' => $user['note_moyenne'] ?? 0,
      'nombre_avis' => count($avis),
      'note_chauffeur' => $moyenneChauffeur,
      'note_passager' => $moyennePassager,
      'niveau_reputation' => $user['niveau_reputation'] ?? 'nouveau',
      'score_reputation' => calculateAdvancedReputationScore($user['id']),
      'score_reference' => $scoreReference,
      'derniere_activite' => $user['derniere_connexion'] ?? $user['date_creation']
    ];

    // Statistiques additionnelles
    $trajets = DB::findAll('trajets', ['chauffeur_id' => $user['id'], 'statut' => 'termine']);
    $participations = DB::findAll('participations', ['utilisateur_id' => $user['id'], 'statut' => 'confirmee']);

    $userRanking['trajets_effectues'] = count($trajets);
    $userRanking['trajets_participes'] = count($participations);
    $userRanking['total_trajets'] = count($trajets) + count($participations);

    $usersWithRatings[] = $userRanking;
  }

  // Tri selon le type de classement
  switch ($type) {
    case 'chauffeur':
      usort($usersWithRatings, function ($a, $b) {
        if ($a['note_chauffeur'] === $b['note_chauffeur']) {
          return $b['nombre_avis'] - $a['nombre_avis'];
        }
        return $b['note_chauffeur'] <=> $a['note_chauffeur'];
      });
      break;

    case 'passager':
      usort($usersWithRatings, function ($a, $b) {
        if ($a['note_passager'] === $b['note_passager']) {
          return $b['nombre_avis'] - $a['nombre_avis'];
        }
        return $b['note_passager'] <=> $a['note_passager'];
      });
      break;

    case 'reputation':
      usort($usersWithRatings, function ($a, $b) {
        return $b['score_reputation'] - $a['score_reputation'];
      });
      break;

    case 'global':
    default:
      usort($usersWithRatings, function ($a, $b) {
        if ($a['note_moyenne'] === $b['note_moyenne']) {
          return $b['nombre_avis'] - $a['nombre_avis'];
        }
        return $b['note_moyenne'] <=> $a['note_moyenne'];
      });
      break;
  }

  // Ajouter le rang
  foreach ($usersWithRatings as $index => &$userRanking) {
    $userRanking['rang'] = $index + 1;
  }

  return array_slice($usersWithRatings, 0, $limit);
}

/**
 * Récupération des badges et récompenses d'un utilisateur
 */
function getUserBadges($userId)
{
  $user = DB::findById('utilisateurs', $userId);
  if (!$user) {
    return [];
  }

  $badges = [];

  // Récupérer les données nécessaires
  $avis = DB::findAll('avis', ['evalue_id' => $userId, 'valide' => true]);
  $avisChauffeur = array_filter($avis, function ($a) {
    return $a['type'] === 'chauffeur';
  });
  $avisPassager = array_filter($avis, function ($a) {
    return $a['type'] === 'passager';
  });
  $trajets = DB::findAll('trajets', ['chauffeur_id' => $userId, 'statut' => 'termine']);
  $participations = DB::findAll('participations', ['utilisateur_id' => $userId, 'statut' => 'confirmee']);

  // Badge Nouveauté
  $anciennete = (time() - strtotime($user['date_creation'])) / (365 * 24 * 3600);
  if ($anciennete < 0.25) { // Moins de 3 mois
    $badges[] = [
      'id' => 'nouveau_membre',
      'nom' => 'Nouveau Membre',
      'description' => 'Bienvenue dans la communauté !',
      'icone' => '🆕',
      'couleur' => 'green'
    ];
  }

  // Badges de notes
  $noteMoyenne = $user['note_moyenne'] ?? 0;

  if ($noteMoyenne >= 4.8 && count($avis) >= 10) {
    $badges[] = [
      'id' => 'excellence',
      'nom' => 'Excellence',
      'description' => 'Note moyenne ≥ 4.8/5 avec au moins 10 avis',
      'icone' => '⭐',
      'couleur' => 'gold'
    ];
  } elseif ($noteMoyenne >= 4.5 && count($avis) >= 5) {
    $badges[] = [
      'id' => 'qualite_superieure',
      'nom' => 'Qualité Supérieure',
      'description' => 'Note moyenne ≥ 4.5/5 avec au moins 5 avis',
      'icone' => '🌟',
      'couleur' => 'silver'
    ];
  }

  // Badges de volume
  if (count($avis) >= 50) {
    $badges[] = [
      'id' => 'veteran',
      'nom' => 'Vétéran',
      'description' => '50+ avis reçus',
      'icone' => '🏆',
      'couleur' => 'gold'
    ];
  } elseif (count($avis) >= 20) {
    $badges[] = [
      'id' => 'experimente',
      'nom' => 'Expérimenté',
      'description' => '20+ avis reçus',
      'icone' => '🥈',
      'couleur' => 'silver'
    ];
  } elseif (count($avis) >= 10) {
    $badges[] = [
      'id' => 'confirme',
      'nom' => 'Confirmé',
      'description' => '10+ avis reçus',
      'icone' => '🥉',
      'couleur' => 'bronze'
    ];
  }

  // Badges spécialisés
  if (!empty($avisChauffeur) && !empty($avisPassager)) {
    $badges[] = [
      'id' => 'polyvalent',
      'nom' => 'Polyvalent',
      'description' => 'Avis positifs en tant que chauffeur ET passager',
      'icone' => '🎭',
      'couleur' => 'purple'
    ];
  }

  if (count($avisChauffeur) >= 20) {
    $noteMoyenneChauffeur = array_sum(array_column($avisChauffeur, 'note')) / count($avisChauffeur);
    if ($noteMoyenneChauffeur >= 4.5) {
      $badges[] = [
        'id' => 'super_chauffeur',
        'nom' => 'Super Chauffeur',
        'description' => 'Excellence en conduite (20+ avis ≥ 4.5/5)',
        'icone' => '🚗',
        'couleur' => 'blue'
      ];
    }
  }

  if (count($avisPassager) >= 20) {
    $noteMoyennePassager = array_sum(array_column($avisPassager, 'note')) / count($avisPassager);
    if ($noteMoyennePassager >= 4.5) {
      $badges[] = [
        'id' => 'passager_model',
        'nom' => 'Passager Modèle',
        'description' => 'Excellence en tant que passager (20+ avis ≥ 4.5/5)',
        'icone' => '👥',
        'couleur' => 'orange'
      ];
    }
  }

  // Badge Régularité
  $avisRecents = array_filter($avis, function ($a) {
    return strtotime($a['date_creation']) > strtotime('-6 months');
  });

  if (count($avisRecents) >= 10) {
    $badges[] = [
      'id' => 'regulier',
      'nom' => 'Régulier',
      'description' => '10+ avis dans les 6 derniers mois',
      'icone' => '📅',
      'couleur' => 'green'
    ];
  }

  // Badge Écologique (basé sur les km parcourus)
  $totalKm = array_sum(array_column($trajets, 'distance_km'));
  if ($totalKm >= 1000) {
    $badges[] = [
      'id' => 'eco_champion',
      'nom' => 'Éco Champion',
      'description' => 'Plus de 1000 km partagés',
      'icone' => '🌱',
      'couleur' => 'green'
    ];
  } elseif ($totalKm >= 500) {
    $badges[] = [
      'id' => 'eco_warrior',
      'nom' => 'Éco Warrior',
      'description' => 'Plus de 500 km partagés',
      'icone' => '♻️',
      'couleur' => 'green'
    ];
  }

  return $badges;
}

/**
 * Récupération des statistiques de réputation générales
 */
function getReputationStats()
{
  $users = DB::findAll('utilisateurs', ['statut' => 'actif']);
  $allAvis = DB::findAll('avis', ['valide' => true]);

  if (empty($allAvis)) {
    return [
      'utilisateurs_avec_avis' => 0,
      'note_moyenne_plateforme' => 0,
      'distribution_reputation' => [],
      'top_performers' => []
    ];
  }

  // Utilisateurs avec avis
  $usersWithAvis = [];
  foreach ($users as $user) {
    $userAvis = array_filter($allAvis, function ($a) use ($user) {
      return $a['evalue_id'] == $user['id'];
    });

    if (!empty($userAvis)) {
      $usersWithAvis[] = $user['id'];
    }
  }

  // Note moyenne de la plateforme
  $noteMovennePlateforme = round(array_sum(array_column($allAvis, 'note')) / count($allAvis), 2);

  // Distribution des niveaux de réputation
  $distributionReputation = [];
  foreach ($users as $user) {
    $niveau = $user['niveau_reputation'] ?? 'nouveau';
    $distributionReputation[$niveau] = ($distributionReputation[$niveau] ?? 0) + 1;
  }

  // Top performers (utilisateurs avec score de réputation le plus élevé)
  $topPerformers = getUserRankings('reputation', 10);

  return [
    'utilisateurs_avec_avis' => count($usersWithAvis),
    'note_moyenne_plateforme' => $noteMovennePlateforme,
    'distribution_reputation' => $distributionReputation,
    'top_performers' => $topPerformers,
    'total_avis_valides' => count($allAvis)
  ];
}

// Traitement de la requête
try {
  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'rankings';

      switch ($action) {
        case 'rankings':
          $type = $_GET['type'] ?? 'global';
          $limit = min(intval($_GET['limit'] ?? 50), 100);

          $rankings = getUserRankings($type, $limit);
          echo json_encode([
            'success' => true,
            'data' => $rankings,
            'type' => $type,
            'count' => count($rankings)
          ]);
          break;

        case 'user_badges':
          if (!isset($_GET['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $badges = getUserBadges($_GET['user_id']);
          echo json_encode([
            'success' => true,
            'data' => $badges,
            'count' => count($badges)
          ]);
          break;

        case 'user_score':
          if (!isset($_GET['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $score = calculateAdvancedReputationScore($_GET['user_id']);
          echo json_encode([
            'success' => true,
            'data' => [
              'score_reputation' => $score,
              'niveau' => $score >= 800 ? 'excellent' : ($score >= 600 ? 'tres_bon' : ($score >= 400 ? 'bon' : ($score >= 200 ? 'moyen' : 'faible')))
            ]
          ]);
          break;

        case 'stats':
          $stats = getReputationStats();
          echo json_encode([
            'success' => true,
            'data' => $stats
          ]);
          break;

        default:
          http_response_code(400);
          echo json_encode(['error' => 'Action non reconnue']);
      }
      break;

    default:
      http_response_code(405);
      echo json_encode(['error' => 'Méthode non autorisée']);
  }
} catch (Exception $e) {
  error_log("Erreur API Réputation: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
