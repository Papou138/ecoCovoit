<?php

/**
 * Jour 7 - Système d'évaluations et avis
 * API de création et gestion des avis avec notation
 * Développé le 11 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/DB.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

/**
 * Vérification de l'authentification
 */
function checkAuthentication()
{
  if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit;
  }

  $user = DB::findById('utilisateurs', $_SESSION['user_id']);
  if (!$user || $user['statut'] !== 'actif') {
    http_response_code(403);
    echo json_encode(['error' => 'Compte inactif ou suspendu']);
    exit;
  }

  return $user;
}

/**
 * Création d'un nouvel avis
 */
function createAvis($userId, $trajetId, $evaluatedUserId, $type, $note, $commentaire = '')
{
  // Vérifications préliminaires
  if ($userId === $evaluatedUserId) {
    return ['error' => 'Impossible de s\'auto-évaluer'];
  }

  if ($note < 1 || $note > 5) {
    return ['error' => 'La note doit être comprise entre 1 et 5'];
  }

  // Vérifier que l'utilisateur a participé au trajet
  $trajet = DB::findById('trajets', $trajetId);
  if (!$trajet) {
    return ['error' => 'Trajet non trouvé'];
  }

  // Vérifier que le trajet est terminé
  if ($trajet['statut'] !== 'termine') {
    return ['error' => 'Le trajet doit être terminé pour laisser un avis'];
  }

  // Vérifier l'autorisation d'évaluer
  $canEvaluate = false;

  if ($type === 'chauffeur') {
    // Seuls les passagers peuvent évaluer le chauffeur
    $participations = DB::findAll('participations', [
      'trajet_id' => $trajetId,
      'utilisateur_id' => $userId,
      'statut' => 'confirmee'
    ]);
    $canEvaluate = !empty($participations) && $trajet['chauffeur_id'] == $evaluatedUserId;
  } elseif ($type === 'passager') {
    // Seul le chauffeur peut évaluer les passagers
    $participations = DB::findAll('participations', [
      'trajet_id' => $trajetId,
      'utilisateur_id' => $evaluatedUserId,
      'statut' => 'confirmee'
    ]);
    $canEvaluate = $trajet['chauffeur_id'] == $userId && !empty($participations);
  }

  if (!$canEvaluate) {
    return ['error' => 'Non autorisé à évaluer cet utilisateur pour ce trajet'];
  }

  // Vérifier qu'un avis n'existe pas déjà
  $existingAvis = DB::findAll('avis', [
    'evaluateur_id' => $userId,
    'evalue_id' => $evaluatedUserId,
    'trajet_id' => $trajetId
  ]);

  if (!empty($existingAvis)) {
    return ['error' => 'Vous avez déjà évalué cet utilisateur pour ce trajet'];
  }

  // Modération automatique du commentaire
  $moderationResult = moderateComment($commentaire);

  $avisData = [
    'evaluateur_id' => $userId,
    'evalue_id' => $evaluatedUserId,
    'trajet_id' => $trajetId,
    'type' => $type,
    'note' => $note,
    'commentaire' => $commentaire,
    'commentaire_modere' => $moderationResult['moderated_text'],
    'contient_mots_interdits' => $moderationResult['has_forbidden_words'],
    'valide' => !$moderationResult['needs_review'],
    'date_creation' => date('Y-m-d H:i:s'),
    'date_modification' => date('Y-m-d H:i:s')
  ];

  $avisId = DB::insert('avis', $avisData);

  if ($avisId) {
    // Mettre à jour les statistiques de l'utilisateur évalué
    updateUserRating($evaluatedUserId);

    // Créer une notification
    DB::insert('notifications', [
      'utilisateur_id' => $evaluatedUserId,
      'type' => 'nouvel_avis',
      'message' => "Vous avez reçu un nouvel avis ({$note}/5 étoiles)",
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false,
      'data' => json_encode([
        'avis_id' => $avisId,
        'note' => $note,
        'type' => $type
      ])
    ]);

    // Notification de modération si nécessaire
    if ($moderationResult['needs_review']) {
      $admins = DB::findAll('utilisateurs', ['role' => 'admin']);
      foreach ($admins as $admin) {
        DB::insert('notifications', [
          'utilisateur_id' => $admin['id'],
          'type' => 'moderation',
          'message' => "Nouvel avis nécessitant une modération",
          'date_creation' => date('Y-m-d H:i:s'),
          'lu' => false,
          'data' => json_encode(['avis_id' => $avisId])
        ]);
      }
    }

    return [
      'success' => true,
      'avis_id' => $avisId,
      'needs_moderation' => $moderationResult['needs_review']
    ];
  }

  return ['error' => 'Erreur lors de la création de l\'avis'];
}

/**
 * Modération automatique des commentaires
 */
function moderateComment($commentaire)
{
  if (empty($commentaire)) {
    return [
      'moderated_text' => '',
      'has_forbidden_words' => false,
      'needs_review' => false
    ];
  }

  // Liste des mots interdits (à étendre selon les besoins)
  $forbiddenWords = [
    'con',
    'connard',
    'salaud',
    'enculé',
    'putain',
    'merde',
    'crétin',
    'imbécile',
    'abruti',
    'débile',
    'nul',
    'pourri',
    'raciste',
    'sexiste',
    'homophobe',
    'nazi',
    'fasciste'
  ];

  $commentaireLower = strtolower($commentaire);
  $hasForbiddenWords = false;
  $moderatedText = $commentaire;

  foreach ($forbiddenWords as $word) {
    if (strpos($commentaireLower, $word) !== false) {
      $hasForbiddenWords = true;
      $moderatedText = str_ireplace($word, str_repeat('*', strlen($word)), $moderatedText);
    }
  }

  // Autres vérifications de modération
  $needsReview = false;

  // Commentaire trop long
  if (strlen($commentaire) > 500) {
    $needsReview = true;
  }

  // Répétition de caractères suspects
  if (preg_match('/(.)\1{4,}/', $commentaire)) {
    $needsReview = true;
  }

  // Majuscules excessives
  $upperCount = preg_match_all('/[A-Z]/', $commentaire);
  if ($upperCount > strlen($commentaire) / 2 && strlen($commentaire) > 10) {
    $needsReview = true;
  }

  return [
    'moderated_text' => $moderatedText,
    'has_forbidden_words' => $hasForbiddenWords,
    'needs_review' => $needsReview || $hasForbiddenWords
  ];
}

/**
 * Mise à jour des statistiques de notation d'un utilisateur
 */
function updateUserRating($userId)
{
  $avisPourUtilisateur = DB::findAll('avis', [
    'evalue_id' => $userId,
    'valide' => true
  ]);

  if (empty($avisPourUtilisateur)) {
    return;
  }

  // Calculs statistiques
  $totalNotes = array_sum(array_column($avisPourUtilisateur, 'note'));
  $nombreAvis = count($avisPourUtilisateur);
  $moyenneGlobale = round($totalNotes / $nombreAvis, 2);

  // Statistiques par type
  $avisChauffeur = array_filter($avisPourUtilisateur, function ($a) {
    return $a['type'] === 'chauffeur';
  });
  $avisPassager = array_filter($avisPourUtilisateur, function ($a) {
    return $a['type'] === 'passager';
  });

  $moyenneChauffeur = !empty($avisChauffeur) ?
    round(array_sum(array_column($avisChauffeur, 'note')) / count($avisChauffeur), 2) : null;

  $moyennePassager = !empty($avisPassager) ?
    round(array_sum(array_column($avisPassager, 'note')) / count($avisPassager), 2) : null;

  // Distribution des notes
  $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
  foreach ($avisPourUtilisateur as $avis) {
    $distribution[$avis['note']]++;
  }

  // Calcul du niveau de réputation
  $niveauReputation = calculateReputationLevel($moyenneGlobale, $nombreAvis);

  // Mise à jour de l'utilisateur
  $user = DB::findById('utilisateurs', $userId);
  if ($user) {
    $user['note_moyenne'] = $moyenneGlobale;
    $user['nombre_avis'] = $nombreAvis;
    $user['note_chauffeur'] = $moyenneChauffeur;
    $user['note_passager'] = $moyennePassager;
    $user['distribution_notes'] = $distribution;
    $user['niveau_reputation'] = $niveauReputation;
    $user['derniere_mise_a_jour_avis'] = date('Y-m-d H:i:s');

    DB::update('utilisateurs', $userId, $user);
  }
}

/**
 * Calcul du niveau de réputation
 */
function calculateReputationLevel($moyenne, $nombreAvis)
{
  if ($nombreAvis < 3) {
    return 'nouveau';
  }

  if ($moyenne >= 4.5 && $nombreAvis >= 10) {
    return 'excellent';
  } elseif ($moyenne >= 4.0 && $nombreAvis >= 5) {
    return 'tres_bon';
  } elseif ($moyenne >= 3.5) {
    return 'bon';
  } elseif ($moyenne >= 3.0) {
    return 'moyen';
  } elseif ($moyenne >= 2.0) {
    return 'faible';
  } else {
    return 'problematique';
  }
}

/**
 * Récupération des avis avec filtres
 */
function getAvis($filters = [])
{
  $avis = DB::findAll('avis');

  // Application des filtres
  if (!empty($filters['evalue_id'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['evalue_id'] == $filters['evalue_id'];
    });
  }

  if (!empty($filters['evaluateur_id'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['evaluateur_id'] == $filters['evaluateur_id'];
    });
  }

  if (!empty($filters['trajet_id'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['trajet_id'] == $filters['trajet_id'];
    });
  }

  if (!empty($filters['type'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['type'] === $filters['type'];
    });
  }

  if (isset($filters['valide'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['valide'] == $filters['valide'];
    });
  }

  if (!empty($filters['note_min'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['note'] >= $filters['note_min'];
    });
  }

  if (!empty($filters['note_max'])) {
    $avis = array_filter($avis, function ($a) use ($filters) {
      return $a['note'] <= $filters['note_max'];
    });
  }

  // Enrichissement avec informations des utilisateurs
  $users = DB::findAll('utilisateurs');
  $usersById = [];
  foreach ($users as $user) {
    $usersById[$user['id']] = $user;
  }

  foreach ($avis as &$av) {
    // Informations de l'évaluateur
    $evaluateur = $usersById[$av['evaluateur_id']] ?? null;
    $av['evaluateur'] = $evaluateur ? [
      'id' => $evaluateur['id'],
      'nom' => $evaluateur['nom'],
      'prenom' => $evaluateur['prenom'],
      'note_moyenne' => $evaluateur['note_moyenne'] ?? null
    ] : null;

    // Informations de l'évalué
    $evalue = $usersById[$av['evalue_id']] ?? null;
    $av['evalue'] = $evalue ? [
      'id' => $evalue['id'],
      'nom' => $evalue['nom'],
      'prenom' => $evalue['prenom'],
      'note_moyenne' => $evalue['note_moyenne'] ?? null,
      'niveau_reputation' => $evalue['niveau_reputation'] ?? 'nouveau'
    ] : null;

    // Informations du trajet
    $trajet = DB::findById('trajets', $av['trajet_id']);
    $av['trajet'] = $trajet ? [
      'id' => $trajet['id'],
      'depart' => $trajet['depart'],
      'arrivee' => $trajet['arrivee'],
      'date_trajet' => $trajet['date_trajet']
    ] : null;
  }

  // Tri par date (plus récent en premier)
  usort($avis, function ($a, $b) {
    return strtotime($b['date_creation']) - strtotime($a['date_creation']);
  });

  return array_values($avis);
}

/**
 * Récupération des statistiques d'avis
 */
function getAvisStats($userId = null)
{
  $avis = $userId ?
    DB::findAll('avis', ['evalue_id' => $userId, 'valide' => true]) :
    DB::findAll('avis', ['valide' => true]);

  if (empty($avis)) {
    return [
      'total_avis' => 0,
      'note_moyenne' => 0,
      'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
      'par_type' => ['chauffeur' => [], 'passager' => []],
      'evolution_mensuelle' => []
    ];
  }

  $totalNotes = array_sum(array_column($avis, 'note'));
  $nombreAvis = count($avis);
  $moyenneGlobale = round($totalNotes / $nombreAvis, 2);

  // Distribution des notes
  $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
  foreach ($avis as $av) {
    $distribution[$av['note']]++;
  }

  // Statistiques par type
  $avisChauffeur = array_filter($avis, function ($a) {
    return $a['type'] === 'chauffeur';
  });
  $avisPassager = array_filter($avis, function ($a) {
    return $a['type'] === 'passager';
  });

  $statsChauffeur = !empty($avisChauffeur) ? [
    'count' => count($avisChauffeur),
    'moyenne' => round(array_sum(array_column($avisChauffeur, 'note')) / count($avisChauffeur), 2)
  ] : ['count' => 0, 'moyenne' => 0];

  $statsPassager = !empty($avisPassager) ? [
    'count' => count($avisPassager),
    'moyenne' => round(array_sum(array_column($avisPassager, 'note')) / count($avisPassager), 2)
  ] : ['count' => 0, 'moyenne' => 0];

  // Évolution mensuelle (6 derniers mois)
  $evolutionMensuelle = [];
  for ($i = 5; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-{$i} months"));
    $avisMonth = array_filter($avis, function ($a) use ($mois) {
      return strpos($a['date_creation'], $mois) === 0;
    });

    $evolutionMensuelle[] = [
      'mois' => $mois,
      'count' => count($avisMonth),
      'moyenne' => !empty($avisMonth) ?
        round(array_sum(array_column($avisMonth, 'note')) / count($avisMonth), 2) : 0
    ];
  }

  return [
    'total_avis' => $nombreAvis,
    'note_moyenne' => $moyenneGlobale,
    'distribution' => $distribution,
    'par_type' => [
      'chauffeur' => $statsChauffeur,
      'passager' => $statsPassager
    ],
    'evolution_mensuelle' => $evolutionMensuelle
  ];
}

// Traitement de la requête
try {
  $user = checkAuthentication();

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'list';

      switch ($action) {
        case 'list':
          $filters = [
            'evalue_id' => $_GET['evalue_id'] ?? '',
            'evaluateur_id' => $_GET['evaluateur_id'] ?? '',
            'trajet_id' => $_GET['trajet_id'] ?? '',
            'type' => $_GET['type'] ?? '',
            'valide' => isset($_GET['valide']) ? (bool)$_GET['valide'] : null,
            'note_min' => $_GET['note_min'] ?? '',
            'note_max' => $_GET['note_max'] ?? ''
          ];

          $avis = getAvis($filters);
          echo json_encode([
            'success' => true,
            'data' => $avis,
            'count' => count($avis)
          ]);
          break;

        case 'stats':
          $userId = $_GET['user_id'] ?? null;
          $stats = getAvisStats($userId);
          echo json_encode([
            'success' => true,
            'data' => $stats
          ]);
          break;

        case 'user_profile':
          if (!isset($_GET['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $profileUser = DB::findById('utilisateurs', $_GET['user_id']);
          if (!$profileUser) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur non trouvé']);
            exit;
          }

          $userAvis = getAvis(['evalue_id' => $_GET['user_id'], 'valide' => true]);
          $userStats = getAvisStats($_GET['user_id']);

          echo json_encode([
            'success' => true,
            'data' => [
              'user' => [
                'id' => $profileUser['id'],
                'nom' => $profileUser['nom'],
                'prenom' => $profileUser['prenom'],
                'note_moyenne' => $profileUser['note_moyenne'] ?? 0,
                'nombre_avis' => $profileUser['nombre_avis'] ?? 0,
                'note_chauffeur' => $profileUser['note_chauffeur'] ?? null,
                'note_passager' => $profileUser['note_passager'] ?? null,
                'niveau_reputation' => $profileUser['niveau_reputation'] ?? 'nouveau',
                'distribution_notes' => $profileUser['distribution_notes'] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
              ],
              'avis' => $userAvis,
              'stats' => $userStats
            ]
          ]);
          break;

        default:
          http_response_code(400);
          echo json_encode(['error' => 'Action non reconnue']);
      }
      break;

    case 'POST':
      $input = json_decode(file_get_contents('php://input'), true);

      if (
        !isset($input['trajet_id']) || !isset($input['evalue_id']) ||
        !isset($input['type']) || !isset($input['note'])
      ) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        exit;
      }

      $result = createAvis(
        $user['id'],
        $input['trajet_id'],
        $input['evalue_id'],
        $input['type'],
        $input['note'],
        $input['commentaire'] ?? ''
      );

      if (isset($result['error'])) {
        http_response_code(400);
        echo json_encode($result);
      } else {
        echo json_encode($result);
      }
      break;

    default:
      http_response_code(405);
      echo json_encode(['error' => 'Méthode non autorisée']);
  }
} catch (Exception $e) {
  error_log("Erreur API Avis: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
