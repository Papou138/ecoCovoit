<?php

/**
 * Jour 6 - APIs d'Administration
 * Panel d'administration principal avec statistiques et navigation
 * Développé le 11 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
 * Récupération des statistiques générales de la plateforme
 */
function getPlatformStats()
{
  $db = Database::getInstance();

  // Statistiques utilisateurs
  $users = $db->read('utilisateurs');
  $activeUsers = array_filter($users, function ($user) {
    return $user['statut'] === 'actif';
  });
  $suspendedUsers = array_filter($users, function ($user) {
    return $user['statut'] === 'suspendu';
  });
  $pendingUsers = array_filter($users, function ($user) {
    return $user['statut'] === 'en_attente';
  });

  // Statistiques trajets
  $trajets = $db->read('trajets');
  $activeTrajets = array_filter($trajets, function ($trajet) {
    return in_array($trajet['statut'], ['en_attente', 'en_cours']);
  });
  $completedTrajets = array_filter($trajets, function ($trajet) {
    return $trajet['statut'] === 'termine';
  });
  $cancelledTrajets = array_filter($trajets, function ($trajet) {
    return $trajet['statut'] === 'annule';
  });

  // Statistiques financières
  $transactions = $db->read('transactions');
  $totalRevenue = 0;
  $totalCommissions = 0;

  foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'commission') {
      $totalCommissions += $transaction['montant'];
      $totalRevenue += $transaction['montant'];
    }
  }

  // Statistiques environnementales
  $totalKm = 0;
  $totalCO2Saved = 0;

  foreach ($completedTrajets as $trajet) {
    $totalKm += $trajet['distance_km'];
    $totalCO2Saved += ($trajet['distance_km'] * 0.12); // 120g CO2/km économisé
  }

  // Notifications et signalements
  $notifications = $db->read('notifications');
  $unreadNotifications = array_filter($notifications, function ($notif) {
    return !$notif['lu'];
  });

  // Avis et évaluations
  $avis = $db->read('avis');
  $validatedAvis = array_filter($avis, function ($av) {
    return $av['valide'];
  });
  $pendingAvis = array_filter($avis, function ($av) {
    return !$av['valide'];
  });

  return [
    'users' => [
      'total' => count($users),
      'active' => count($activeUsers),
      'suspended' => count($suspendedUsers),
      'pending' => count($pendingUsers)
    ],
    'trajets' => [
      'total' => count($trajets),
      'active' => count($activeTrajets),
      'completed' => count($completedTrajets),
      'cancelled' => count($cancelledTrajets)
    ],
    'financial' => [
      'total_revenue' => round($totalRevenue, 2),
      'total_commissions' => round($totalCommissions, 2),
      'transactions_count' => count($transactions)
    ],
    'environmental' => [
      'total_km' => round($totalKm, 2),
      'co2_saved_kg' => round($totalCO2Saved / 1000, 2)
    ],
    'moderation' => [
      'unread_notifications' => count($unreadNotifications),
      'pending_avis' => count($pendingAvis),
      'validated_avis' => count($validatedAvis)
    ]
  ];
}

/**
 * Récupération des activités récentes pour le dashboard
 */
function getRecentActivities($limit = 10)
{
  $db = Database::getInstance();

  $activities = [];

  // Nouveaux utilisateurs (derniers 7 jours)
  $users = $db->read('utilisateurs');
  foreach ($users as $user) {
    $createdDate = new DateTime($user['date_creation']);
    $now = new DateTime();
    $diff = $now->diff($createdDate);

    if ($diff->days <= 7) {
      $activities[] = [
        'type' => 'new_user',
        'message' => "Nouvel utilisateur : {$user['nom']} {$user['prenom']}",
        'date' => $user['date_creation'],
        'user_id' => $user['id'],
        'priority' => 'info'
      ];
    }
  }

  // Nouveaux trajets (derniers 3 jours)
  $trajets = $db->read('trajets');
  foreach ($trajets as $trajet) {
    $createdDate = new DateTime($trajet['date_creation']);
    $now = new DateTime();
    $diff = $now->diff($createdDate);

    if ($diff->days <= 3) {
      $activities[] = [
        'type' => 'new_trajet',
        'message' => "Nouveau trajet : {$trajet['depart']} → {$trajet['arrivee']}",
        'date' => $trajet['date_creation'],
        'trajet_id' => $trajet['id'],
        'priority' => 'info'
      ];
    }
  }

  // Notifications non lues importantes
  $notifications = $db->read('notifications');
  foreach ($notifications as $notif) {
    if (!$notif['lu'] && $notif['type'] === 'incident') {
      $activities[] = [
        'type' => 'incident',
        'message' => "Incident signalé : {$notif['message']}",
        'date' => $notif['date_creation'],
        'notification_id' => $notif['id'],
        'priority' => 'urgent'
      ];
    }
  }

  // Trier par date (plus récent en premier)
  usort($activities, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
  });

  return array_slice($activities, 0, $limit);
}

/**
 * Récupération des alertes système
 */
function getSystemAlerts()
{
  $db = Database::getInstance();
  $alerts = [];

  // Vérifier les utilisateurs suspendus récemment
  $users = $db->read('utilisateurs');
  $suspendedCount = count(array_filter($users, function ($user) {
    return $user['statut'] === 'suspendu';
  }));

  if ($suspendedCount > 0) {
    $alerts[] = [
      'type' => 'warning',
      'message' => "{$suspendedCount} utilisateur(s) suspendu(s) nécessitent une révision",
      'action_url' => '/admin/users.php?filter=suspended'
    ];
  }

  // Vérifier les avis en attente de validation
  $avis = $db->read('avis');
  $pendingAvis = count(array_filter($avis, function ($av) {
    return !$av['valide'];
  }));

  if ($pendingAvis > 5) {
    $alerts[] = [
      'type' => 'info',
      'message' => "{$pendingAvis} avis en attente de validation",
      'action_url' => '/admin/avis.php?filter=pending'
    ];
  }

  // Vérifier l'activité de la plateforme
  $trajets = $db->read('trajets');
  $recentTrajets = array_filter($trajets, function ($trajet) {
    $createdDate = new DateTime($trajet['date_creation']);
    $now = new DateTime();
    $diff = $now->diff($createdDate);
    return $diff->days <= 1;
  });

  if (count($recentTrajets) === 0 && count($trajets) > 0) {
    $alerts[] = [
      'type' => 'warning',
      'message' => "Aucun nouveau trajet créé dans les dernières 24h",
      'action_url' => '/admin/trajets.php'
    ];
  }

  return $alerts;
}

// Traitement de la requête
try {
  $admin = checkAdminPermissions();

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'dashboard';

      switch ($action) {
        case 'dashboard':
          $stats = getPlatformStats();
          $activities = getRecentActivities();
          $alerts = getSystemAlerts();

          echo json_encode([
            'success' => true,
            'data' => [
              'admin' => [
                'name' => $admin['nom'] . ' ' . $admin['prenom'],
                'email' => $admin['email']
              ],
              'stats' => $stats,
              'recent_activities' => $activities,
              'alerts' => $alerts
            ]
          ]);
          break;

        case 'stats':
          $stats = getPlatformStats();
          echo json_encode([
            'success' => true,
            'data' => $stats
          ]);
          break;

        case 'activities':
          $limit = intval($_GET['limit'] ?? 20);
          $activities = getRecentActivities($limit);
          echo json_encode([
            'success' => true,
            'data' => $activities
          ]);
          break;

        case 'alerts':
          $alerts = getSystemAlerts();
          echo json_encode([
            'success' => true,
            'data' => $alerts
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
  error_log("Erreur Dashboard Admin: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
