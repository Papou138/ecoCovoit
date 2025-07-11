<?php

/**
 * Jour 8 - Finalisation et optimisations
 * API de monitoring et observabilité
 * Développé le 12 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/DB.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

/**
 * Enregistrement d'un événement de monitoring
 */
function logEvent($type, $data, $userId = null)
{
  $event = [
    'id' => uniqid(),
    'timestamp' => date('Y-m-d H:i:s'),
    'type' => $type,
    'user_id' => $userId,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'data' => $data
  ];

  $logFile = '../data/monitoring_events.json';
  $events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
  $events[] = $event;

  // Garder seulement les 1000 derniers événements
  if (count($events) > 1000) {
    $events = array_slice($events, -1000);
  }

  file_put_contents($logFile, json_encode($events, JSON_PRETTY_PRINT));

  return $event['id'];
}

/**
 * Métriques de performance en temps réel
 */
function getPerformanceMetrics()
{
  $startTime = microtime(true);

  // Test de performance base de données
  $dbTestStart = microtime(true);
  $users = DB::findAll('utilisateurs');
  $dbTestEnd = microtime(true);
  $dbResponseTime = round(($dbTestEnd - $dbTestStart) * 1000, 2);

  // Test de performance fichiers
  $fileTestStart = microtime(true);
  $configFile = '../data/config_system.json';
  if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
  }
  $fileTestEnd = microtime(true);
  $fileResponseTime = round(($fileTestEnd - $fileTestStart) * 1000, 2);

  // Utilisation mémoire
  $memoryUsage = [
    'current_mb' => round(memory_get_usage() / 1024 / 1024, 2),
    'peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
    'limit_mb' => ini_get('memory_limit')
  ];

  // Espace disque
  $dataDir = '../data/';
  $diskUsage = [
    'data_directory_mb' => 0,
    'total_files' => 0
  ];

  if (is_dir($dataDir)) {
    $files = glob($dataDir . '*');
    $diskUsage['total_files'] = count($files);

    $totalSize = 0;
    foreach ($files as $file) {
      if (is_file($file)) {
        $totalSize += filesize($file);
      }
    }
    $diskUsage['data_directory_mb'] = round($totalSize / 1024 / 1024, 2);
  }

  $endTime = microtime(true);
  $totalResponseTime = round(($endTime - $startTime) * 1000, 2);

  return [
    'timestamp' => date('Y-m-d H:i:s'),
    'response_times' => [
      'total_ms' => $totalResponseTime,
      'database_ms' => $dbResponseTime,
      'file_system_ms' => $fileResponseTime
    ],
    'memory' => $memoryUsage,
    'disk' => $diskUsage,
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
  ];
}

/**
 * Analyse des tendances d'utilisation
 */
function getUsageTrends($period = '7d')
{
  $logFile = '../data/monitoring_events.json';
  $events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

  // Définir la période d'analyse
  $cutoffDate = match ($period) {
    '1d' => strtotime('-1 day'),
    '7d' => strtotime('-7 days'),
    '30d' => strtotime('-30 days'),
    default => strtotime('-7 days')
  };

  // Filtrer les événements par période
  $relevantEvents = array_filter($events, function ($event) use ($cutoffDate) {
    return strtotime($event['timestamp']) >= $cutoffDate;
  });

  // Analyse par type d'événement
  $eventTypes = [];
  foreach ($relevantEvents as $event) {
    $type = $event['type'];
    if (!isset($eventTypes[$type])) {
      $eventTypes[$type] = ['count' => 0, 'unique_users' => []];
    }
    $eventTypes[$type]['count']++;
    if ($event['user_id']) {
      $eventTypes[$type]['unique_users'][$event['user_id']] = true;
    }
  }

  // Convertir les utilisateurs uniques en compteur
  foreach ($eventTypes as &$typeData) {
    $typeData['unique_users'] = count($typeData['unique_users']);
  }

  // Analyse par heure (dernières 24h)
  $hourlyStats = [];
  for ($i = 23; $i >= 0; $i--) {
    $hour = date('H', strtotime("-{$i} hours"));
    $hourStart = strtotime("-{$i} hours", strtotime(date('Y-m-d H:00:00')));
    $hourEnd = strtotime("-{$i} hours", strtotime(date('Y-m-d H:59:59')));

    $hourEvents = array_filter($relevantEvents, function ($event) use ($hourStart, $hourEnd) {
      $eventTime = strtotime($event['timestamp']);
      return $eventTime >= $hourStart && $eventTime <= $hourEnd;
    });

    $hourlyStats[] = [
      'hour' => $hour,
      'events' => count($hourEvents),
      'unique_users' => count(array_unique(array_column($hourEvents, 'user_id')))
    ];
  }

  // Top utilisateurs actifs
  $userActivity = [];
  foreach ($relevantEvents as $event) {
    if ($event['user_id']) {
      $userId = $event['user_id'];
      if (!isset($userActivity[$userId])) {
        $userActivity[$userId] = 0;
      }
      $userActivity[$userId]++;
    }
  }

  arsort($userActivity);
  $topUsers = array_slice($userActivity, 0, 10, true);

  return [
    'period' => $period,
    'total_events' => count($relevantEvents),
    'unique_users' => count(array_unique(array_column($relevantEvents, 'user_id'))),
    'event_types' => $eventTypes,
    'hourly_stats' => $hourlyStats,
    'top_users' => $topUsers,
    'analysis_timestamp' => date('Y-m-d H:i:s')
  ];
}

/**
 * Détection d'anomalies
 */
function detectAnomalies()
{
  $anomalies = [];

  // 1. Vérification des utilisateurs suspects
  $users = DB::findAll('utilisateurs');
  foreach ($users as $user) {
    // Utilisateur avec beaucoup d'avis négatifs
    $avisUser = DB::findAll('avis', ['evalue_id' => $user['id'], 'valide' => true]);
    if (count($avisUser) >= 5) {
      $notesNegatives = array_filter($avisUser, fn($a) => $a['note'] <= 2);
      $pourcentageNegatif = count($notesNegatives) / count($avisUser);

      if ($pourcentageNegatif >= 0.6) {
        $anomalies[] = [
          'type' => 'user_suspicious',
          'severity' => 'medium',
          'message' => "Utilisateur {$user['id']} avec {$pourcentageNegatif}% d'avis négatifs",
          'data' => ['user_id' => $user['id'], 'negative_ratio' => $pourcentageNegatif]
        ];
      }
    }
  }

  // 2. Vérification des trajets suspects
  $trajets = DB::findAll('trajets');
  foreach ($trajets as $trajet) {
    // Prix anormalement élevé ou bas
    $distance = $trajet['distance_km'] ?? 100;
    $prix = $trajet['prix_par_place'] ?? 0;

    if ($distance > 0) {
      $prixParKm = $prix / $distance;

      if ($prixParKm > 1.5) { // Plus de 1.50€/km
        $anomalies[] = [
          'type' => 'trajet_price_high',
          'severity' => 'low',
          'message' => "Trajet {$trajet['id']} avec prix élevé: {$prixParKm}€/km",
          'data' => ['trajet_id' => $trajet['id'], 'price_per_km' => $prixParKm]
        ];
      } elseif ($prixParKm < 0.05) { // Moins de 5 centimes/km
        $anomalies[] = [
          'type' => 'trajet_price_low',
          'severity' => 'low',
          'message' => "Trajet {$trajet['id']} avec prix très bas: {$prixParKm}€/km",
          'data' => ['trajet_id' => $trajet['id'], 'price_per_km' => $prixParKm]
        ];
      }
    }
  }

  // 3. Vérification des avis suspects
  $avis = DB::findAll('avis');
  $avisParUtilisateur = [];

  foreach ($avis as $av) {
    $evaluateurId = $av['evaluateur_id'];
    if (!isset($avisParUtilisateur[$evaluateurId])) {
      $avisParUtilisateur[$evaluateurId] = [];
    }
    $avisParUtilisateur[$evaluateurId][] = $av;
  }

  foreach ($avisParUtilisateur as $userId => $userAvis) {
    // Utilisateur qui donne toujours la même note
    if (count($userAvis) >= 5) {
      $notes = array_column($userAvis, 'note');
      $noteUnique = array_unique($notes);

      if (count($noteUnique) == 1) {
        $anomalies[] = [
          'type' => 'avis_pattern_suspicious',
          'severity' => 'medium',
          'message' => "Utilisateur {$userId} donne toujours la note {$notes[0]}",
          'data' => ['user_id' => $userId, 'pattern' => 'same_rating', 'rating' => $notes[0]]
        ];
      }
    }
  }

  // 4. Vérification des pics d'activité
  $logFile = '../data/monitoring_events.json';
  if (file_exists($logFile)) {
    $events = json_decode(file_get_contents($logFile), true);
    $recentEvents = array_filter($events, function ($event) {
      return strtotime($event['timestamp']) >= strtotime('-1 hour');
    });

    if (count($recentEvents) > 100) { // Plus de 100 événements en 1h
      $anomalies[] = [
        'type' => 'activity_spike',
        'severity' => 'high',
        'message' => "Pic d'activité détecté: " . count($recentEvents) . " événements en 1h",
        'data' => ['events_count' => count($recentEvents), 'period' => '1h']
      ];
    }
  }

  return [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_anomalies' => count($anomalies),
    'anomalies' => $anomalies,
    'severity_counts' => [
      'high' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'high')),
      'medium' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'medium')),
      'low' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'low'))
    ]
  ];
}

/**
 * Rapport de santé système
 */
function getSystemHealth()
{
  $health = [
    'timestamp' => date('Y-m-d H:i:s'),
    'overall_status' => 'healthy',
    'checks' => []
  ];

  // 1. Vérification de la base de données
  try {
    $users = DB::findAll('utilisateurs');
    $health['checks']['database'] = [
      'status' => 'healthy',
      'message' => 'Base de données accessible',
      'details' => ['users_count' => count($users)]
    ];
  } catch (Exception $e) {
    $health['checks']['database'] = [
      'status' => 'error',
      'message' => 'Erreur base de données: ' . $e->getMessage()
    ];
    $health['overall_status'] = 'degraded';
  }

  // 2. Vérification des fichiers de données
  $dataFiles = ['utilisateurs', 'trajets', 'participations', 'avis', 'notifications'];
  $filesOk = 0;

  foreach ($dataFiles as $file) {
    $filePath = "../data/$file.json";
    if (file_exists($filePath) && is_readable($filePath)) {
      $filesOk++;
    }
  }

  if ($filesOk == count($dataFiles)) {
    $health['checks']['data_files'] = [
      'status' => 'healthy',
      'message' => 'Tous les fichiers de données sont accessibles',
      'details' => ['files_count' => $filesOk]
    ];
  } else {
    $health['checks']['data_files'] = [
      'status' => 'warning',
      'message' => "Seulement $filesOk/" . count($dataFiles) . " fichiers accessibles"
    ];
    if ($health['overall_status'] === 'healthy') {
      $health['overall_status'] = 'warning';
    }
  }

  // 3. Vérification de l'espace disque
  $dataDir = '../data/';
  $totalSize = 0;
  if (is_dir($dataDir)) {
    $files = glob($dataDir . '*');
    foreach ($files as $file) {
      if (is_file($file)) {
        $totalSize += filesize($file);
      }
    }
  }

  $sizeMB = round($totalSize / 1024 / 1024, 2);
  if ($sizeMB < 100) { // Moins de 100MB
    $health['checks']['disk_space'] = [
      'status' => 'healthy',
      'message' => 'Espace disque suffisant',
      'details' => ['data_size_mb' => $sizeMB]
    ];
  } else {
    $health['checks']['disk_space'] = [
      'status' => 'warning',
      'message' => 'Utilisation disque élevée: ' . $sizeMB . 'MB'
    ];
    if ($health['overall_status'] === 'healthy') {
      $health['overall_status'] = 'warning';
    }
  }

  // 4. Vérification des performances
  $perfMetrics = getPerformanceMetrics();
  $dbResponseTime = $perfMetrics['response_times']['database_ms'];

  if ($dbResponseTime < 100) {
    $health['checks']['performance'] = [
      'status' => 'healthy',
      'message' => 'Performances normales',
      'details' => ['db_response_ms' => $dbResponseTime]
    ];
  } elseif ($dbResponseTime < 500) {
    $health['checks']['performance'] = [
      'status' => 'warning',
      'message' => 'Performances dégradées',
      'details' => ['db_response_ms' => $dbResponseTime]
    ];
    if ($health['overall_status'] === 'healthy') {
      $health['overall_status'] = 'warning';
    }
  } else {
    $health['checks']['performance'] = [
      'status' => 'error',
      'message' => 'Performances critiques',
      'details' => ['db_response_ms' => $dbResponseTime]
    ];
    $health['overall_status'] = 'degraded';
  }

  // 5. Vérification des anomalies
  $anomalies = detectAnomalies();
  $highSeverityCount = $anomalies['severity_counts']['high'];

  if ($highSeverityCount == 0) {
    $health['checks']['anomalies'] = [
      'status' => 'healthy',
      'message' => 'Aucune anomalie critique détectée',
      'details' => $anomalies['severity_counts']
    ];
  } else {
    $health['checks']['anomalies'] = [
      'status' => 'warning',
      'message' => "$highSeverityCount anomalie(s) critique(s) détectée(s)",
      'details' => $anomalies['severity_counts']
    ];
    if ($health['overall_status'] === 'healthy') {
      $health['overall_status'] = 'warning';
    }
  }

  return $health;
}

// Traitement de la requête
try {
  $action = $_GET['action'] ?? 'health';

  // Log de l'accès à l'API
  $userId = $_SESSION['user_id'] ?? null;
  logEvent('api_monitoring_access', ['action' => $action], $userId);

  switch ($action) {
    case 'health':
      $health = getSystemHealth();
      echo json_encode([
        'success' => true,
        'data' => $health
      ]);
      break;

    case 'performance':
      $metrics = getPerformanceMetrics();
      echo json_encode([
        'success' => true,
        'data' => $metrics
      ]);
      break;

    case 'trends':
      $period = $_GET['period'] ?? '7d';
      $trends = getUsageTrends($period);
      echo json_encode([
        'success' => true,
        'data' => $trends
      ]);
      break;

    case 'anomalies':
      $anomalies = detectAnomalies();
      echo json_encode([
        'success' => true,
        'data' => $anomalies
      ]);
      break;

    case 'events':
      // Vérification des permissions admin pour voir les événements
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

      $logFile = '../data/monitoring_events.json';
      $events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

      // Pagination
      $page = max(1, intval($_GET['page'] ?? 1));
      $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
      $offset = ($page - 1) * $limit;

      $totalEvents = count($events);
      $pageEvents = array_slice(array_reverse($events), $offset, $limit);

      echo json_encode([
        'success' => true,
        'data' => [
          'events' => $pageEvents,
          'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalEvents,
            'pages' => ceil($totalEvents / $limit)
          ]
        ]
      ]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Action non reconnue']);
  }
} catch (Exception $e) {
  error_log("Erreur API Monitoring: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}
