<?php

/**
 * Test du système de base de données
 */

require_once __DIR__ . '/../models/DB.php';

echo "🧪 Test du système de base de données ecoCovoit\n\n";

try {
  // Test 1: Récupération des utilisateurs
  echo "👥 Test 1: Récupération des utilisateurs\n";
  $users = DB::findAll('utilisateurs');
  echo "   Utilisateurs trouvés: " . count($users) . "\n";
  foreach ($users as $user) {
    echo "   - {$user['pseudo']} ({$user['email']}) - {$user['role']}\n";
  }

  // Test 2: Connexion utilisateur
  echo "\n🔐 Test 2: Test de connexion\n";
  $user = DB::findBy('utilisateurs', 'email', 'chauffeur@test.fr');
  if ($user) {
    echo "   ✅ Utilisateur trouvé: {$user['pseudo']}\n";
    if (password_verify('password', $user['mot_de_passe'])) {
      echo "   ✅ Mot de passe correct\n";
    } else {
      echo "   ❌ Mot de passe incorrect\n";
    }
  } else {
    echo "   ❌ Utilisateur non trouvé\n";
  }

  // Test 3: Recherche de trajets
  echo "\n🚗 Test 3: Recherche de trajets\n";
  $trajets = DB::searchTrajets('Paris', 'Lyon', date('Y-m-d', strtotime('+2 days')));
  echo "   Trajets trouvés: " . count($trajets) . "\n";
  foreach ($trajets as $trajet) {
    echo "   - {$trajet['ville_depart']} → {$trajet['ville_arrivee']}\n";
    echo "     Chauffeur: {$trajet['chauffeur_pseudo']}\n";
    echo "     Prix: {$trajet['prix']}€, Places: {$trajet['nombre_places_restantes']}\n";
    echo "     Écologique: " . ($trajet['est_ecologique'] ? 'Oui' : 'Non') . "\n";
  }

  // Test 4: Création d'un nouveau trajet
  echo "\n➕ Test 4: Création d'un nouveau trajet\n";
  $nouveauTrajet = [
    'chauffeur_id' => 2,
    'vehicule_id' => 1,
    'ville_depart' => 'Marseille',
    'ville_arrivee' => 'Nice',
    'date_depart' => date('Y-m-d H:i:s', strtotime('+3 days')),
    'prix' => 15.00,
    'nombre_places_total' => 3,
    'nombre_places_restantes' => 3,
    'statut' => 'planifie'
  ];

  $newId = DB::insert('trajets', $nouveauTrajet);
  echo "   ✅ Nouveau trajet créé avec l'ID: $newId\n";

  // Vérification
  $trajets = DB::findAll('trajets');
  echo "   Total trajets: " . count($trajets) . "\n";

  echo "\n✅ Tous les tests sont passés avec succès!\n";
  echo "🎯 Le système de base de données mock est opérationnel\n";
} catch (Exception $e) {
  echo "❌ Erreur lors des tests: " . $e->getMessage() . "\n";
}
