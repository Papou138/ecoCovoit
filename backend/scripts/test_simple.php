<?php

/**
 * Test simple DatabaseMock
 */

require_once __DIR__ . '/../models/DatabaseMock.php';

echo "🧪 Test DatabaseMock simple\n";

try {
  // Test d'initialisation
  echo "📁 Initialisation...\n";
  DatabaseMock::init();

  // Test de chargement des utilisateurs
  echo "👥 Chargement des utilisateurs...\n";
  $users = DatabaseMock::loadData('utilisateurs');
  echo "Utilisateurs: " . count($users) . "\n";

  foreach ($users as $user) {
    echo "- {$user['pseudo']} ({$user['email']})\n";
  }

  echo "✅ Test réussi!\n";
} catch (Exception $e) {
  echo "❌ Erreur: " . $e->getMessage() . "\n";
  echo "Trace: " . $e->getTraceAsString() . "\n";
}
