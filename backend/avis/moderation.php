<?php

/**
 * Jour 7 - Système d'évaluations et avis
 * API de modération des avis pour les administrateurs
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
 * Vérification des permissions administrateur
 */
function checkAdminPermissions()
{
  if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
  }

  $user = DB::findById('utilisateurs', $_SESSION['user_id']);
  if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès administrateur requis']);
    exit;
  }

  return $user;
}

/**
 * Récupération des avis en attente de modération
 */
function getAvisToModerate()
{
  $avis = DB::findAll('avis', ['valide' => false]);

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
      'email' => $evaluateur['email'],
      'nombre_avis_donnes' => count(DB::findAll('avis', ['evaluateur_id' => $evaluateur['id']]))
    ] : null;

    // Informations de l'évalué
    $evalue = $usersById[$av['evalue_id']] ?? null;
    $av['evalue'] = $evalue ? [
      'id' => $evalue['id'],
      'nom' => $evalue['nom'],
      'prenom' => $evalue['prenom'],
      'email' => $evalue['email'],
      'note_moyenne' => $evalue['note_moyenne'] ?? null
    ] : null;

    // Informations du trajet
    $trajet = DB::findById('trajets', $av['trajet_id']);
    $av['trajet'] = $trajet ? [
      'id' => $trajet['id'],
      'depart' => $trajet['depart'],
      'arrivee' => $trajet['arrivee'],
      'date_trajet' => $trajet['date_trajet'],
      'prix' => $trajet['prix']
    ] : null;

    // Analyses de modération
    $av['moderation_flags'] = [
      'has_forbidden_words' => $av['contient_mots_interdits'] ?? false,
      'length_excessive' => strlen($av['commentaire']) > 500,
      'caps_excessive' => (preg_match_all('/[A-Z]/', $av['commentaire']) > strlen($av['commentaire']) / 2),
      'repetitive_chars' => preg_match('/(.)\1{4,}/', $av['commentaire'])
    ];

    // Score de priorité pour la modération
    $priorityScore = 0;
    if ($av['moderation_flags']['has_forbidden_words']) $priorityScore += 3;
    if ($av['note'] <= 2) $priorityScore += 2;
    if ($av['moderation_flags']['length_excessive']) $priorityScore += 1;
    if ($av['moderation_flags']['caps_excessive']) $priorityScore += 1;
    if ($av['moderation_flags']['repetitive_chars']) $priorityScore += 1;

    $av['priority_score'] = $priorityScore;
    $av['priority_level'] = $priorityScore >= 4 ? 'haute' : ($priorityScore >= 2 ? 'moyenne' : 'basse');
  }

  // Tri par priorité puis par date
  usort($avis, function ($a, $b) {
    if ($a['priority_score'] === $b['priority_score']) {
      return strtotime($a['date_creation']) - strtotime($b['date_creation']);
    }
    return $b['priority_score'] - $a['priority_score'];
  });

  return array_values($avis);
}

/**
 * Validation d'un avis
 */
function validateAvis($avisId, $moderatorId, $action, $reason = '')
{
  $avis = DB::findById('avis', $avisId);
  if (!$avis) {
    return ['error' => 'Avis non trouvé'];
  }

  $actions = $avis['actions_moderation'] ?? [];
  $now = date('Y-m-d H:i:s');

  switch ($action) {
    case 'approve':
      $avis['valide'] = true;
      $avis['date_validation'] = $now;
      $avis['moderateur_id'] = $moderatorId;

      $actions[] = [
        'action' => 'approved',
        'moderator_id' => $moderatorId,
        'reason' => $reason,
        'date' => $now
      ];

      // Mettre à jour les statistiques de l'utilisateur
      updateUserRatingAfterValidation($avis['evalue_id']);

      // Notifier l'utilisateur évalué
      DB::insert('notifications', [
        'utilisateur_id' => $avis['evalue_id'],
        'type' => 'avis_valide',
        'message' => "Un nouvel avis vous concernant a été validé ({$avis['note']}/5 étoiles)",
        'date_creation' => $now,
        'lu' => false,
        'data' => json_encode(['avis_id' => $avisId])
      ]);

      break;

    case 'reject':
      $avis['valide'] = false;
      $avis['rejete'] = true;
      $avis['raison_rejet'] = $reason;
      $avis['date_rejet'] = $now;
      $avis['moderateur_id'] = $moderatorId;

      $actions[] = [
        'action' => 'rejected',
        'moderator_id' => $moderatorId,
        'reason' => $reason,
        'date' => $now
      ];

      // Notifier l'évaluateur
      DB::insert('notifications', [
        'utilisateur_id' => $avis['evaluateur_id'],
        'type' => 'avis_rejete',
        'message' => "Votre avis a été rejeté par la modération. Raison : {$reason}",
        'date_creation' => $now,
        'lu' => false,
        'data' => json_encode(['avis_id' => $avisId])
      ]);

      break;

    case 'edit':
      // Edition du commentaire par le modérateur
      if (isset($reason) && !empty($reason)) {
        $avis['commentaire'] = $reason; // Le nouveau commentaire est passé dans 'reason'
        $avis['commentaire_modere'] = $reason;
        $avis['modifie_par_moderateur'] = true;
      }

      $avis['valide'] = true;
      $avis['date_validation'] = $now;
      $avis['moderateur_id'] = $moderatorId;

      $actions[] = [
        'action' => 'edited_and_approved',
        'moderator_id' => $moderatorId,
        'reason' => 'Commentaire modifié par la modération',
        'date' => $now
      ];

      // Mettre à jour les statistiques
      updateUserRatingAfterValidation($avis['evalue_id']);

      // Notifier l'utilisateur évalué
      DB::insert('notifications', [
        'utilisateur_id' => $avis['evalue_id'],
        'type' => 'avis_valide',
        'message' => "Un nouvel avis vous concernant a été validé après modération ({$avis['note']}/5 étoiles)",
        'date_creation' => $now,
        'lu' => false,
        'data' => json_encode(['avis_id' => $avisId])
      ]);

      break;

    default:
      return ['error' => 'Action de modération non reconnue'];
  }

  $avis['actions_moderation'] = $actions;
  $avis['date_modification'] = $now;

  $result = DB::update('avis', $avisId, $avis);

  return $result ?
    ['success' => true, 'message' => 'Action de modération appliquée'] :
    ['error' => 'Erreur lors de la modération'];
}

/**
 * Mise à jour des statistiques utilisateur après validation d'avis
 */
function updateUserRatingAfterValidation($userId)
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
 * Suppression d'un avis (cas extrême)
 */
function deleteAvis($avisId, $moderatorId, $reason)
{
  $avis = DB::findById('avis', $avisId);
  if (!$avis) {
    return ['error' => 'Avis non trouvé'];
  }

  // Enregistrer la suppression dans l'historique
  $historyData = [
    'avis_original' => $avis,
    'moderator_id' => $moderatorId,
    'reason' => $reason,
    'date_suppression' => date('Y-m-d H:i:s')
  ];

  DB::insert('avis_supprimes', $historyData);

  // Supprimer l'avis
  $result = DB::delete('avis', $avisId);

  if ($result) {
    // Recalculer les statistiques de l'utilisateur
    updateUserRatingAfterValidation($avis['evalue_id']);

    // Notifier l'évaluateur
    DB::insert('notifications', [
      'utilisateur_id' => $avis['evaluateur_id'],
      'type' => 'avis_supprime',
      'message' => "Votre avis a été supprimé par la modération. Raison : {$reason}",
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false
    ]);

    return ['success' => true, 'message' => 'Avis supprimé avec succès'];
  }

  return ['error' => 'Erreur lors de la suppression'];
}

/**
 * Statistiques de modération
 */
function getModerationStats()
{
  $allAvis = DB::findAll('avis');
  $avisValides = array_filter($allAvis, function ($a) {
    return $a['valide'] ?? false;
  });
  $avisRejetes = array_filter($allAvis, function ($a) {
    return isset($a['rejete']) && $a['rejete'];
  });
  $avisEnAttente = array_filter($allAvis, function ($a) {
    return !($a['valide'] ?? false) && !($a['rejete'] ?? false);
  });

  // Avis avec mots interdits
  $avisAvecMotsInterdits = array_filter($allAvis, function ($a) {
    return $a['contient_mots_interdits'] ?? false;
  });

  // Avis récents (derniers 30 jours)
  $date30JoursAgo = date('Y-m-d', strtotime('-30 days'));
  $avisRecents = array_filter($allAvis, function ($a) use ($date30JoursAgo) {
    return $a['date_creation'] >= $date30JoursAgo;
  });

  // Distribution des notes
  $distributionGlobale = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
  foreach ($avisValides as $avis) {
    $distributionGlobale[$avis['note']]++;
  }

  // Temps de modération moyen
  $tempsModeration = [];
  foreach ($avisValides as $avis) {
    if (isset($avis['date_validation'])) {
      $created = new DateTime($avis['date_creation']);
      $validated = new DateTime($avis['date_validation']);
      $diff = $validated->diff($created);
      $heures = $diff->days * 24 + $diff->h + ($diff->i / 60);
      $tempsModeration[] = $heures;
    }
  }

  $tempsMoyenModeration = !empty($tempsModeration) ?
    round(array_sum($tempsModeration) / count($tempsModeration), 1) : 0;

  return [
    'total_avis' => count($allAvis),
    'avis_valides' => count($avisValides),
    'avis_rejetes' => count($avisRejetes),
    'avis_en_attente' => count($avisEnAttente),
    'avis_avec_mots_interdits' => count($avisAvecMotsInterdits),
    'avis_recents_30j' => count($avisRecents),
    'distribution_notes' => $distributionGlobale,
    'temps_moyen_moderation_h' => $tempsMoyenModeration,
    'taux_validation' => count($allAvis) > 0 ?
      round((count($avisValides) / count($allAvis)) * 100, 1) : 0
  ];
}

// Traitement de la requête
try {
  $admin = checkAdminPermissions();

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'pending';

      switch ($action) {
        case 'pending':
          $avis = getAvisToModerate();
          echo json_encode([
            'success' => true,
            'data' => $avis,
            'count' => count($avis)
          ]);
          break;

        case 'stats':
          $stats = getModerationStats();
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

    case 'POST':
      $input = json_decode(file_get_contents('php://input'), true);
      $action = $input['action'] ?? '';

      switch ($action) {
        case 'validate':
          if (!isset($input['avis_id']) || !isset($input['decision'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = validateAvis(
            $input['avis_id'],
            $admin['id'],
            $input['decision'],
            $input['reason'] ?? ''
          );
          echo json_encode($result);
          break;

        case 'delete':
          if (!isset($input['avis_id']) || !isset($input['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = deleteAvis(
            $input['avis_id'],
            $admin['id'],
            $input['reason']
          );
          echo json_encode($result);
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
  error_log("Erreur Modération Avis: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
