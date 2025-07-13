#!/usr/bin/env php
<?php

/**
 * Test final de validation pour ecoCovoit
 * Vérification des fonctionnalités critiques avant déploiement
 */

echo "🔬 TEST FINAL DE VALIDATION - ecoCovoit\n";
echo "======================================\n\n";

$results = [
  'total' => 0,
  'passed' => 0,
  'failed' => 0,
  'critical_issues' => []
];

function testResult($test_name, $success, $message = '', $critical = false)
{
  global $results;

  $results['total']++;
  $icon = $success ? '✅' : '❌';

  if ($success) {
    $results['passed']++;
    echo "$icon $test_name: OK";
    if ($message) echo " - $message";
    echo "\n";
  } else {
    $results['failed']++;
    echo "$icon $test_name: ÉCHEC";
    if ($message) echo " - $message";
    echo "\n";

    if ($critical) {
      $results['critical_issues'][] = $test_name;
    }
  }
}

// 1. Test de la structure des fichiers
echo "1. STRUCTURE DES FICHIERS\n";
echo "------------------------\n";

$critical_files = [
  'backend/config/config.php' => 'Configuration principale',
  'backend/models/DB.php' => 'Modèle de base de données',
  'backend/auth/login.php' => 'API de connexion',
  'backend/auth/register.php' => 'API d\'inscription',
  'backend/trajets/create.php' => 'API création trajets',
  'backend/trajets/rechercher.php' => 'API recherche trajets',
  'frontend/index.html' => 'Page d\'accueil',
  'frontend/assets/js/auth.js' => 'JavaScript authentification'
];

foreach ($critical_files as $file => $description) {
  $exists = file_exists(__DIR__ . '/' . $file);
  testResult($description, $exists, $file, true);
}

// 2. Test des données JSON
echo "\n2. INTÉGRITÉ DES DONNÉES\n";
echo "-----------------------\n";

$json_files = [
  'backend/data/utilisateurs.json' => 'Utilisateurs',
  'backend/data/trajets.json' => 'Trajets',
  'backend/data/participations.json' => 'Participations',
  'backend/data/avis.json' => 'Avis',
  'backend/data/notifications.json' => 'Notifications'
];

foreach ($json_files as $file => $description) {
  if (file_exists(__DIR__ . '/' . $file)) {
    $content = file_get_contents(__DIR__ . '/' . $file);
    $data = json_decode($content, true);
    $valid = $data !== null && is_array($data);
    $count = $valid ? count($data) : 0;
    testResult($description, $valid, "$count enregistrements", false);
  } else {
    testResult($description, false, "Fichier manquant", false);
  }
}

// 3. Test de connectivité des serveurs
echo "\n3. CONNECTIVITÉ SERVEURS\n";
echo "-----------------------\n";

function testURL($url, $description)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  curl_setopt($ch, CURLOPT_NOBODY, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    testResult($description, false, "Erreur: $error", true);
  } else {
    $success = $httpCode >= 200 && $httpCode < 400;
    testResult($description, $success, "HTTP $httpCode", $httpCode >= 500);
  }
}

testURL('http://localhost:8080/index.html', 'Frontend (port 8080)');
testURL('http://localhost:8000/index.php', 'Backend (port 8000)');

// 4. Test des APIs critiques
echo "\n4. APIs CRITIQUES\n";
echo "----------------\n";

// Test API de configuration
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/system/config.php?action=config');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = $httpCode === 200;
$data = $success ? json_decode($response, true) : null;
testResult('API Configuration', $success, $success ? "Réponse valide" : "HTTP $httpCode", true);

// Test API de monitoring
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/system/monitoring.php?action=health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = $httpCode === 200;
testResult('API Monitoring', $success, $success ? "Système opérationnel" : "HTTP $httpCode", true);

// Test API d'authentification
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/auth/register.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'pseudo' => 'test_validation_' . time(),
  'email' => 'validation_' . time() . '@test.com',
  'password' => 'TestPassword123!',
  'confirm_password' => 'TestPassword123!'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = $httpCode === 200;
$data = $success ? json_decode($response, true) : null;
$registration_works = $success && isset($data['success']) && $data['success'];
testResult('API Inscription', $registration_works, $registration_works ? "Inscription fonctionnelle" : "Erreur inscription", true);

// 5. Test de cohérence des données
echo "\n5. COHÉRENCE DES DONNÉES\n";
echo "-----------------------\n";

// Vérifier que les IDs sont cohérents entre les fichiers
$users_file = __DIR__ . '/backend/data/utilisateurs.json';
$trajets_file = __DIR__ . '/backend/data/trajets.json';

if (file_exists($users_file) && file_exists($trajets_file)) {
  $users = json_decode(file_get_contents($users_file), true);
  $trajets = json_decode(file_get_contents($trajets_file), true);

  $user_ids = array_column($users, 'id');
  $chauffeur_ids = array_column($trajets, 'chauffeur_id');

  $orphaned_trajets = array_diff($chauffeur_ids, $user_ids);
  $coherent = empty($orphaned_trajets);

  testResult(
    'Cohérence utilisateurs-trajets',
    $coherent,
    $coherent ? "Toutes les références sont valides" : count($orphaned_trajets) . " trajets orphelins",
    false
  );
}

// 6. Test de sécurité basique
echo "\n6. SÉCURITÉ BASIQUE\n";
echo "------------------\n";

// Vérifier que les APIs sensibles nécessitent une authentification
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/admin/dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$protected = $httpCode === 401;
testResult('Protection admin', $protected, $protected ? "Authentification requise" : "Non protégé", !$protected);

// Vérifier la présence de headers CORS
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/system/config.php?action=config');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$has_cors = strpos($response, 'Access-Control-Allow-Origin') !== false;
testResult('Headers CORS', $has_cors, $has_cors ? "CORS configuré" : "CORS manquant", false);

// 7. Résumé final
echo "\n" . str_repeat("=", 50) . "\n";
echo "RÉSUMÉ FINAL\n";
echo str_repeat("=", 50) . "\n";

$success_rate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;

echo "Total des tests: {$results['total']}\n";
echo "Tests réussis: {$results['passed']} ({$success_rate}%)\n";
echo "Tests échoués: {$results['failed']}\n";

if (!empty($results['critical_issues'])) {
  echo "\n🚨 PROBLÈMES CRITIQUES:\n";
  foreach ($results['critical_issues'] as $issue) {
    echo "   - $issue\n";
  }
}

if ($success_rate >= 90) {
  echo "\n🎉 STATUT: EXCELLENT - Prêt pour la production\n";
} elseif ($success_rate >= 75) {
  echo "\n✅ STATUT: BON - Quelques améliorations nécessaires\n";
} elseif ($success_rate >= 60) {
  echo "\n⚠️ STATUT: MOYEN - Corrections importantes nécessaires\n";
} else {
  echo "\n❌ STATUT: CRITIQUE - Révision majeure requise\n";
}

echo "\n📊 Taux de réussite: $success_rate%\n";

if (empty($results['critical_issues']) && $success_rate >= 85) {
  echo "\n🚀 RECOMMANDATION: GO POUR LA PRODUCTION\n";
} else {
  echo "\n🔧 RECOMMANDATION: CORRECTIONS NÉCESSAIRES AVANT PRODUCTION\n";
}

echo "\nDate du test: " . date('Y-m-d H:i:s') . "\n";

?>
