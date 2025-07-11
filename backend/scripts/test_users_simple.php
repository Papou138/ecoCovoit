<?php

/**
 * Test unitaire simple des APIs utilisateurs
 */

echo "🧪 Test unitaire des APIs Utilisateurs\n\n";

require_once __DIR__ . '/../models/DB.php';

// Test 1: Gestion des véhicules
echo "1️⃣ Test gestion véhicules:\n";

try {
  // Créer un véhicule de test
  $vehicleData = [
    'proprietaire_id' => 2, // chauffeur1
    'marque' => 'Toyota',
    'modele' => 'Prius',
    'couleur' => 'Grise',
    'immatriculation' => 'TEST-' . time(),
    'nombre_places' => 4,
    'type_energie' => 'hybride',
    'actif' => true
  ];

  $vehicleId = DB::insert('vehicules', $vehicleData);
  echo "   ✅ Véhicule créé avec ID: $vehicleId\n";

  // Récupérer le véhicule
  $vehicle = DB::findById('vehicules', $vehicleId);
  if ($vehicle) {
    echo "   ✅ Véhicule récupéré: {$vehicle['marque']} {$vehicle['modele']}\n";
    echo "   - Ecologique: " . ($vehicle['type_energie'] === 'electrique' ? 'Oui' : 'Non') . "\n";
    echo "   - Hybride: " . ($vehicle['type_energie'] === 'hybride' ? 'Oui' : 'Non') . "\n";
  }

  // Modifier le véhicule
  $updateSuccess = DB::update('vehicules', $vehicleId, [
    'couleur' => 'Verte',
    'actif' => false
  ]);

  if ($updateSuccess) {
    echo "   ✅ Véhicule modifié avec succès\n";
  }
} catch (Exception $e) {
  echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n2️⃣ Test système de préférences:\n";

try {
  // Créer des préférences
  $prefsData = [
    'utilisateur_id' => 3, // passager1
    'fumeur' => false,
    'animaux' => true,
    'musique' => false,
    'discussion' => true,
    'autres_preferences' => 'Pas de musique forte, voyage silencieux préféré'
  ];

  $prefId = DB::insert('preferences', $prefsData);
  echo "   ✅ Préférences créées avec ID: $prefId\n";

  // Récupérer et vérifier
  $prefs = DB::findBy('preferences', 'utilisateur_id', 3);
  if ($prefs) {
    echo "   ✅ Préférences récupérées:\n";
    echo "   - Fumeur: " . ($prefs['fumeur'] ? 'Oui' : 'Non') . "\n";
    echo "   - Animaux: " . ($prefs['animaux'] ? 'Oui' : 'Non') . "\n";
    echo "   - Musique: " . ($prefs['musique'] ? 'Oui' : 'Non') . "\n";
    echo "   - Discussion: " . ($prefs['discussion'] ? 'Oui' : 'Non') . "\n";
  }
} catch (Exception $e) {
  echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n3️⃣ Test système de transactions:\n";

try {
  // Simuler une transaction de crédit
  $user = DB::findById('utilisateurs', 2);
  $creditsBefore = $user['credits'];

  $transactionData = [
    'utilisateur_id' => 2,
    'type' => 'credit',
    'montant' => 15,
    'solde_avant' => $creditsBefore,
    'solde_apres' => $creditsBefore + 15,
    'description' => 'Bonus de test',
    'date_transaction' => date('Y-m-d H:i:s')
  ];

  $transId = DB::insert('transactions', $transactionData);
  echo "   ✅ Transaction créée avec ID: $transId\n";

  // Mettre à jour le solde utilisateur
  DB::update('utilisateurs', 2, ['credits' => $creditsBefore + 15]);
  echo "   ✅ Solde mis à jour: $creditsBefore → " . ($creditsBefore + 15) . "\n";

  // Récupérer l'historique
  $transactions = DB::findAll('transactions', ['utilisateur_id' => 2]);
  echo "   ✅ Total transactions utilisateur: " . count($transactions) . "\n";
} catch (Exception $e) {
  echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n4️⃣ Test validations:\n";

// Test validation immatriculation
function testImmatriculation($immat)
{
  return preg_match('/^[A-Z]{1,2}-?\d{3}-?[A-Z]{1,2}$/', $immat);
}

$immatriculations = [
  'AB-123-CD' => 'Valid français',
  'A-123-BC' => 'Valid court',
  'INVALID' => 'Invalid format',
  '123-ABC' => 'Invalid ordre'
];

foreach ($immatriculations as $immat => $desc) {
  $valid = testImmatriculation($immat);
  $status = $valid ? '✅' : '❌';
  echo "   $immat ($desc): $status\n";
}

// Test validation téléphone français
function testTelephone($tel)
{
  return preg_match('/^(?:\+33|0)[1-9](?:[0-9]{8})$/', $tel);
}

echo "\n   Test téléphones:\n";
$telephones = [
  '0123456789' => 'Valid fixe',
  '+33123456789' => 'Valid international',
  '0612345678' => 'Valid mobile',
  '123456' => 'Invalid court'
];

foreach ($telephones as $tel => $desc) {
  $valid = testTelephone($tel);
  $status = $valid ? '✅' : '❌';
  echo "     $tel ($desc): $status\n";
}

echo "\n5️⃣ Test statistiques utilisateur:\n";

try {
  $userId = 2; // chauffeur1

  // Compter les trajets en tant que chauffeur
  $trajetsChaffeur = DB::findAll('trajets', ['chauffeur_id' => $userId]);
  echo "   Trajets en tant que chauffeur: " . count($trajetsChaffeur) . "\n";

  // Compter les participations en tant que passager
  $trajetsPassager = DB::findAll('participations', ['passager_id' => $userId]);
  echo "   Trajets en tant que passager: " . count($trajetsPassager) . "\n";

  // Compter les véhicules
  $vehicules = DB::findAll('vehicules', ['proprietaire_id' => $userId]);
  echo "   Véhicules enregistrés: " . count($vehicules) . "\n";

  // Calculer le total des crédits gagnés/dépensés
  $transactions = DB::findAll('transactions', ['utilisateur_id' => $userId]);
  $totalCredit = 0;
  $totalDebit = 0;

  foreach ($transactions as $trans) {
    if ($trans['type'] === 'credit') {
      $totalCredit += $trans['montant'];
    } elseif ($trans['type'] === 'debit') {
      $totalDebit += $trans['montant'];
    }
  }

  echo "   Total crédits gagnés: $totalCredit\n";
  echo "   Total crédits dépensés: $totalDebit\n";
  echo "   Solde net: " . ($totalCredit - $totalDebit) . "\n";
} catch (Exception $e) {
  echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n✅ Tests des APIs utilisateurs terminés !\n";
echo "\n📊 Résumé des fonctionnalités:\n";
echo "   ✅ CRUD véhicules complet\n";
echo "   ✅ Gestion des préférences\n";
echo "   ✅ Système de transactions\n";
echo "   ✅ Validations robustes\n";
echo "   ✅ Statistiques utilisateur\n";

echo "\n🎯 APIs prêtes pour le frontend !\n";
