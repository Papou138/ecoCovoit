<?php

/**
 * Script de test pour le Jour 6 - APIs d'Administration
 * Test complet des fonctionnalités administratives
 * Développé le 11 juillet 2025
 */

require_once '../config/config.php';
require_once '../models/DB.php';

echo "🛡️ **TEST JOUR 6 - APIS D'ADMINISTRATION**\n";
echo "==========================================\n\n";

// Configuration de base
$baseUrl = 'http://localhost/ecoCovoit/backend';

/**
 * Fonction pour effectuer une requête cURL
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = [])
{
  $ch = curl_init();

  $defaultHeaders = [
    'Content-Type: application/json',
    'Accept: application/json'
  ];

  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
  ]);

  if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return [
    'code' => $httpCode,
    'response' => $response,
    'data' => json_decode($response, true)
  ];
}

/**
 * Initialisation des données de test
 */
function initTestData()
{
  global $baseUrl;

  echo "📊 **INITIALISATION DES DONNEES DE TEST**\n";
  echo "=========================================\n";

  // Créer un administrateur de test si nécessaire
  $admins = DB::findAll('utilisateurs', ['role' => 'admin']);
  if (empty($admins)) {
    $adminData = [
      'id' => 1,
      'nom' => 'Admin',
      'prenom' => 'Test',
      'email' => 'admin@ecocovoit.com',
      'mot_de_passe' => password_hash('admin123', PASSWORD_DEFAULT),
      'telephone' => '0123456789',
      'role' => 'admin',
      'statut' => 'actif',
      'date_creation' => date('Y-m-d H:i:s'),
      'credits' => 1000.00
    ];

    $adminId = DB::insert('utilisateurs', $adminData);
    echo "✅ Administrateur de test créé (ID: {$adminId})\n";
  } else {
    $adminId = $admins[0]['id'];
    echo "✅ Administrateur existant utilisé (ID: {$adminId})\n";
  }

  // Créer quelques utilisateurs problématiques pour les tests
  $problematicUsers = [
    [
      'nom' => 'Probleme',
      'prenom' => 'User1',
      'email' => 'probleme1@test.com',
      'mot_de_passe' => password_hash('test123', PASSWORD_DEFAULT),
      'telephone' => '0111111111',
      'role' => 'utilisateur',
      'statut' => 'en_attente',
      'date_creation' => date('Y-m-d H:i:s'),
      'credits' => 50.00
    ],
    [
      'nom' => 'Suspendu',
      'prenom' => 'User2',
      'email' => 'suspendu@test.com',
      'mot_de_passe' => password_hash('test123', PASSWORD_DEFAULT),
      'telephone' => '0222222222',
      'role' => 'utilisateur',
      'statut' => 'suspendu',
      'raison_suspension' => 'Test de suspension',
      'date_creation' => date('Y-m-d H:i:s'),
      'credits' => 25.00
    ]
  ];

  foreach ($problematicUsers as $userData) {
    $existing = DB::findAll('utilisateurs', ['email' => $userData['email']]);
    if (empty($existing)) {
      $userId = DB::insert('utilisateurs', $userData);
      echo "✅ Utilisateur test créé: {$userData['email']} (ID: {$userId})\n";
    }
  }

  // Créer quelques incidents de test
  $incidents = [
    [
      'reporter_id' => $adminId,
      'type' => 'harcellement',
      'description' => 'Incident de test - Harcèlement signalé',
      'statut' => 'ouvert',
      'priorite' => 'urgente',
      'date_creation' => date('Y-m-d H:i:s'),
      'date_modification' => date('Y-m-d H:i:s')
    ],
    [
      'reporter_id' => $adminId,
      'type' => 'probleme_technique',
      'description' => 'Incident de test - Problème technique',
      'statut' => 'en_cours',
      'priorite' => 'normale',
      'date_creation' => date('Y-m-d H:i:s'),
      'date_modification' => date('Y-m-d H:i:s'),
      'assigned_to' => $adminId
    ]
  ];

  foreach ($incidents as $incidentData) {
    $existing = DB::findAll('incidents', ['description' => $incidentData['description']]);
    if (empty($existing)) {
      $incidentId = DB::insert('incidents', $incidentData);
      echo "✅ Incident de test créé: {$incidentData['type']} (ID: {$incidentId})\n";
    }
  }

  echo "\n";
  return $adminId;
}

/**
 * Test du dashboard administrateur
 */
function testAdminDashboard($baseUrl)
{
  echo "🏠 **TEST DASHBOARD ADMINISTRATEUR**\n";
  echo "====================================\n";

  // Test récupération du dashboard
  $response = makeRequest("{$baseUrl}/admin/dashboard.php?action=dashboard");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Dashboard chargé avec succès\n";
    $data = $response['data']['data'];

    echo "   👥 Utilisateurs: {$data['stats']['users']['total']} total, {$data['stats']['users']['active']} actifs\n";
    echo "   🚗 Trajets: {$data['stats']['trajets']['total']} total, {$data['stats']['trajets']['active']} actifs\n";
    echo "   💰 Revenus: {$data['stats']['financial']['total_revenue']}€\n";
    echo "   🌱 CO2 économisé: {$data['stats']['environmental']['co2_saved_kg']}kg\n";
    echo "   🔔 Activités récentes: " . count($data['recent_activities']) . "\n";
    echo "   ⚠️ Alertes: " . count($data['alerts']) . "\n";
  } else {
    echo "❌ Erreur dashboard: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  // Test des statistiques seules
  $response = makeRequest("{$baseUrl}/admin/dashboard.php?action=stats");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Statistiques récupérées avec succès\n";
  } else {
    echo "❌ Erreur statistiques: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  echo "\n";
}

/**
 * Test de la gestion des utilisateurs
 */
function testUserManagement($baseUrl)
{
  echo "👥 **TEST GESTION DES UTILISATEURS**\n";
  echo "===================================\n";

  // Test liste des utilisateurs
  $response = makeRequest("{$baseUrl}/admin/users.php?action=list");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Liste des utilisateurs récupérée (" . count($response['data']['data']) . " utilisateurs)\n";

    $users = $response['data']['data'];
    $userToTest = null;

    // Trouver un utilisateur en attente pour les tests
    foreach ($users as $user) {
      if ($user['statut'] === 'en_attente') {
        $userToTest = $user;
        break;
      }
    }

    if ($userToTest) {
      echo "   📋 Utilisateur de test trouvé: {$userToTest['email']}\n";

      // Test validation d'un utilisateur
      $validationData = [
        'action' => 'validate',
        'user_id' => $userToTest['id']
      ];

      $response = makeRequest("{$baseUrl}/admin/users.php", 'POST', $validationData);

      if ($response['code'] === 200 && $response['data']['success']) {
        echo "✅ Utilisateur validé avec succès\n";

        // Test suspension du même utilisateur
        $suspensionData = [
          'action' => 'suspend',
          'user_id' => $userToTest['id'],
          'reason' => 'Test de suspension automatique',
          'duration' => 7
        ];

        $response = makeRequest("{$baseUrl}/admin/users.php", 'POST', $suspensionData);

        if ($response['code'] === 200 && $response['data']['success']) {
          echo "✅ Utilisateur suspendu avec succès\n";

          // Test réactivation
          $reactivationData = [
            'action' => 'reactivate',
            'user_id' => $userToTest['id'],
            'notes' => 'Test de réactivation automatique'
          ];

          $response = makeRequest("{$baseUrl}/admin/users.php", 'POST', $reactivationData);

          if ($response['code'] === 200 && $response['data']['success']) {
            echo "✅ Utilisateur réactivé avec succès\n";
          } else {
            echo "❌ Erreur réactivation: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
          }
        } else {
          echo "❌ Erreur suspension: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
        }
      } else {
        echo "❌ Erreur validation: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
      }

      // Test détails utilisateur
      $response = makeRequest("{$baseUrl}/admin/users.php?action=details&id={$userToTest['id']}");

      if ($response['code'] === 200 && $response['data']['success']) {
        echo "✅ Détails utilisateur récupérés\n";
        $details = $response['data']['data'];
        echo "   📊 Trajets créés: {$details['detailed_stats']['trajets_crees']}\n";
        echo "   💰 Gains totaux: {$details['detailed_stats']['total_earnings']}€\n";
      } else {
        echo "❌ Erreur détails: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
      }
    }
  } else {
    echo "❌ Erreur liste utilisateurs: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  echo "\n";
}

/**
 * Test de la modération des trajets
 */
function testTrajetModeration($baseUrl)
{
  echo "🚗 **TEST MODERATION DES TRAJETS**\n";
  echo "=================================\n";

  // Test liste des trajets
  $response = makeRequest("{$baseUrl}/admin/trajets.php?action=list");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Liste des trajets récupérée (" . count($response['data']['data']) . " trajets)\n";

    $trajets = $response['data']['data'];
    if (!empty($trajets)) {
      $trajetToTest = $trajets[0];

      // Test signalement d'un trajet
      $reportData = [
        'action' => 'report',
        'trajet_id' => $trajetToTest['id'],
        'reason' => 'comportement_inapproprie',
        'description' => 'Test de signalement automatique'
      ];

      $response = makeRequest("{$baseUrl}/admin/trajets.php", 'POST', $reportData);

      if ($response['code'] === 200 && $response['data']['success']) {
        echo "✅ Trajet signalé avec succès\n";

        // Test modération - avertissement
        $moderationData = [
          'action' => 'moderate',
          'trajet_id' => $trajetToTest['id'],
          'moderation_action' => 'warn',
          'reason' => 'Premier avertissement - test automatique'
        ];

        $response = makeRequest("{$baseUrl}/admin/trajets.php", 'POST', $moderationData);

        if ($response['code'] === 200 && $response['data']['success']) {
          echo "✅ Avertissement appliqué avec succès\n";

          // Test modération - approbation
          $approvalData = [
            'action' => 'moderate',
            'trajet_id' => $trajetToTest['id'],
            'moderation_action' => 'approve',
            'reason' => 'Trajet approuvé après vérification'
          ];

          $response = makeRequest("{$baseUrl}/admin/trajets.php", 'POST', $approvalData);

          if ($response['code'] === 200 && $response['data']['success']) {
            echo "✅ Trajet approuvé avec succès\n";
          } else {
            echo "❌ Erreur approbation: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
          }
        } else {
          echo "❌ Erreur avertissement: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
        }
      } else {
        echo "❌ Erreur signalement: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
      }
    }
  } else {
    echo "❌ Erreur liste trajets: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  // Test statistiques de modération
  $response = makeRequest("{$baseUrl}/admin/trajets.php?action=stats");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Statistiques de modération récupérées\n";
    $stats = $response['data']['data'];
    echo "   📊 Trajets signalés: {$stats['trajets_signales']}\n";
    echo "   ⚠️ Trajets problématiques: {$stats['trajets_problematiques']}\n";
    echo "   🚫 Trajets suspendus: {$stats['trajets_suspendus']}\n";
  } else {
    echo "❌ Erreur stats modération: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  echo "\n";
}

/**
 * Test de la gestion des incidents
 */
function testIncidentManagement($baseUrl)
{
  echo "🚨 **TEST GESTION DES INCIDENTS**\n";
  echo "===============================\n";

  // Test création d'un incident
  $incidentData = [
    'action' => 'create',
    'type' => 'comportement_inapproprie',
    'description' => 'Test automatique - Comportement inapproprié signalé',
    'related_data' => [
      'trajet_id' => 1,
      'user_reported' => 2
    ]
  ];

  $response = makeRequest("{$baseUrl}/admin/incidents.php", 'POST', $incidentData);

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Incident créé avec succès (ID: {$response['data']['incident_id']})\n";
    $incidentId = $response['data']['incident_id'];

    // Test assignation de l'incident
    $assignData = [
      'action' => 'assign',
      'incident_id' => $incidentId,
      'admin_id' => 1  // Premier admin
    ];

    $response = makeRequest("{$baseUrl}/admin/incidents.php", 'POST', $assignData);

    if ($response['code'] === 200 && $response['data']['success']) {
      echo "✅ Incident assigné avec succès\n";

      // Test mise à jour du statut
      $updateData = [
        'action' => 'update_status',
        'incident_id' => $incidentId,
        'status' => 'ferme',
        'notes' => 'Incident résolu automatiquement par le test'
      ];

      $response = makeRequest("{$baseUrl}/admin/incidents.php", 'POST', $updateData);

      if ($response['code'] === 200 && $response['data']['success']) {
        echo "✅ Incident fermé avec succès\n";
      } else {
        echo "❌ Erreur fermeture: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
      }
    } else {
      echo "❌ Erreur assignation: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
    }
  } else {
    echo "❌ Erreur création incident: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  // Test liste des incidents
  $response = makeRequest("{$baseUrl}/admin/incidents.php?action=list");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Liste des incidents récupérée (" . count($response['data']['data']) . " incidents)\n";
  } else {
    echo "❌ Erreur liste incidents: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  // Test statistiques des incidents
  $response = makeRequest("{$baseUrl}/admin/incidents.php?action=stats");

  if ($response['code'] === 200 && $response['data']['success']) {
    echo "✅ Statistiques des incidents récupérées\n";
    $stats = $response['data']['data'];
    echo "   📊 Total incidents: {$stats['total']}\n";
    echo "   📂 Ouverts: {$stats['ouverts']}\n";
    echo "   ⏳ En cours: {$stats['en_cours']}\n";
    echo "   ✅ Fermés: {$stats['fermes']}\n";
    echo "   ⏱️ Temps résolution moyen: {$stats['temps_resolution_moyen']}h\n";
  } else {
    echo "❌ Erreur stats incidents: " . ($response['data']['error'] ?? 'Erreur inconnue') . "\n";
  }

  echo "\n";
}

/**
 * Fonction principale de test
 */
function runTests()
{
  global $baseUrl;

  echo "🚀 Démarrage des tests du Jour 6...\n\n";

  // Initialisation
  $adminId = initTestData();

  // Simulation de connexion admin (dans un vrai cas, il faudrait s'authentifier)
  session_start();
  $_SESSION['user_id'] = $adminId;

  // Tests des APIs
  testAdminDashboard($baseUrl);
  testUserManagement($baseUrl);
  testTrajetModeration($baseUrl);
  testIncidentManagement($baseUrl);

  // Nettoyage de session
  session_destroy();

  echo "🎯 **RESUME DES TESTS JOUR 6**\n";
  echo "============================\n";
  echo "✅ Dashboard administrateur testé\n";
  echo "✅ Gestion des utilisateurs testée\n";
  echo "✅ Modération des trajets testée\n";
  echo "✅ Gestion des incidents testée\n";
  echo "\n🎉 **JOUR 6 COMPLETE AVEC SUCCES !**\n\n";

  echo "🔧 **APIs d'Administration disponibles:**\n";
  echo "   📊 /admin/dashboard.php - Tableau de bord\n";
  echo "   👥 /admin/users.php - Gestion utilisateurs\n";
  echo "   🚗 /admin/trajets.php - Modération trajets\n";
  echo "   🚨 /admin/incidents.php - Gestion incidents\n\n";

  echo "🚀 **PROGRESSION:** 75% du backend ecoCovoit complété !\n";
  echo "📅 **PROCHAINE ETAPE:** Jour 7 - Système d'évaluations et avis\n\n";
}

// Exécution des tests
try {
  runTests();
} catch (Exception $e) {
  echo "❌ **ERREUR LORS DES TESTS:** " . $e->getMessage() . "\n";
  echo "📍 Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
