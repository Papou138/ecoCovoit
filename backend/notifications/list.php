<?php

/**
 * API de gestion des notifications
 *
 * Endpoints:
 * - GET /backend/notifications/list.php (Lister les notifications)
 * - PUT /backend/notifications/mark-read.php (Marquer comme lues)
 * - DELETE /backend/notifications/delete.php (Supprimer)
 *
 * Nécessite une authentification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier l'authentification
$user = requireAuth();

try {
  // === TRAITEMENT SELON LA MÉTHODE ===

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      listNotifications($user);
      break;

    case 'PUT':
      markNotificationsAsRead($user);
      break;

    case 'DELETE':
      deleteNotifications($user);
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur notifications API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la gestion des notifications', null, 500);
}

/**
 * Lister les notifications de l'utilisateur
 */
function listNotifications($user)
{
  // === RÉCUPÉRATION DES PARAMÈTRES ===

  $limite = min((int)($_GET['limite'] ?? 20), 50); // Max 50 notifications
  $page = max((int)($_GET['page'] ?? 1), 1);
  $nonLueSeulement = isset($_GET['non_lues']) && $_GET['non_lues'] === '1';
  $type = $_GET['type'] ?? null; // Filtrer par type

  // === RÉCUPÉRATION DES NOTIFICATIONS ===

  $criteria = ['utilisateur_id' => $user['id']];

  if ($nonLueSeulement) {
    $criteria['lu'] = false;
  }

  if ($type) {
    $criteria['type'] = $type;
  }

  $notifications = DB::findAll('notifications', $criteria, 'date_creation DESC');

  // === ENRICHISSEMENT DES DONNÉES ===

  $notificationsEnrichies = [];
  foreach ($notifications as $notification) {
    $notifEnrichie = $notification;

    // Formatage de la date
    $dateCreation = new DateTime($notification['date_creation']);
    $maintenant = new DateTime();
    $diff = $maintenant->diff($dateCreation);

    $notifEnrichie['date_formatted'] = formatDateRelative($diff);
    $notifEnrichie['date_complete'] = $dateCreation->format('d/m/Y à H:i');

    // Enrichissement selon le type
    $notifEnrichie = enrichNotification($notifEnrichie);

    $notificationsEnrichies[] = $notifEnrichie;
  }

  // === PAGINATION ===

  $totalNotifications = count($notificationsEnrichies);
  $totalPages = ceil($totalNotifications / $limite);
  $offset = ($page - 1) * $limite;

  $notificationsPaginees = array_slice($notificationsEnrichies, $offset, $limite);

  // === STATISTIQUES ===

  $stats = [
    'total' => count(DB::findAll('notifications', ['utilisateur_id' => $user['id']])),
    'non_lues' => count(DB::findAll('notifications', [
      'utilisateur_id' => $user['id'],
      'lu' => false
    ])),
    'types' => getNotificationTypes($user['id'])
  ];

  // === RÉPONSE ===

  $responseData = [
    'notifications' => $notificationsPaginees,
    'pagination' => [
      'page_actuelle' => $page,
      'total_pages' => $totalPages,
      'total_notifications' => $totalNotifications,
      'limite_par_page' => $limite
    ],
    'statistiques' => $stats,
    'filtres_appliques' => [
      'non_lues_seulement' => $nonLueSeulement,
      'type' => $type
    ]
  ];

  jsonResponse(true, 'Notifications récupérées avec succès', $responseData);
}

/**
 * Marquer des notifications comme lues
 */
function markNotificationsAsRead($user)
{
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    jsonResponse(false, 'Données JSON invalides');
  }

  $notificationIds = $input['notification_ids'] ?? [];
  $marquerToutesCommeLues = $input['toutes'] ?? false;

  $notificationsModifiees = 0;

  if ($marquerToutesCommeLues) {
    // Marquer toutes les notifications non lues de l'utilisateur
    $notificationsNonLues = DB::findAll('notifications', [
      'utilisateur_id' => $user['id'],
      'lu' => false
    ]);

    foreach ($notificationsNonLues as $notification) {
      DB::update('notifications', $notification['id'], [
        'lu' => true,
        'date_lecture' => date('Y-m-d H:i:s')
      ]);
      $notificationsModifiees++;
    }
  } else {
    // Marquer les notifications spécifiées
    if (empty($notificationIds) || !is_array($notificationIds)) {
      jsonResponse(false, 'Liste des IDs de notifications requise');
    }

    foreach ($notificationIds as $notifId) {
      $notification = DB::findById('notifications', (int)$notifId);

      if ($notification && $notification['utilisateur_id'] == $user['id']) {
        DB::update('notifications', $notification['id'], [
          'lu' => true,
          'date_lecture' => date('Y-m-d H:i:s')
        ]);
        $notificationsModifiees++;
      }
    }
  }

  // === RÉPONSE ===

  $responseData = [
    'notifications_modifiees' => $notificationsModifiees,
    'nouvelles_non_lues' => count(DB::findAll('notifications', [
      'utilisateur_id' => $user['id'],
      'lu' => false
    ]))
  ];

  jsonResponse(true, "$notificationsModifiees notification(s) marquée(s) comme lue(s)", $responseData);
}

/**
 * Supprimer des notifications
 */
function deleteNotifications($user)
{
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    jsonResponse(false, 'Données JSON invalides');
  }

  $notificationIds = $input['notification_ids'] ?? [];
  $supprimerToutesLues = $input['toutes_lues'] ?? false;

  $notificationsSupprimees = 0;

  if ($supprimerToutesLues) {
    // Supprimer toutes les notifications lues de l'utilisateur
    $notificationsLues = DB::findAll('notifications', [
      'utilisateur_id' => $user['id'],
      'lu' => true
    ]);

    foreach ($notificationsLues as $notification) {
      DB::delete('notifications', $notification['id']);
      $notificationsSupprimees++;
    }
  } else {
    // Supprimer les notifications spécifiées
    if (empty($notificationIds) || !is_array($notificationIds)) {
      jsonResponse(false, 'Liste des IDs de notifications requise');
    }

    foreach ($notificationIds as $notifId) {
      $notification = DB::findById('notifications', (int)$notifId);

      if ($notification && $notification['utilisateur_id'] == $user['id']) {
        DB::delete('notifications', $notification['id']);
        $notificationsSupprimees++;
      }
    }
  }

  // === RÉPONSE ===

  $responseData = [
    'notifications_supprimees' => $notificationsSupprimees,
    'total_restantes' => count(DB::findAll('notifications', [
      'utilisateur_id' => $user['id']
    ]))
  ];

  jsonResponse(true, "$notificationsSupprimees notification(s) supprimée(s)", $responseData);
}

/**
 * Enrichit une notification avec des informations contextuelle s
 */
function enrichNotification($notification)
{
  // Icônes et couleurs selon le type
  $typeConfig = [
    'trajet_demarre' => ['🚗', 'Trajet démarré', 'success'],
    'trajet_termine' => ['✅', 'Trajet terminé', 'success'],
    'trajet_annule' => ['❌', 'Trajet annulé', 'danger'],
    'nouvelle_participation' => ['👥', 'Nouvelle participation', 'info'],
    'participation_annulee' => ['⚠️', 'Participation annulée', 'warning'],
    'nouveau_message' => ['💬', 'Nouveau message', 'primary'],
    'avis_recu' => ['⭐', 'Avis reçu', 'success'],
    'rappel_trajet' => ['🔔', 'Rappel de trajet', 'info'],
    'credit_recu' => ['💰', 'Crédit reçu', 'success'],
    'credit_debite' => ['💸', 'Crédit débité', 'warning']
  ];

  $config = $typeConfig[$notification['type']] ?? ['📢', 'Notification', 'secondary'];

  $notification['icone'] = $config[0];
  $notification['type_libelle'] = $config[1];
  $notification['couleur'] = $config[2];

  // Enrichissement avec les données du trajet si applicable
  if ($notification['trajet_id']) {
    $trajet = DB::findById('trajets', $notification['trajet_id']);
    if ($trajet) {
      $notification['trajet'] = [
        'id' => $trajet['id'],
        'depart' => $trajet['depart'],
        'arrivee' => $trajet['arrivee'],
        'date_depart' => $trajet['date_depart'],
        'heure_depart' => $trajet['heure_depart']
      ];
    }
  }

  return $notification;
}

/**
 * Récupère les types de notifications avec leur nombre
 */
function getNotificationTypes($userId)
{
  $notifications = DB::findAll('notifications', ['utilisateur_id' => $userId]);
  $types = [];

  foreach ($notifications as $notification) {
    $type = $notification['type'];
    if (!isset($types[$type])) {
      $types[$type] = [
        'total' => 0,
        'non_lues' => 0
      ];
    }

    $types[$type]['total']++;

    if (!$notification['lu']) {
      $types[$type]['non_lues']++;
    }
  }

  return $types;
}

/**
 * Formate une date en format relatif (il y a X temps)
 */
function formatDateRelative($diff)
{
  if ($diff->days > 0) {
    if ($diff->days == 1) {
      return 'Hier';
    } elseif ($diff->days <= 7) {
      return 'Il y a ' . $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
    } else {
      return 'Il y a ' . intval($diff->days / 7) . ' semaine' . (intval($diff->days / 7) > 1 ? 's' : '');
    }
  } elseif ($diff->h > 0) {
    return 'Il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
  } elseif ($diff->i > 0) {
    return 'Il y a ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
  } else {
    return 'À l\'instant';
  }
}
