<?php

/**
 * Jour 6 - APIs d'Administration
 * Modération des trajets et contenus
 * Développé le 11 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/Database.php';

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

  $db = Database::getInstance();
  $user = $db->read('utilisateurs', ['id' => $_SESSION['user_id']]);

  if (empty($user) || $user[0]['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès administrateur requis']);
    exit;
  }

  return $user[0];
}

/**
 * Récupération des trajets avec filtres de modération
 */
function getTrajetsForModeration($filters = [])
{
  $db = Database::getInstance();
  $trajets = $db->read('trajets');

  // Application des filtres
  if (!empty($filters['statut'])) {
    $trajets = array_filter($trajets, function ($trajet) use ($filters) {
      return $trajet['statut'] === $filters['statut'];
    });
  }

  if (!empty($filters['signale'])) {
    $trajets = array_filter($trajets, function ($trajet) use ($filters) {
      return isset($trajet['signale']) && $trajet['signale'] == true;
    });
  }

  if (!empty($filters['problematique'])) {
    $trajets = array_filter($trajets, function ($trajet) use ($filters) {
      return isset($trajet['problematique']) && $trajet['problematique'] == true;
    });
  }

  if (!empty($filters['search'])) {
    $search = strtolower($filters['search']);
    $trajets = array_filter($trajets, function ($trajet) use ($search) {
      return strpos(strtolower($trajet['depart']), $search) !== false ||
        strpos(strtolower($trajet['arrivee']), $search) !== false ||
        strpos(strtolower($trajet['description'] ?? ''), $search) !== false;
    });
  }

  // Enrichissement avec informations du chauffeur et participants
  $users = $db->read('utilisateurs');
  $participations = $db->read('participations');

  $usersById = [];
  foreach ($users as $user) {
    $usersById[$user['id']] = $user;
  }

  foreach ($trajets as &$trajet) {
    // Informations du chauffeur
    $chauffeur = $usersById[$trajet['chauffeur_id']] ?? null;
    $trajet['chauffeur'] = $chauffeur ? [
      'id' => $chauffeur['id'],
      'nom' => $chauffeur['nom'],
      'prenom' => $chauffeur['prenom'],
      'email' => $chauffeur['email'],
      'statut' => $chauffeur['statut']
    ] : null;

    // Participants
    $trajetParticipations = array_filter($participations, function ($p) use ($trajet) {
      return $p['trajet_id'] === $trajet['id'];
    });

    $trajet['participants'] = [];
    foreach ($trajetParticipations as $participation) {
      $participant = $usersById[$participation['utilisateur_id']] ?? null;
      if ($participant) {
        $trajet['participants'][] = [
          'id' => $participant['id'],
          'nom' => $participant['nom'],
          'prenom' => $participant['prenom'],
          'statut' => $participation['statut']
        ];
      }
    }

    $trajet['nb_participants'] = count($trajet['participants']);

    // Signalements et problèmes
    $trajet['signalements'] = $trajet['signalements'] ?? [];
    $trajet['actions_moderateur'] = $trajet['actions_moderateur'] ?? [];
  }

  return array_values($trajets);
}

/**
 * Signalement d'un trajet problématique
 */
function reportTrajet($trajetId, $reason, $description, $reporterId)
{
  $db = Database::getInstance();

  $trajet = $db->read('trajets', ['id' => $trajetId]);
  if (empty($trajet)) {
    return ['error' => 'Trajet non trouvé'];
  }

  $trajet = $trajet[0];
  $signalements = $trajet['signalements'] ?? [];

  // Ajouter le nouveau signalement
  $signalements[] = [
    'reporter_id' => $reporterId,
    'reason' => $reason,
    'description' => $description,
    'date' => date('Y-m-d H:i:s'),
    'traite' => false
  ];

  $updateData = [
    'signalements' => $signalements,
    'signale' => true,
    'nb_signalements' => count($signalements)
  ];

  // Si plus de 3 signalements, marquer comme problématique
  if (count($signalements) >= 3) {
    $updateData['problematique'] = true;
  }

  $result = $db->update('trajets', $updateData, ['id' => $trajetId]);

  if ($result) {
    // Notifier les administrateurs
    $admins = $db->read('utilisateurs', ['role' => 'admin']);
    foreach ($admins as $admin) {
      $db->create('notifications', [
        'utilisateur_id' => $admin['id'],
        'type' => 'moderation',
        'message' => "Nouveau signalement pour le trajet {$trajet['depart']} → {$trajet['arrivee']}",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false,
        'data' => json_encode(['trajet_id' => $trajetId])
      ]);
    }

    return ['success' => true, 'message' => 'Signalement enregistré'];
  }

  return ['error' => 'Erreur lors du signalement'];
}

/**
 * Action de modération sur un trajet
 */
function moderateTrajet($trajetId, $action, $reason, $moderatorId)
{
  $db = Database::getInstance();

  $trajet = $db->read('trajets', ['id' => $trajetId]);
  if (empty($trajet)) {
    return ['error' => 'Trajet non trouvé'];
  }

  $trajet = $trajet[0];
  $actions = $trajet['actions_moderateur'] ?? [];

  // Enregistrer l'action de modération
  $actions[] = [
    'moderator_id' => $moderatorId,
    'action' => $action,
    'reason' => $reason,
    'date' => date('Y-m-d H:i:s')
  ];

  $updateData = [
    'actions_moderateur' => $actions
  ];

  switch ($action) {
    case 'approve':
      $updateData['signale'] = false;
      $updateData['problematique'] = false;
      $updateData['moderation_statut'] = 'approuve';

      // Marquer tous les signalements comme traités
      $signalements = $trajet['signalements'] ?? [];
      foreach ($signalements as &$signalement) {
        $signalement['traite'] = true;
      }
      $updateData['signalements'] = $signalements;
      break;

    case 'suspend':
      $updateData['statut'] = 'suspendu';
      $updateData['moderation_statut'] = 'suspendu';
      $updateData['raison_suspension'] = $reason;

      // Notifier le chauffeur
      $db->create('notifications', [
        'utilisateur_id' => $trajet['chauffeur_id'],
        'type' => 'moderation',
        'message' => "Votre trajet a été suspendu par la modération. Raison : {$reason}",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false
      ]);
      break;

    case 'cancel':
      $updateData['statut'] = 'annule';
      $updateData['moderation_statut'] = 'annule';
      $updateData['raison_annulation'] = $reason;

      // Rembourser tous les participants
      $participations = $db->read('participations', ['trajet_id' => $trajetId]);
      foreach ($participations as $participation) {
        if ($participation['statut'] === 'confirmee') {
          // Créer transaction de remboursement
          $db->create('transactions', [
            'utilisateur_id' => $participation['utilisateur_id'],
            'type' => 'remboursement',
            'montant' => $participation['montant_paye'],
            'description' => "Remboursement trajet annulé par modération",
            'date_creation' => date('Y-m-d H:i:s')
          ]);

          // Notifier le participant
          $db->create('notifications', [
            'utilisateur_id' => $participation['utilisateur_id'],
            'type' => 'remboursement',
            'message' => "Trajet annulé par la modération. Vous avez été remboursé.",
            'date_creation' => date('Y-m-d H:i:s'),
            'lu' => false
          ]);
        }
      }

      // Notifier le chauffeur
      $db->create('notifications', [
        'utilisateur_id' => $trajet['chauffeur_id'],
        'type' => 'moderation',
        'message' => "Votre trajet a été annulé par la modération. Raison : {$reason}",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false
      ]);
      break;

    case 'warn':
      $updateData['moderation_statut'] = 'avertissement';

      // Notifier le chauffeur avec avertissement
      $db->create('notifications', [
        'utilisateur_id' => $trajet['chauffeur_id'],
        'type' => 'avertissement',
        'message' => "Avertissement concernant votre trajet. Raison : {$reason}",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false
      ]);
      break;
  }

  $result = $db->update('trajets', $updateData, ['id' => $trajetId]);

  return $result ?
    ['success' => true, 'message' => 'Action de modération appliquée'] :
    ['error' => 'Erreur lors de la modération'];
}

/**
 * Récupération des statistiques de modération
 */
function getModerationStats()
{
  $db = Database::getInstance();

  $trajets = $db->read('trajets');
  $signales = array_filter($trajets, function ($t) {
    return isset($t['signale']) && $t['signale'];
  });
  $problematiques = array_filter($trajets, function ($t) {
    return isset($t['problematique']) && $t['problematique'];
  });
  $suspendus = array_filter($trajets, function ($t) {
    return $t['statut'] === 'suspendu';
  });

  // Statistiques des signalements par raison
  $signalementStats = [];
  foreach ($signales as $trajet) {
    $signalements = $trajet['signalements'] ?? [];
    foreach ($signalements as $signalement) {
      $reason = $signalement['reason'] ?? 'Autre';
      $signalementStats[$reason] = ($signalementStats[$reason] ?? 0) + 1;
    }
  }

  // Actions de modération récentes
  $recentActions = [];
  foreach ($trajets as $trajet) {
    $actions = $trajet['actions_moderateur'] ?? [];
    foreach ($actions as $action) {
      $action['trajet_id'] = $trajet['id'];
      $action['trajet_info'] = $trajet['depart'] . ' → ' . $trajet['arrivee'];
      $recentActions[] = $action;
    }
  }

  // Trier par date (plus récent en premier)
  usort($recentActions, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
  });

  return [
    'trajets_signales' => count($signales),
    'trajets_problematiques' => count($problematiques),
    'trajets_suspendus' => count($suspendus),
    'signalements_par_raison' => $signalementStats,
    'actions_recentes' => array_slice($recentActions, 0, 10),
    'total_trajets' => count($trajets)
  ];
}

// Traitement de la requête
try {
  $admin = checkAdminPermissions();

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'list';

      switch ($action) {
        case 'list':
          $filters = [
            'statut' => $_GET['statut'] ?? '',
            'signale' => $_GET['signale'] ?? '',
            'problematique' => $_GET['problematique'] ?? '',
            'search' => $_GET['search'] ?? ''
          ];

          $trajets = getTrajetsForModeration($filters);
          echo json_encode([
            'success' => true,
            'data' => $trajets,
            'count' => count($trajets)
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
        case 'report':
          if (!isset($input['trajet_id']) || !isset($input['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = reportTrajet(
            $input['trajet_id'],
            $input['reason'],
            $input['description'] ?? '',
            $admin['id']
          );
          echo json_encode($result);
          break;

        case 'moderate':
          if (!isset($input['trajet_id']) || !isset($input['moderation_action']) || !isset($input['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = moderateTrajet(
            $input['trajet_id'],
            $input['moderation_action'],
            $input['reason'],
            $admin['id']
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
  error_log("Erreur Modération Trajets: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
