<?php

/**
 * Jour 6 - APIs d'Administration
 * Gestion des incidents et signalements
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
 * Création d'un incident
 */
function createIncident($reporterId, $type, $description, $relatedData = [])
{
  $db = Database::getInstance();

  $incident = [
    'reporter_id' => $reporterId,
    'type' => $type,
    'description' => $description,
    'statut' => 'ouvert',
    'priorite' => 'normale',
    'date_creation' => date('Y-m-d H:i:s'),
    'date_modification' => date('Y-m-d H:i:s'),
    'assigned_to' => null,
    'related_data' => $relatedData
  ];

  // Déterminer la priorité automatiquement
  switch ($type) {
    case 'harcellement':
    case 'violence':
    case 'securite':
      $incident['priorite'] = 'urgente';
      break;
    case 'comportement_inapproprie':
    case 'non_respect_regles':
      $incident['priorite'] = 'haute';
      break;
    case 'probleme_technique':
    case 'retard':
    case 'annulation':
      $incident['priorite'] = 'normale';
      break;
    default:
      $incident['priorite'] = 'basse';
  }

  $incidentId = $db->create('incidents', $incident);

  if ($incidentId) {
    // Notifier tous les administrateurs
    $admins = $db->read('utilisateurs', ['role' => 'admin']);
    foreach ($admins as $admin) {
      $db->create('notifications', [
        'utilisateur_id' => $admin['id'],
        'type' => 'incident',
        'message' => "Nouveau {$incident['type']} signalé - Priorité: {$incident['priorite']}",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false,
        'data' => json_encode(['incident_id' => $incidentId])
      ]);
    }

    return ['success' => true, 'incident_id' => $incidentId];
  }

  return ['error' => 'Erreur lors de la création de l\'incident'];
}

/**
 * Récupération des incidents avec filtres
 */
function getIncidents($filters = [])
{
  $db = Database::getInstance();
  $incidents = $db->read('incidents');

  // Application des filtres
  if (!empty($filters['statut'])) {
    $incidents = array_filter($incidents, function ($incident) use ($filters) {
      return $incident['statut'] === $filters['statut'];
    });
  }

  if (!empty($filters['type'])) {
    $incidents = array_filter($incidents, function ($incident) use ($filters) {
      return $incident['type'] === $filters['type'];
    });
  }

  if (!empty($filters['priorite'])) {
    $incidents = array_filter($incidents, function ($incident) use ($filters) {
      return $incident['priorite'] === $filters['priorite'];
    });
  }

  if (!empty($filters['assigned_to'])) {
    $incidents = array_filter($incidents, function ($incident) use ($filters) {
      return $incident['assigned_to'] === $filters['assigned_to'];
    });
  }

  // Enrichissement avec informations des utilisateurs
  $users = $db->read('utilisateurs');
  $usersById = [];
  foreach ($users as $user) {
    $usersById[$user['id']] = $user;
  }

  foreach ($incidents as &$incident) {
    // Informations du rapporteur
    $reporter = $usersById[$incident['reporter_id']] ?? null;
    $incident['reporter'] = $reporter ? [
      'id' => $reporter['id'],
      'nom' => $reporter['nom'],
      'prenom' => $reporter['prenom'],
      'email' => $reporter['email']
    ] : null;

    // Informations de l'assigné
    if ($incident['assigned_to']) {
      $assignee = $usersById[$incident['assigned_to']] ?? null;
      $incident['assignee'] = $assignee ? [
        'id' => $assignee['id'],
        'nom' => $assignee['nom'],
        'prenom' => $assignee['prenom']
      ] : null;
    }

    // Historique des actions
    $incident['actions'] = $incident['actions'] ?? [];

    // Calculer le temps depuis la création
    $created = new DateTime($incident['date_creation']);
    $now = new DateTime();
    $diff = $now->diff($created);
    $incident['time_since_creation'] = $diff->format('%d jours %h heures');
  }

  // Trier par priorité et date
  usort($incidents, function ($a, $b) {
    $priorityOrder = ['urgente' => 4, 'haute' => 3, 'normale' => 2, 'basse' => 1];
    $aPriority = $priorityOrder[$a['priorite']] ?? 0;
    $bPriority = $priorityOrder[$b['priorite']] ?? 0;

    if ($aPriority === $bPriority) {
      return strtotime($b['date_creation']) - strtotime($a['date_creation']);
    }

    return $bPriority - $aPriority;
  });

  return array_values($incidents);
}

/**
 * Assignation d'un incident à un administrateur
 */
function assignIncident($incidentId, $adminId, $assignedBy)
{
  $db = Database::getInstance();

  $incident = $db->read('incidents', ['id' => $incidentId]);
  if (empty($incident)) {
    return ['error' => 'Incident non trouvé'];
  }

  $incident = $incident[0];
  $actions = $incident['actions'] ?? [];

  // Ajouter l'action d'assignation
  $actions[] = [
    'type' => 'assigned',
    'admin_id' => $assignedBy,
    'description' => "Incident assigné à l'administrateur {$adminId}",
    'date' => date('Y-m-d H:i:s')
  ];

  $result = $db->update('incidents', [
    'assigned_to' => $adminId,
    'statut' => 'en_cours',
    'actions' => $actions,
    'date_modification' => date('Y-m-d H:i:s')
  ], ['id' => $incidentId]);

  if ($result) {
    // Notifier l'administrateur assigné
    $db->create('notifications', [
      'utilisateur_id' => $adminId,
      'type' => 'assignation',
      'message' => "Un incident vous a été assigné - Type: {$incident['type']}",
      'date_creation' => date('Y-m-d H:i:s'),
      'lu' => false,
      'data' => json_encode(['incident_id' => $incidentId])
    ]);

    return ['success' => true, 'message' => 'Incident assigné avec succès'];
  }

  return ['error' => 'Erreur lors de l\'assignation'];
}

/**
 * Action sur un incident
 */
function updateIncidentStatus($incidentId, $newStatus, $adminId, $notes = '')
{
  $db = Database::getInstance();

  $incident = $db->read('incidents', ['id' => $incidentId]);
  if (empty($incident)) {
    return ['error' => 'Incident non trouvé'];
  }

  $incident = $incident[0];
  $actions = $incident['actions'] ?? [];

  // Ajouter l'action
  $actions[] = [
    'type' => 'status_change',
    'admin_id' => $adminId,
    'old_status' => $incident['statut'],
    'new_status' => $newStatus,
    'notes' => $notes,
    'date' => date('Y-m-d H:i:s')
  ];

  $updateData = [
    'statut' => $newStatus,
    'actions' => $actions,
    'date_modification' => date('Y-m-d H:i:s')
  ];

  // Si fermeture, ajouter la date de fermeture
  if ($newStatus === 'ferme') {
    $updateData['date_fermeture'] = date('Y-m-d H:i:s');
  }

  $result = $db->update('incidents', $updateData, ['id' => $incidentId]);

  if ($result) {
    // Notifier le rapporteur de la résolution
    if ($newStatus === 'ferme') {
      $db->create('notifications', [
        'utilisateur_id' => $incident['reporter_id'],
        'type' => 'resolution',
        'message' => "Votre signalement a été traité et résolu. Merci pour votre collaboration.",
        'date_creation' => date('Y-m-d H:i:s'),
        'lu' => false
      ]);
    }

    return ['success' => true, 'message' => 'Statut mis à jour avec succès'];
  }

  return ['error' => 'Erreur lors de la mise à jour'];
}

/**
 * Statistiques des incidents
 */
function getIncidentStats()
{
  $db = Database::getInstance();
  $incidents = $db->read('incidents');

  $stats = [
    'total' => count($incidents),
    'ouverts' => 0,
    'en_cours' => 0,
    'fermes' => 0,
    'par_type' => [],
    'par_priorite' => ['urgente' => 0, 'haute' => 0, 'normale' => 0, 'basse' => 0],
    'temps_resolution_moyen' => 0,
    'incidents_recents' => []
  ];

  $tempsResolutionTotal = 0;
  $incidentsResolus = 0;

  foreach ($incidents as $incident) {
    // Compter par statut
    $stats[$incident['statut']]++;

    // Compter par type
    $type = $incident['type'];
    $stats['par_type'][$type] = ($stats['par_type'][$type] ?? 0) + 1;

    // Compter par priorité
    $stats['par_priorite'][$incident['priorite']]++;

    // Calculer temps de résolution
    if ($incident['statut'] === 'ferme' && isset($incident['date_fermeture'])) {
      $created = new DateTime($incident['date_creation']);
      $closed = new DateTime($incident['date_fermeture']);
      $diff = $closed->diff($created);
      $tempsResolutionTotal += $diff->days * 24 + $diff->h;
      $incidentsResolus++;
    }

    // Incidents récents (derniers 7 jours)
    $created = new DateTime($incident['date_creation']);
    $now = new DateTime();
    $diff = $now->diff($created);

    if ($diff->days <= 7) {
      $stats['incidents_recents'][] = [
        'id' => $incident['id'],
        'type' => $incident['type'],
        'priorite' => $incident['priorite'],
        'statut' => $incident['statut'],
        'date' => $incident['date_creation']
      ];
    }
  }

  // Temps de résolution moyen en heures
  if ($incidentsResolus > 0) {
    $stats['temps_resolution_moyen'] = round($tempsResolutionTotal / $incidentsResolus, 1);
  }

  // Trier les incidents récents par date
  usort($stats['incidents_recents'], function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
  });

  return $stats;
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
            'type' => $_GET['type'] ?? '',
            'priorite' => $_GET['priorite'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? ''
          ];

          $incidents = getIncidents($filters);
          echo json_encode([
            'success' => true,
            'data' => $incidents,
            'count' => count($incidents)
          ]);
          break;

        case 'stats':
          $stats = getIncidentStats();
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
        case 'create':
          if (!isset($input['type']) || !isset($input['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = createIncident(
            $input['reporter_id'] ?? $admin['id'],
            $input['type'],
            $input['description'],
            $input['related_data'] ?? []
          );
          echo json_encode($result);
          break;

        case 'assign':
          if (!isset($input['incident_id']) || !isset($input['admin_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = assignIncident(
            $input['incident_id'],
            $input['admin_id'],
            $admin['id']
          );
          echo json_encode($result);
          break;

        case 'update_status':
          if (!isset($input['incident_id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
          }

          $result = updateIncidentStatus(
            $input['incident_id'],
            $input['status'],
            $admin['id'],
            $input['notes'] ?? ''
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
  error_log("Erreur Gestion Incidents: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
