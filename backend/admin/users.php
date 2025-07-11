<?php

/**
 * Jour 6 - APIs d'Administration
 * Gestion des utilisateurs (suspension, validation, modération)
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
 * Récupération de la liste des utilisateurs avec filtres
 */
function getUsers($filters = [])
{
  $db = Database::getInstance();
  $users = $db->read('utilisateurs');

  // Application des filtres
  if (!empty($filters['statut'])) {
    $users = array_filter($users, function ($user) use ($filters) {
      return $user['statut'] === $filters['statut'];
    });
  }

  if (!empty($filters['role'])) {
    $users = array_filter($users, function ($user) use ($filters) {
      return $user['role'] === $filters['role'];
    });
  }

  if (!empty($filters['search'])) {
    $search = strtolower($filters['search']);
    $users = array_filter($users, function ($user) use ($search) {
      return strpos(strtolower($user['nom']), $search) !== false ||
        strpos(strtolower($user['prenom']), $search) !== false ||
        strpos(strtolower($user['email']), $search) !== false;
    });
  }

  // Enrichissement des données utilisateur
  foreach ($users as &$user) {
    // Récupérer les statistiques utilisateur
    $trajets = $db->read('trajets', ['chauffeur_id' => $user['id']]);
    $participations = $db->read('participations', ['utilisateur_id' => $user['id']]);
    $avis = $db->read('avis', ['utilisateur_id' => $user['id']]);

    $user['stats'] = [
      'trajets_crees' => count($trajets),
      'trajets_participes' => count($participations),
      'avis_donnes' => count($avis),
      'derniere_connexion' => $user['derniere_connexion'] ?? 'Jamais'
    ];

    // Supprimer les informations sensibles
    unset($user['mot_de_passe']);
  }

  return array_values($users);
}

/**
 * Récupération des détails d'un utilisateur
 */
function getUserDetails($userId)
{
  $db = Database::getInstance();
  $user = $db->read('utilisateurs', ['id' => $userId]);

  if (empty($user)) {
    return null;
  }

  $user = $user[0];
  unset($user['mot_de_passe']);

  // Récupérer l'historique complet
  $trajets = $db->read('trajets', ['chauffeur_id' => $userId]);
  $participations = $db->read('participations', ['utilisateur_id' => $userId]);
  $avis = $db->read('avis', ['utilisateur_id' => $userId]);
  $transactions = $db->read('transactions', ['utilisateur_id' => $userId]);
  $vehicules = $db->read('vehicules', ['proprietaire_id' => $userId]);

  // Calculer les statistiques détaillées
  $trajetsTermines = array_filter($trajets, function ($t) {
    return $t['statut'] === 'termine';
  });

  $totalKm = array_sum(array_column($trajetsTermines, 'distance_km'));
  $totalEarnings = 0;
  $totalSpent = 0;

  foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'credit') {
      $totalEarnings += $transaction['montant'];
    } elseif ($transaction['type'] === 'debit') {
      $totalSpent += $transaction['montant'];
    }
  }

  $user['detailed_stats'] = [
    'trajets_crees' => count($trajets),
    'trajets_termines' => count($trajetsTermines),
    'trajets_participes' => count($participations),
    'total_km' => $totalKm,
    'total_earnings' => round($totalEarnings, 2),
    'total_spent' => round($totalSpent, 2),
    'avis_count' => count($avis),
    'vehicules_count' => count($vehicules),
    'co2_saved_kg' => round(($totalKm * 0.12) / 1000, 2)
  ];

  $user['recent_activity'] = [
    'trajets' => array_slice($trajets, -5),
    'participations' => array_slice($participations, -5),
    'transactions' => array_slice($transactions, -10)
  ];

  return $user;
}

/**
 * Suspension d'un utilisateur
 */
function suspendUser($userId, $reason, $duration = null)
{
  $db = Database::getInstance();

  $user = $db->read('utilisateurs', ['id' => $userId]);
  if (empty($user)) {
    return ['error' => 'Utilisateur non trouvé'];
  }

  $updateData = [
    'statut' => 'suspendu',
    'raison_suspension' => $reason,
    'date_suspension' => date('Y-m-d H:i:s')
  ];

  if ($duration) {
    $suspensionEnd = new DateTime();
    $suspensionEnd->add(new DateInterval("P{$duration}D"));
    $updateData['fin_suspension'] = $suspensionEnd->format('Y-m-d H:i:s');
  }

  $result = $db->update('utilisateurs', $updateData, ['id' => $userId]);

  if ($result) {
    // Annuler tous les trajets en attente de cet utilisateur
    $trajets = $db->read('trajets', [
      'chauffeur_id' => $userId,
      'statut' => 'en_attente'
    ]);

    foreach ($trajets as $trajet) {
      $db->update('trajets', [
        'statut' => 'annule',
        'raison_annulation' => 'Utilisateur suspendu'
      ], ['id' => $trajet['id']]);
    }

    // Créer une notification pour l'utilisateur
    $db->create('notifications', [
      'utilisateur_id' => $userId,
      'type' => 'suspension',
      'message' => "Votre compte a été suspendu. Raison : {$reason}",
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false
    ]);

    return ['success' => true, 'message' => 'Utilisateur suspendu avec succès'];
  }

  return ['error' => 'Erreur lors de la suspension'];
}

/**
 * Réactivation d'un utilisateur
 */
function reactivateUser($userId, $notes = '')
{
  $db = Database::getInstance();

  $result = $db->update('utilisateurs', [
    'statut' => 'actif',
    'raison_suspension' => null,
    'date_suspension' => null,
    'fin_suspension' => null,
    'notes_admin' => $notes
  ], ['id' => $userId]);

  if ($result) {
    // Créer une notification pour l'utilisateur
    $db->create('notifications', [
      'utilisateur_id' => $userId,
      'type' => 'reactivation',
      'message' => 'Votre compte a été réactivé. Vous pouvez à nouveau utiliser la plateforme.',
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false
    ]);

    return ['success' => true, 'message' => 'Utilisateur réactivé avec succès'];
  }

  return ['error' => 'Erreur lors de la réactivation'];
}

/**
 * Validation d'un utilisateur en attente
 */
function validateUser($userId)
{
  $db = Database::getInstance();

  $result = $db->update('utilisateurs', [
    'statut' => 'actif',
    'date_validation' => date('Y-m-d H:i:s')
  ], ['id' => $userId]);

  if ($result) {
    // Créer une notification de bienvenue
    $db->create('notifications', [
      'utilisateur_id' => $userId,
      'type' => 'validation',
      'message' => 'Félicitations ! Votre compte a été validé. Bienvenue sur ecoCovoit !',
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false
    ]);

    return ['success' => true, 'message' => 'Utilisateur validé avec succès'];
  }

  return ['error' => 'Erreur lors de la validation'];
}

/**
 * Suppression d'un utilisateur (avec sauvegarde des données)
 */
function deleteUser($userId, $reason)
{
  $db = Database::getInstance();

  $user = $db->read('utilisateurs', ['id' => $userId]);
  if (empty($user)) {
    return ['error' => 'Utilisateur non trouvé'];
  }

  // Vérifier qu'il n'y a pas de trajets actifs
  $activeTrajets = $db->read('trajets', [
    'chauffeur_id' => $userId,
    'statut' => ['en_attente', 'en_cours']
  ]);

  if (!empty($activeTrajets)) {
    return ['error' => 'Impossible de supprimer : trajets actifs en cours'];
  }

  // Anonymiser les données au lieu de supprimer complètement
  $result = $db->update('utilisateurs', [
    'nom' => 'SUPPRIMÉ',
    'prenom' => 'UTILISATEUR',
    'email' => "deleted_{$userId}@example.com",
    'telephone' => null,
    'statut' => 'supprime',
    'raison_suppression' => $reason,
    'date_suppression' => date('Y-m-d H:i:s'),
    'mot_de_passe' => null
  ], ['id' => $userId]);

  return $result ?
    ['success' => true, 'message' => 'Utilisateur supprimé avec succès'] :
    ['error' => 'Erreur lors de la suppression'];
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
            'role' => $_GET['role'] ?? '',
            'search' => $_GET['search'] ?? ''
          ];

          $users = getUsers($filters);
          echo json_encode([
            'success' => true,
            'data' => $users,
            'count' => count($users)
          ]);
          break;

        case 'details':
          if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $userDetails = getUserDetails($_GET['id']);
          if (!$userDetails) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur non trouvé']);
            exit;
          }

          echo json_encode([
            'success' => true,
            'data' => $userDetails
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
        case 'suspend':
          if (!isset($input['user_id']) || !isset($input['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = suspendUser(
            $input['user_id'],
            $input['reason'],
            $input['duration'] ?? null
          );
          echo json_encode($result);
          break;

        case 'reactivate':
          if (!isset($input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $result = reactivateUser($input['user_id'], $input['notes'] ?? '');
          echo json_encode($result);
          break;

        case 'validate':
          if (!isset($input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID utilisateur requis']);
            exit;
          }

          $result = validateUser($input['user_id']);
          echo json_encode($result);
          break;

        case 'delete':
          if (!isset($input['user_id']) || !isset($input['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = deleteUser($input['user_id'], $input['reason']);
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
  error_log("Erreur Gestion Utilisateurs Admin: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
