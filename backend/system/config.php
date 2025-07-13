<?php

/**
 * Jour 8 - Finalisation et optimisations
 * API de configuration et paramètres système
 * Développé le 12 juillet 2025
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
    echo json_encode(['error' => 'Authentification requise']);
    exit;
  }

  $user = DB::findById('utilisateurs', $_SESSION['user_id']);
  if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permissions administrateur requises']);
    exit;
  }

  return $user;
}

/**
 * Configuration par défaut du système
 */
function getDefaultConfig()
{
  return [
    // Paramètres généraux
    'app_name' => 'ecoCovoit',
    'app_version' => '1.0.0',
    'app_environment' => 'production',
    'app_timezone' => 'Europe/Paris',
    'app_locale' => 'fr_FR',

    // Paramètres de covoiturage
    'max_places_par_trajet' => 8,
    'min_prix_par_place' => 1,
    'max_prix_par_place' => 100,
    'distance_max_km' => 1000,
    'delai_annulation_heures' => 24,
    'delai_confirmation_minutes' => 15,

    // Paramètres d'avis et réputation
    'note_minimum_pour_trajet' => 3.0,
    'nombre_avis_minimum_chauffeur' => 3,
    'seuil_moderation_automatique' => 2.5,
    'duree_conservation_avis_jours' => 730, // 2 ans

    // Paramètres de sécurité
    'tentatives_connexion_max' => 5,
    'duree_blocage_connexion_minutes' => 30,
    'duree_session_minutes' => 120,
    'force_https' => true,

    // Paramètres de notifications
    'notifications_email_actives' => true,
    'notifications_sms_actives' => false,
    'notifications_push_actives' => true,
    'frequence_email_resume' => 'weekly',

    // Paramètres financiers
    'commission_plateforme_pourcent' => 5.0,
    'seuil_remboursement_automatique' => 50,
    'duree_conservation_transactions_jours' => 2555, // 7 ans

    // Paramètres de performance
    'cache_duration_seconds' => 3600,
    'max_resultats_par_page' => 50,
    'timeout_api_seconds' => 30,
    'log_level' => 'info',

    // Paramètres de modération
    'moderation_automatique_active' => true,
    'seuil_signalement_automatique' => 3,
    'duree_suspension_temporaire_jours' => 7,
    'mots_interdits_actifs' => true,

    // Paramètres écologiques
    'calcul_co2_actif' => true,
    'affichage_economies_co2' => true,
    'objectif_reduction_co2_pourcent' => 20,
    'bonus_ecologique_actif' => true
  ];
}

/**
 * Récupération de la configuration système
 */
function getSystemConfig()
{
  $configFile = '../data/config_system.json';

  if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if ($config) {
      // Fusionner avec la config par défaut pour les nouvelles clés
      return array_merge(getDefaultConfig(), $config);
    }
  }

  // Créer le fichier de config avec les valeurs par défaut
  $defaultConfig = getDefaultConfig();
  $defaultConfig['date_creation'] = date('Y-m-d H:i:s');
  $defaultConfig['derniere_modification'] = date('Y-m-d H:i:s');

  file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));

  return $defaultConfig;
}

/**
 * Mise à jour de la configuration système
 */
function updateSystemConfig($newConfig, $adminId)
{
  $configFile = '../data/config_system.json';
  $currentConfig = getSystemConfig();

  // Validation des paramètres critiques
  $validationErrors = [];

  if (isset($newConfig['max_places_par_trajet']) && ($newConfig['max_places_par_trajet'] < 1 || $newConfig['max_places_par_trajet'] > 15)) {
    $validationErrors[] = 'Le nombre maximum de places doit être entre 1 et 15';
  }

  if (isset($newConfig['commission_plateforme_pourcent']) && ($newConfig['commission_plateforme_pourcent'] < 0 || $newConfig['commission_plateforme_pourcent'] > 30)) {
    $validationErrors[] = 'La commission doit être entre 0% et 30%';
  }

  if (isset($newConfig['note_minimum_pour_trajet']) && ($newConfig['note_minimum_pour_trajet'] < 1 || $newConfig['note_minimum_pour_trajet'] > 5)) {
    $validationErrors[] = 'La note minimum doit être entre 1 et 5';
  }

  if (!empty($validationErrors)) {
    return ['error' => 'Erreurs de validation', 'details' => $validationErrors];
  }

  // Mise à jour sélective
  $updatedConfig = $currentConfig;
  foreach ($newConfig as $key => $value) {
    if (array_key_exists($key, $currentConfig)) {
      $updatedConfig[$key] = $value;
    }
  }

  $updatedConfig['derniere_modification'] = date('Y-m-d H:i:s');
  $updatedConfig['modifie_par'] = $adminId;

  // Sauvegarde
  if (file_put_contents($configFile, json_encode($updatedConfig, JSON_PRETTY_PRINT))) {
    // Log de l'action
    $logEntry = [
      'date' => date('Y-m-d H:i:s'),
      'admin_id' => $adminId,
      'action' => 'update_config',
      'changes' => array_keys($newConfig),
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    $logFile = '../data/config_changes_log.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    $logs[] = $logEntry;

    // Garder seulement les 100 dernières modifications
    if (count($logs) > 100) {
      $logs = array_slice($logs, -100);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

    return ['success' => true, 'config' => $updatedConfig];
  }

  return ['error' => 'Erreur lors de la sauvegarde'];
}

/**
 * Récupération des statistiques système
 */
function getSystemStats()
{
  $stats = [];

  // Statistiques base de données
  $tables = ['utilisateurs', 'trajets', 'participations', 'avis', 'notifications', 'incidents', 'transactions'];
  foreach ($tables as $table) {
    $data = DB::findAll($table);
    $stats['database'][$table] = count($data);
  }

  // Statistiques utilisateurs
  $users = DB::findAll('utilisateurs');
  $stats['users']['total'] = count($users);
  $stats['users']['actifs'] = count(array_filter($users, fn($u) => $u['statut'] === 'actif'));
  $stats['users']['suspendus'] = count(array_filter($users, fn($u) => $u['statut'] === 'suspendu'));
  $stats['users']['admins'] = count(array_filter($users, fn($u) => $u['role'] === 'admin'));

  // Statistiques trajets
  $trajets = DB::findAll('trajets');
  $stats['trajets']['total'] = count($trajets);
  $stats['trajets']['actifs'] = count(array_filter($trajets, fn($t) => $t['statut'] === 'actif'));
  $stats['trajets']['termines'] = count(array_filter($trajets, fn($t) => $t['statut'] === 'termine'));
  $stats['trajets']['annules'] = count(array_filter($trajets, fn($t) => $t['statut'] === 'annule'));

  // Statistiques avis
  $avis = DB::findAll('avis');
  $avisValides = array_filter($avis, fn($a) => $a['valide']);
  $stats['avis']['total'] = count($avis);
  $stats['avis']['valides'] = count($avisValides);
  $stats['avis']['en_attente'] = count($avis) - count($avisValides);

  if (!empty($avisValides)) {
    $stats['avis']['note_moyenne'] = round(array_sum(array_column($avisValides, 'note')) / count($avisValides), 2);
  } else {
    $stats['avis']['note_moyenne'] = 0;
  }

  // Statistiques incidents
  $incidents = DB::findAll('incidents');
  $stats['incidents']['total'] = count($incidents);
  $stats['incidents']['ouverts'] = count(array_filter($incidents, fn($i) => $i['statut'] === 'ouvert'));
  $stats['incidents']['resolus'] = count(array_filter($incidents, fn($i) => $i['statut'] === 'resolu'));

  // Statistiques système
  $config = getSystemConfig();
  $stats['system']['version'] = $config['app_version'];
  $stats['system']['environment'] = $config['app_environment'];
  $stats['system']['derniere_modification_config'] = $config['derniere_modification'] ?? 'jamais';

  // Espace disque (approximatif pour les fichiers JSON)
  $dataDir = '../data/';
  $totalSize = 0;
  if (is_dir($dataDir)) {
    $files = glob($dataDir . '*.json');
    foreach ($files as $file) {
      $totalSize += filesize($file);
    }
  }
  $stats['system']['taille_donnees_kb'] = round($totalSize / 1024, 2);

  // Performance (temps de réponse moyen simulé)
  $stats['performance']['temps_reponse_moyen_ms'] = rand(50, 200);
  $stats['performance']['uptime_pourcentage'] = 99.5;
  $stats['performance']['derniere_mise_a_jour'] = date('Y-m-d H:i:s');

  return $stats;
}

/**
 * Nettoyage et maintenance des données
 */
function performMaintenance($adminId)
{
  $maintenanceLog = [];
  $startTime = microtime(true);

  // 1. Nettoyage des sessions expirées
  $sessionFile = '../data/sessions.json';
  if (file_exists($sessionFile)) {
    $sessions = json_decode(file_get_contents($sessionFile), true);
    $activeSessions = array_filter($sessions, function ($session) {
      return strtotime($session['expires']) > time();
    });

    $cleanedSessions = count($sessions) - count($activeSessions);
    if ($cleanedSessions > 0) {
      file_put_contents($sessionFile, json_encode($activeSessions, JSON_PRETTY_PRINT));
      $maintenanceLog[] = "Sessions expirées supprimées: $cleanedSessions";
    }
  }

  // 2. Nettoyage des notifications anciennes
  $notifications = DB::findAll('notifications');
  $oldNotifications = array_filter($notifications, function ($notif) {
    return strtotime($notif['date_creation']) < strtotime('-30 days');
  });

  foreach ($oldNotifications as $notif) {
    DB::delete('notifications', $notif['id']);
  }

  if (!empty($oldNotifications)) {
    $maintenanceLog[] = "Notifications anciennes supprimées: " . count($oldNotifications);
  }

  // 3. Archivage des trajets très anciens
  $trajets = DB::findAll('trajets');
  $oldTrajets = array_filter($trajets, function ($trajet) {
    return $trajet['statut'] === 'termine' &&
      strtotime($trajet['date_depart']) < strtotime('-1 year');
  });

  if (!empty($oldTrajets)) {
    // Créer un fichier d'archive
    $archiveFile = '../data/trajets_archives_' . date('Y') . '.json';
    $existingArchive = file_exists($archiveFile) ? json_decode(file_get_contents($archiveFile), true) : [];

    $archiveData = array_merge($existingArchive, $oldTrajets);
    file_put_contents($archiveFile, json_encode($archiveData, JSON_PRETTY_PRINT));

    $maintenanceLog[] = "Trajets archivés: " . count($oldTrajets);
  }

  // 4. Optimisation des fichiers JSON (compactage)
  $dataFiles = ['utilisateurs', 'trajets', 'participations', 'avis', 'incidents'];
  $optimizedFiles = 0;

  foreach ($dataFiles as $file) {
    $filePath = "../data/$file.json";
    if (file_exists($filePath)) {
      $data = json_decode(file_get_contents($filePath), true);
      if ($data && is_array($data)) {
        // Réindexation des IDs
        $reindexedData = array_values($data);
        file_put_contents($filePath, json_encode($reindexedData, JSON_PRETTY_PRINT));
        $optimizedFiles++;
      }
    }
  }

  if ($optimizedFiles > 0) {
    $maintenanceLog[] = "Fichiers optimisés: $optimizedFiles";
  }

  // 5. Calcul et mise à jour des statistiques cache
  $stats = getSystemStats();
  $cacheFile = '../data/stats_cache.json';
  $cacheData = [
    'stats' => $stats,
    'generated_at' => date('Y-m-d H:i:s'),
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
  ];
  file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
  $maintenanceLog[] = "Cache des statistiques régénéré";

  $endTime = microtime(true);
  $executionTime = round(($endTime - $startTime) * 1000, 2);

  // Log de la maintenance
  $maintenanceRecord = [
    'date' => date('Y-m-d H:i:s'),
    'admin_id' => $adminId,
    'execution_time_ms' => $executionTime,
    'actions' => $maintenanceLog,
    'success' => true
  ];

  $logFile = '../data/maintenance_log.json';
  $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
  $logs[] = $maintenanceRecord;

  // Garder seulement les 50 dernières maintenances
  if (count($logs) > 50) {
    $logs = array_slice($logs, -50);
  }

  file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

  return [
    'success' => true,
    'execution_time_ms' => $executionTime,
    'actions_performed' => $maintenanceLog,
    'timestamp' => date('Y-m-d H:i:s')
  ];
}

/**
 * Export des données pour sauvegarde
 */
function exportData($adminId, $includePersonalData = false)
{
  $exportData = [
    'export_info' => [
      'date' => date('Y-m-d H:i:s'),
      'admin_id' => $adminId,
      'version' => getSystemConfig()['app_version'],
      'include_personal_data' => $includePersonalData
    ]
  ];

  // Export des données système
  $exportData['config'] = getSystemConfig();
  $exportData['stats'] = getSystemStats();

  // Export des données de base
  if ($includePersonalData) {
    $exportData['utilisateurs'] = DB::findAll('utilisateurs');
  } else {
    // Version anonymisée
    $users = DB::findAll('utilisateurs');
    $exportData['utilisateurs'] = array_map(function ($user) {
      return [
        'id' => $user['id'],
        'role' => $user['role'],
        'statut' => $user['statut'],
        'date_creation' => $user['date_creation'],
        'note_moyenne' => $user['note_moyenne'] ?? 0,
        'nombre_avis' => $user['nombre_avis'] ?? 0,
        'niveau_reputation' => $user['niveau_reputation'] ?? 'nouveau'
      ];
    }, $users);
  }

  $exportData['trajets'] = DB::findAll('trajets');
  $exportData['participations'] = DB::findAll('participations');

  // Avis (sans données personnelles sensibles)
  $avis = DB::findAll('avis');
  $exportData['avis'] = array_map(function ($av) {
    $clean = $av;
    if (!empty($clean['commentaire'])) {
      $clean['commentaire'] = '[COMMENTAIRE_ANONYMISE]';
    }
    return $clean;
  }, $avis);

  $exportData['incidents'] = DB::findAll('incidents');
  $exportData['notifications'] = DB::findAll('notifications');

  // Métadonnées d'export
  $exportData['metadata'] = [
    'total_records' => array_sum(array_map('count', [
      $exportData['utilisateurs'],
      $exportData['trajets'],
      $exportData['participations'],
      $exportData['avis'],
      $exportData['incidents'],
      $exportData['notifications']
    ])),
    'export_size_mb' => round(strlen(json_encode($exportData)) / 1024 / 1024, 2)
  ];

  return $exportData;
}

// Traitement de la requête
try {
  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $action = $_GET['action'] ?? 'config';

      switch ($action) {
        case 'config':
          $config = getSystemConfig();
          echo json_encode([
            'success' => true,
            'data' => $config
          ]);
          break;

        case 'stats':
          $stats = getSystemStats();
          echo json_encode([
            'success' => true,
            'data' => $stats
          ]);
          break;

        case 'export':
          $admin = checkAdminPermissions();
          $includePersonal = isset($_GET['include_personal']) && $_GET['include_personal'] === 'true';

          $exportData = exportData($admin['id'], $includePersonal);

          // Définir les headers pour le téléchargement
          header('Content-Type: application/json');
          header('Content-Disposition: attachment; filename="ecoCovoit_export_' . date('Y-m-d_H-i-s') . '.json"');

          echo json_encode($exportData, JSON_PRETTY_PRINT);
          break;

        case 'maintenance_log':
          $admin = checkAdminPermissions();

          $logFile = '../data/maintenance_log.json';
          $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

          echo json_encode([
            'success' => true,
            'data' => array_reverse($logs) // Plus récent en premier
          ]);
          break;

        default:
          http_response_code(400);
          echo json_encode(['error' => 'Action non reconnue']);
      }
      break;

    case 'POST':
      $admin = checkAdminPermissions();
      $input = json_decode(file_get_contents('php://input'), true);

      $action = $input['action'] ?? '';

      switch ($action) {
        case 'update_config':
          if (!isset($input['config'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Configuration manquante']);
            exit;
          }

          $result = updateSystemConfig($input['config'], $admin['id']);

          if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
          } else {
            echo json_encode($result);
          }
          break;

        case 'maintenance':
          $result = performMaintenance($admin['id']);
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
  error_log("Erreur API Système: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
