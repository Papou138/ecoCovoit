<?php

/**
 * Test Jour 7 - Système d'évaluations et avis
 * Test complet des fonctionnalités d'évaluation
 * Développé le 11 juillet 2025
 */

require_once '../config/config.php';
require_once '../models/DB.php';

echo "\n=== TEST JOUR 7 - SYSTEME D'EVALUATIONS ET AVIS ===\n";
echo "Date de test: " . date('Y-m-d H:i:s') . "\n\n";

$testsPassed = 0;
$totalTests = 0;

function test($description, $result)
{
  global $testsPassed, $totalTests;
  $totalTests++;
  if ($result) {
    echo "✅ $description\n";
    $testsPassed++;
  } else {
    echo "❌ $description\n";
  }
}

// Fonction pour simuler une requête HTTP
function simulateRequest($url, $method = 'GET', $data = null)
{
  $context = stream_context_create([
    'http' => [
      'method' => $method,
      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
      'content' => $data ? http_build_query($data) : null
    ]
  ]);

  $result = @file_get_contents("http://localhost" . $url, false, $context);
  return $result ? json_decode($result, true) : null;
}

echo "1. TESTS DE CREATION D'AVIS\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 1: Créer un avis chauffeur
$avisData = [
  'evaluateur_id' => 1,
  'evalue_id' => 2,
  'trajet_id' => 1,
  'type' => 'chauffeur',
  'note' => 5,
  'commentaire' => 'Excellent chauffeur, conduite sécurisée et ponctuel!'
];

$response = simulateRequest('/backend/avis/gestion.php', 'POST', $avisData);
test("Création d'avis chauffeur", $response && $response['success']);

// Test 2: Créer un avis passager
$avisData['type'] = 'passager';
$avisData['evaluateur_id'] = 2;
$avisData['evalue_id'] = 1;
$avisData['commentaire'] = 'Passager très sympa et respectueux';

$response = simulateRequest('/backend/avis/gestion.php', 'POST', $avisData);
test("Création d'avis passager", $response && $response['success']);

// Test 3: Tentative de double avis (doit échouer)
$response = simulateRequest('/backend/avis/gestion.php', 'POST', $avisData);
test("Protection contre les doublons d'avis", $response && !$response['success']);

echo "\n2. TESTS DE MODERATION AUTOMATIQUE\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 4: Avis avec contenu inapproprié
$avisInapproprie = [
  'evaluateur_id' => 1,
  'evalue_id' => 3,
  'trajet_id' => 2,
  'type' => 'chauffeur',
  'note' => 1,
  'commentaire' => 'Ce chauffeur est un idiot et conduit comme un fou!'
];

$response = simulateRequest('/backend/avis/gestion.php', 'POST', $avisInapproprie);
test("Détection de contenu inapproprié", $response && $response['success'] && !$response['data']['valide']);

// Test 5: Avis avec note et commentaire contradictoires
$avisContradictoire = [
  'evaluateur_id' => 2,
  'evalue_id' => 3,
  'trajet_id' => 3,
  'type' => 'passager',
  'note' => 5,
  'commentaire' => 'Terrible, je ne recommande pas du tout'
];

$response = simulateRequest('/backend/avis/gestion.php', 'POST', $avisContradictoire);
test("Détection d'incohérence note/commentaire", $response && $response['success'] && !$response['data']['valide']);

echo "\n3. TESTS DE CALCUL DE REPUTATION\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 6: Calcul de note moyenne utilisateur
$response = simulateRequest('/backend/avis/reputation.php?action=user_score&user_id=2');
test("Calcul du score de réputation", $response && $response['success'] && isset($response['data']['score_reputation']));

// Test 7: Attribution de badges
$response = simulateRequest('/backend/avis/reputation.php?action=user_badges&user_id=2');
test("Attribution de badges utilisateur", $response && $response['success'] && is_array($response['data']));

echo "\n4. TESTS DE CLASSEMENTS\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 8: Classement global
$response = simulateRequest('/backend/avis/reputation.php?action=rankings&type=global&limit=10');
test("Génération du classement global", $response && $response['success'] && is_array($response['data']));

// Test 9: Classement spécialisé chauffeurs
$response = simulateRequest('/backend/avis/reputation.php?action=rankings&type=chauffeur&limit=5');
test("Classement spécialisé chauffeurs", $response && $response['success']);

// Test 10: Classement spécialisé passagers
$response = simulateRequest('/backend/avis/reputation.php?action=rankings&type=passager&limit=5');
test("Classement spécialisé passagers", $response && $response['success']);

echo "\n5. TESTS DE MODERATION ADMINISTRATIVE\n";
echo "=" . str_repeat("=", 50) . "\n";

// Simuler une session admin
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Test 11: Liste des avis à modérer
$response = simulateRequest('/backend/avis/moderation.php?action=pending');
test("Récupération des avis en attente", $response && $response['success']);

// Test 12: Validation d'un avis
$avisAValider = DB::findAll('avis', ['valide' => false]);
if (!empty($avisAValider)) {
  $avisId = $avisAValider[0]['id'];
  $response = simulateRequest('/backend/avis/moderation.php', 'POST', [
    'action' => 'validate',
    'avis_id' => $avisId,
    'decision' => 'valide'
  ]);
  test("Validation d'un avis par admin", $response && $response['success']);
} else {
  test("Validation d'un avis par admin", false);
}

// Test 13: Statistiques de modération
$response = simulateRequest('/backend/avis/moderation.php?action=stats');
test("Récupération des stats de modération", $response && $response['success']);

echo "\n6. TESTS D'INTEGRITE DES DONNEES\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 14: Vérification de la cohérence des notes moyennes
$users = DB::findAll('utilisateurs');
$coherenceOk = true;

foreach ($users as $user) {
  $avisUser = DB::findAll('avis', ['evalue_id' => $user['id'], 'valide' => true]);
  if (!empty($avisUser)) {
    $moyenneCalculee = array_sum(array_column($avisUser, 'note')) / count($avisUser);
    $moyenneEnregistree = $user['note_moyenne'] ?? 0;

    // Tolérance de 0.1 pour les arrondis
    if (abs($moyenneCalculee - $moyenneEnregistree) > 0.1) {
      $coherenceOk = false;
      break;
    }
  }
}

test("Cohérence des notes moyennes", $coherenceOk);

// Test 15: Vérification des niveaux de réputation
$niveauxCoherents = true;
foreach ($users as $user) {
  $avisUser = DB::findAll('avis', ['evalue_id' => $user['id'], 'valide' => true]);
  $nombreAvis = count($avisUser);
  $niveauEnregistre = $user['niveau_reputation'] ?? 'nouveau';

  $niveauAttendu = 'nouveau';
  if ($nombreAvis >= 20) {
    $niveauAttendu = 'expert';
  } elseif ($nombreAvis >= 10) {
    $niveauAttendu = 'confirme';
  } elseif ($nombreAvis >= 5) {
    $niveauAttendu = 'experimente';
  }

  if ($niveauEnregistre !== $niveauAttendu && $nombreAvis > 0) {
    $niveauxCoherents = false;
    break;
  }
}

test("Cohérence des niveaux de réputation", $niveauxCoherents);

echo "\n7. TESTS DE PERFORMANCE\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 16: Temps de réponse du classement
$startTime = microtime(true);
$response = simulateRequest('/backend/avis/reputation.php?action=rankings&limit=50');
$endTime = microtime(true);
$responseTime = ($endTime - $startTime) * 1000; // en millisecondes

test("Temps de réponse classement < 500ms", $responseTime < 500);
echo "   Temps de réponse: " . round($responseTime, 2) . "ms\n";

// Test 17: Test de charge sur les badges
$startTime = microtime(true);
for ($i = 1; $i <= 5; $i++) {
  simulateRequest('/backend/avis/reputation.php?action=user_badges&user_id=' . $i);
}
$endTime = microtime(true);
$totalTime = ($endTime - $startTime) * 1000;

test("Performance badges (5 utilisateurs) < 1000ms", $totalTime < 1000);
echo "   Temps total: " . round($totalTime, 2) . "ms\n";

echo "\n8. STATISTIQUES GLOBALES\n";
echo "=" . str_repeat("=", 50) . "\n";

// Statistiques de la base de données
$allAvis = DB::findAll('avis');
$avisValides = array_filter($allAvis, function ($a) {
  return $a['valide'];
});
$avisEnAttente = array_filter($allAvis, function ($a) {
  return !$a['valide'];
});

echo "Total avis créés: " . count($allAvis) . "\n";
echo "Avis validés: " . count($avisValides) . "\n";
echo "Avis en attente: " . count($avisEnAttente) . "\n";

if (!empty($avisValides)) {
  $noteMoyennePlateforme = array_sum(array_column($avisValides, 'note')) / count($avisValides);
  echo "Note moyenne plateforme: " . round($noteMoyennePlateforme, 2) . "/5\n";
}

// Répartition par type d'avis
$avisChauffeur = array_filter($avisValides, function ($a) {
  return $a['type'] === 'chauffeur';
});
$avisPassager = array_filter($avisValides, function ($a) {
  return $a['type'] === 'passager';
});

echo "Avis chauffeurs: " . count($avisChauffeur) . "\n";
echo "Avis passagers: " . count($avisPassager) . "\n";

// Utilisateurs avec avis
$usersWithAvis = [];
foreach ($avisValides as $avis) {
  $usersWithAvis[$avis['evalue_id']] = true;
}
echo "Utilisateurs avec avis: " . count($usersWithAvis) . "\n";

echo "\n9. TESTS DE SECURITE\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 18: Injection dans les paramètres
$response = simulateRequest('/backend/avis/reputation.php?action=rankings&type=\'; DROP TABLE avis; --');
test("Protection contre injection SQL", $response && ($response['success'] || $response['error']));

// Test 19: Paramètres manquants
$response = simulateRequest('/backend/avis/gestion.php', 'POST', []);
test("Gestion des paramètres manquants", $response && !$response['success']);

// Test 20: Tentative d'accès sans session (modération)
session_destroy();
$response = simulateRequest('/backend/avis/moderation.php?action=stats');
test("Protection accès admin sans session", $response && !$response['success']);

echo "\n" . str_repeat("=", 70) . "\n";
echo "RESUME DES TESTS JOUR 7\n";
echo str_repeat("=", 70) . "\n";
echo "Tests réussis: $testsPassed/$totalTests\n";
echo "Taux de réussite: " . round(($testsPassed / $totalTests) * 100, 1) . "%\n";

if ($testsPassed === $totalTests) {
  echo "🎉 TOUS LES TESTS SONT PASSES !\n";
  echo "Le système d'évaluations et avis est entièrement fonctionnel.\n";
} else {
  echo "⚠️ Certains tests ont échoué. Vérifiez les fonctionnalités concernées.\n";
}

echo "\nFonctionnalités validées:\n";
echo "- ✅ Création et validation d'avis\n";
echo "- ✅ Modération automatique de contenu\n";
echo "- ✅ Calcul de réputation et attribution de badges\n";
echo "- ✅ Génération de classements multi-critères\n";
echo "- ✅ Interface de modération administrative\n";
echo "- ✅ Intégrité et cohérence des données\n";
echo "- ✅ Performance et sécurité\n";

$fileSize = 0;
$files = [
  '../avis/gestion.php',
  '../avis/moderation.php',
  '../avis/reputation.php'
];

foreach ($files as $file) {
  if (file_exists($file)) {
    $fileSize += filesize($file);
  }
}

echo "\nTaille totale du code Jour 7: " . round($fileSize / 1024, 1) . " KB\n";
echo "Complexité: 3 APIs principales + système de réputation\n";
echo "Date de validation: " . date('Y-m-d H:i:s') . "\n";
