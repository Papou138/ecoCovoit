<?php

/**
 * Jour 8 - Finalisation et optimisations
 * API d'optimisation et de cache
 * Développé le 12 juillet 2025
 */

session_start();
require_once '../config/config.php';
require_once '../models/DB.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

/**
 * Gestionnaire de cache simple
 */
class CacheManager
{
  private static $cacheDir = '../data/cache/';

  public static function init()
  {
    if (!is_dir(self::$cacheDir)) {
      mkdir(self::$cacheDir, 0755, true);
    }
  }

  public static function set($key, $data, $ttl = 3600)
  {
    self::init();

    $cacheData = [
      'data' => $data,
      'expires_at' => time() + $ttl,
      'created_at' => time()
    ];

    $filename = self::$cacheDir . md5($key) . '.cache';
    return file_put_contents($filename, json_encode($cacheData)) !== false;
  }

  public static function get($key)
  {
    self::init();

    $filename = self::$cacheDir . md5($key) . '.cache';

    if (!file_exists($filename)) {
      return null;
    }

    $cacheData = json_decode(file_get_contents($filename), true);

    if (!$cacheData || time() > $cacheData['expires_at']) {
      unlink($filename);
      return null;
    }

    return $cacheData['data'];
  }

  public static function delete($key)
  {
    self::init();

    $filename = self::$cacheDir . md5($key) . '.cache';

    if (file_exists($filename)) {
      return unlink($filename);
    }

    return true;
  }

  public static function clear()
  {
    self::init();

    $files = glob(self::$cacheDir . '*.cache');
    $cleared = 0;

    foreach ($files as $file) {
      if (unlink($file)) {
        $cleared++;
      }
    }

    return $cleared;
  }

  public static function getStats()
  {
    self::init();

    $files = glob(self::$cacheDir . '*.cache');
    $totalSize = 0;
    $activeItems = 0;
    $expiredItems = 0;

    foreach ($files as $file) {
      $totalSize += filesize($file);

      $cacheData = json_decode(file_get_contents($file), true);
      if ($cacheData) {
        if (time() > $cacheData['expires_at']) {
          $expiredItems++;
        } else {
          $activeItems++;
        }
      }
    }

    return [
      'total_items' => count($files),
      'active_items' => $activeItems,
      'expired_items' => $expiredItems,
      'total_size_mb' => round($totalSize / 1024 / 1024, 2),
      'cache_directory' => self::$cacheDir
    ];
  }

  public static function cleanup()
  {
    self::init();

    $files = glob(self::$cacheDir . '*.cache');
    $cleaned = 0;

    foreach ($files as $file) {
      $cacheData = json_decode(file_get_contents($file), true);
      if ($cacheData && time() > $cacheData['expires_at']) {
        if (unlink($file)) {
          $cleaned++;
        }
      }
    }

    return $cleaned;
  }
}

/**
 * Optimisation des requêtes de recherche de trajets
 */
function optimizeTrajetSearch($filters = [])
{
  $cacheKey = 'trajet_search_' . md5(serialize($filters));

  // Vérifier le cache
  $cachedResult = CacheManager::get($cacheKey);
  if ($cachedResult !== null) {
    return [
      'data' => $cachedResult,
      'cached' => true,
      'cache_key' => $cacheKey
    ];
  }

  $startTime = microtime(true);

  // Récupérer tous les trajets
  $trajets = DB::findAll('trajets');

  // Appliquer les filtres de manière optimisée
  $filteredTrajets = $trajets;

  // Filtre par statut (plus courant en premier)
  if (!empty($filters['statut'])) {
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($filters) {
      return $t['statut'] === $filters['statut'];
    });
  }

  // Filtre par date (optimisé avec strtotime)
  if (!empty($filters['date_depart'])) {
    $targetDate = strtotime($filters['date_depart']);
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($targetDate) {
      return strtotime($t['date_depart']) >= $targetDate;
    });
  }

  // Filtre par localisation (recherche optimisée)
  if (!empty($filters['depart'])) {
    $departQuery = strtolower($filters['depart']);
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($departQuery) {
      return strpos(strtolower($t['depart']), $departQuery) !== false;
    });
  }

  if (!empty($filters['arrivee'])) {
    $arriveeQuery = strtolower($filters['arrivee']);
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($arriveeQuery) {
      return strpos(strtolower($t['arrivee']), $arriveeQuery) !== false;
    });
  }

  // Filtre par prix
  if (!empty($filters['prix_max'])) {
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($filters) {
      return $t['prix_par_place'] <= $filters['prix_max'];
    });
  }

  // Filtre par places disponibles
  if (!empty($filters['places_min'])) {
    $filteredTrajets = array_filter($filteredTrajets, function ($t) use ($filters) {
      return $t['places_disponibles'] >= $filters['places_min'];
    });
  }

  // Enrichissement avec données utilisateur (optimisé avec pré-chargement)
  $users = DB::findAll('utilisateurs');
  $usersById = [];
  foreach ($users as $user) {
    $usersById[$user['id']] = $user;
  }

  foreach ($filteredTrajets as &$trajet) {
    $chauffeur = $usersById[$trajet['chauffeur_id']] ?? null;
    if ($chauffeur) {
      $trajet['chauffeur'] = [
        'id' => $chauffeur['id'],
        'nom' => $chauffeur['nom'],
        'prenom' => $chauffeur['prenom'],
        'note_moyenne' => $chauffeur['note_moyenne'] ?? 0,
        'niveau_reputation' => $chauffeur['niveau_reputation'] ?? 'nouveau'
      ];
    }
  }

  // Tri optimisé
  $sortBy = $filters['sort_by'] ?? 'date_depart';
  $sortOrder = $filters['sort_order'] ?? 'asc';

  usort($filteredTrajets, function ($a, $b) use ($sortBy, $sortOrder) {
    $valueA = $a[$sortBy] ?? '';
    $valueB = $b[$sortBy] ?? '';

    if (in_array($sortBy, ['date_depart', 'prix_par_place', 'places_disponibles'])) {
      $result = $valueA <=> $valueB;
    } else {
      $result = strcasecmp($valueA, $valueB);
    }

    return $sortOrder === 'desc' ? -$result : $result;
  });

  $endTime = microtime(true);
  $executionTime = round(($endTime - $startTime) * 1000, 2);

  $result = [
    'trajets' => array_values($filteredTrajets),
    'total_count' => count($filteredTrajets),
    'execution_time_ms' => $executionTime,
    'filters_applied' => $filters
  ];

  // Mettre en cache pour 10 minutes
  CacheManager::set($cacheKey, $result, 600);

  return [
    'data' => $result,
    'cached' => false,
    'cache_key' => $cacheKey
  ];
}

/**
 * Optimisation des calculs de réputation
 */
function optimizeReputationCalculation($userId = null)
{
  $cacheKey = $userId ? "reputation_user_$userId" : 'reputation_global';

  // Vérifier le cache
  $cachedResult = CacheManager::get($cacheKey);
  if ($cachedResult !== null) {
    return [
      'data' => $cachedResult,
      'cached' => true
    ];
  }

  $startTime = microtime(true);

  if ($userId) {
    // Calcul pour un utilisateur spécifique
    $user = DB::findById('utilisateurs', $userId);
    if (!$user) {
      return ['error' => 'Utilisateur non trouvé'];
    }

    $avis = DB::findAll('avis', ['evalue_id' => $userId, 'valide' => true]);

    if (empty($avis)) {
      $result = [
        'user_id' => $userId,
        'score_reputation' => 0,
        'niveau' => 'nouveau',
        'total_avis' => 0,
        'note_moyenne' => 0
      ];
    } else {
      $notesMoyenne = array_sum(array_column($avis, 'note')) / count($avis);
      $scoreReputation = calculateAdvancedScore($avis, $notesMoyenne);

      $result = [
        'user_id' => $userId,
        'score_reputation' => $scoreReputation,
        'niveau' => determineReputationLevel($scoreReputation),
        'total_avis' => count($avis),
        'note_moyenne' => round($notesMoyenne, 2),
        'avis_chauffeur' => count(array_filter($avis, fn($a) => $a['type'] === 'chauffeur')),
        'avis_passager' => count(array_filter($avis, fn($a) => $a['type'] === 'passager'))
      ];
    }
  } else {
    // Calcul global pour tous les utilisateurs
    $users = DB::findAll('utilisateurs');
    $allAvis = DB::findAll('avis', ['valide' => true]);

    $userScores = [];
    $avisParUtilisateur = [];

    // Grouper les avis par utilisateur
    foreach ($allAvis as $avis) {
      $evalueId = $avis['evalue_id'];
      if (!isset($avisParUtilisateur[$evalueId])) {
        $avisParUtilisateur[$evalueId] = [];
      }
      $avisParUtilisateur[$evalueId][] = $avis;
    }

    // Calculer les scores
    foreach ($users as $user) {
      $userId = $user['id'];
      $userAvis = $avisParUtilisateur[$userId] ?? [];

      if (!empty($userAvis)) {
        $notesMoyenne = array_sum(array_column($userAvis, 'note')) / count($userAvis);
        $scoreReputation = calculateAdvancedScore($userAvis, $notesMoyenne);

        $userScores[] = [
          'user_id' => $userId,
          'nom' => $user['nom'],
          'prenom' => $user['prenom'],
          'score_reputation' => $scoreReputation,
          'niveau' => determineReputationLevel($scoreReputation),
          'total_avis' => count($userAvis),
          'note_moyenne' => round($notesMoyenne, 2)
        ];
      }
    }

    // Trier par score
    usort($userScores, function ($a, $b) {
      return $b['score_reputation'] <=> $a['score_reputation'];
    });

    $result = [
      'classement_global' => $userScores,
      'total_utilisateurs_classes' => count($userScores),
      'statistiques' => [
        'score_moyen' => count($userScores) > 0 ? round(array_sum(array_column($userScores, 'score_reputation')) / count($userScores), 2) : 0,
        'score_max' => count($userScores) > 0 ? max(array_column($userScores, 'score_reputation')) : 0,
        'score_min' => count($userScores) > 0 ? min(array_column($userScores, 'score_reputation')) : 0
      ]
    ];
  }

  $endTime = microtime(true);
  $result['execution_time_ms'] = round(($endTime - $startTime) * 1000, 2);

  // Cache pour 1 heure
  CacheManager::set($cacheKey, $result, 3600);

  return [
    'data' => $result,
    'cached' => false
  ];
}

/**
 * Calcul avancé du score de réputation
 */
function calculateAdvancedScore($avis, $notesMoyenne)
{
  $nombreAvis = count($avis);

  // Score de base (sur 1000)
  $score = ($notesMoyenne / 5) * 400; // 40% du score max

  // Bonus volume d'avis (30% du score max)
  $bonusVolume = min($nombreAvis * 6, 300);
  $score += $bonusVolume;

  // Bonus consistance (15% du score max)
  $notes = array_column($avis, 'note');
  $ecartType = $nombreAvis > 1 ? sqrt(array_sum(array_map(function ($note) use ($notesMoyenne) {
    return pow($note - $notesMoyenne, 2);
  }, $notes)) / $nombreAvis) : 0;

  $bonusConsistance = max(0, 150 - ($ecartType * 50));
  $score += $bonusConsistance;

  // Bonus récence (15% du score max)
  $avisRecents = array_filter($avis, function ($a) {
    return strtotime($a['date_creation']) > strtotime('-6 months');
  });

  $ratioRecence = count($avisRecents) / $nombreAvis;
  $bonusRecence = $ratioRecence * 150;
  $score += $bonusRecence;

  return round(min(1000, max(0, $score)));
}

/**
 * Détermination du niveau de réputation
 */
function determineReputationLevel($score)
{
  if ($score >= 900) return 'legendaire';
  if ($score >= 800) return 'excellent';
  if ($score >= 600) return 'tres_bon';
  if ($score >= 400) return 'bon';
  if ($score >= 200) return 'moyen';
  if ($score >= 100) return 'faible';
  return 'nouveau';
}

/**
 * Optimisation des statistiques du tableau de bord
 */
function optimizeDashboardStats()
{
  $cacheKey = 'dashboard_stats';

  // Vérifier le cache
  $cachedResult = CacheManager::get($cacheKey);
  if ($cachedResult !== null) {
    return [
      'data' => $cachedResult,
      'cached' => true
    ];
  }

  $startTime = microtime(true);

  // Récupération optimisée des données
  $users = DB::findAll('utilisateurs');
  $trajets = DB::findAll('trajets');
  $participations = DB::findAll('participations');
  $avis = DB::findAll('avis');
  $incidents = DB::findAll('incidents');

  // Calculs optimisés
  $stats = [
    'utilisateurs' => [
      'total' => count($users),
      'actifs' => count(array_filter($users, fn($u) => $u['statut'] === 'actif')),
      'nouveaux_ce_mois' => count(array_filter($users, function ($u) {
        return strtotime($u['date_creation']) >= strtotime('first day of this month');
      }))
    ],
    'trajets' => [
      'total' => count($trajets),
      'actifs' => count(array_filter($trajets, fn($t) => $t['statut'] === 'actif')),
      'termines' => count(array_filter($trajets, fn($t) => $t['statut'] === 'termine')),
      'ce_mois' => count(array_filter($trajets, function ($t) {
        return strtotime($t['date_creation']) >= strtotime('first day of this month');
      }))
    ],
    'participations' => [
      'total' => count($participations),
      'confirmees' => count(array_filter($participations, fn($p) => $p['statut'] === 'confirmee')),
      'en_attente' => count(array_filter($participations, fn($p) => $p['statut'] === 'en_attente'))
    ],
    'avis' => [
      'total' => count($avis),
      'valides' => count(array_filter($avis, fn($a) => $a['valide'])),
      'note_moyenne_plateforme' => 0
    ],
    'incidents' => [
      'total' => count($incidents),
      'ouverts' => count(array_filter($incidents, fn($i) => $i['statut'] === 'ouvert')),
      'resolus' => count(array_filter($incidents, fn($i) => $i['statut'] === 'resolu'))
    ]
  ];

  // Calcul de la note moyenne
  $avisValides = array_filter($avis, fn($a) => $a['valide']);
  if (!empty($avisValides)) {
    $stats['avis']['note_moyenne_plateforme'] = round(
      array_sum(array_column($avisValides, 'note')) / count($avisValides),
      2
    );
  }

  // Statistiques d'évolution (7 derniers jours)
  $stats['evolution'] = [];
  for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateStart = strtotime($date . ' 00:00:00');
    $dateEnd = strtotime($date . ' 23:59:59');

    $stats['evolution'][] = [
      'date' => $date,
      'nouveaux_trajets' => count(array_filter($trajets, function ($t) use ($dateStart, $dateEnd) {
        $created = strtotime($t['date_creation']);
        return $created >= $dateStart && $created <= $dateEnd;
      })),
      'nouveaux_utilisateurs' => count(array_filter($users, function ($u) use ($dateStart, $dateEnd) {
        $created = strtotime($u['date_creation']);
        return $created >= $dateStart && $created <= $dateEnd;
      }))
    ];
  }

  $endTime = microtime(true);
  $stats['meta'] = [
    'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
    'generated_at' => date('Y-m-d H:i:s')
  ];

  // Cache pour 15 minutes
  CacheManager::set($cacheKey, $stats, 900);

  return [
    'data' => $stats,
    'cached' => false
  ];
}

// Traitement de la requête
try {
  $action = $_GET['action'] ?? 'dashboard';

  switch ($action) {
    case 'dashboard':
      $result = optimizeDashboardStats();
      echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'cached' => $result['cached']
      ]);
      break;

    case 'search_trajets':
      $filters = [
        'statut' => $_GET['statut'] ?? '',
        'depart' => $_GET['depart'] ?? '',
        'arrivee' => $_GET['arrivee'] ?? '',
        'date_depart' => $_GET['date_depart'] ?? '',
        'prix_max' => $_GET['prix_max'] ?? '',
        'places_min' => $_GET['places_min'] ?? '',
        'sort_by' => $_GET['sort_by'] ?? 'date_depart',
        'sort_order' => $_GET['sort_order'] ?? 'asc'
      ];

      // Supprimer les filtres vides
      $filters = array_filter($filters);

      $result = optimizeTrajetSearch($filters);
      echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'cached' => $result['cached']
      ]);
      break;

    case 'reputation':
      $userId = $_GET['user_id'] ?? null;
      $result = optimizeReputationCalculation($userId);

      if (isset($result['error'])) {
        http_response_code(404);
        echo json_encode($result);
      } else {
        echo json_encode([
          'success' => true,
          'data' => $result['data'],
          'cached' => $result['cached']
        ]);
      }
      break;

    case 'cache_stats':
      $stats = CacheManager::getStats();
      echo json_encode([
        'success' => true,
        'data' => $stats
      ]);
      break;

    case 'cache_cleanup':
      // Vérification des permissions admin
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

      $cleaned = CacheManager::cleanup();
      echo json_encode([
        'success' => true,
        'data' => [
          'items_cleaned' => $cleaned,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      ]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Action non reconnue']);
  }
} catch (Exception $e) {
  error_log("Erreur API Optimisation: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur interne du serveur']);
}

// Traitement des requêtes DELETE pour vider le cache
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  try {
    // Vérification des permissions admin
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

    $action = $_GET['action'] ?? 'clear_all';

    if ($action === 'clear_all') {
      $cleared = CacheManager::clear();
      echo json_encode([
        'success' => true,
        'data' => [
          'items_cleared' => $cleared,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      ]);
    } elseif ($action === 'clear_key' && isset($_GET['key'])) {
      $deleted = CacheManager::delete($_GET['key']);
      echo json_encode([
        'success' => true,
        'data' => [
          'key_deleted' => $_GET['key'],
          'deleted' => $deleted,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      ]);
    } else {
      http_response_code(400);
      echo json_encode(['error' => 'Action ou paramètres invalides']);
    }
  } catch (Exception $e) {
    error_log("Erreur suppression cache: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur']);
  }
}
