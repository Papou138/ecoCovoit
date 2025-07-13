<?php

/**
 * Script d'initialisation de la base de données
 *
 * A exécuter une seule fois pour créer toute la structure
 * Usage: php init_database.php
 */

require_once __DIR__ . '/../config/config.php';

echo "🚀 Initialisation de la base de données ecoCovoit...\n\n";

try {
  // Connexion directe pour créer la base
  $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  echo "✅ Connexion MySQL établie\n";

  // Lecture et exécution du fichier SQL
  $sqlFile = __DIR__ . '/../sql/structure.sql';

  if (!file_exists($sqlFile)) {
    throw new Exception("Fichier structure.sql introuvable");
  }

  $sql = file_get_contents($sqlFile);

  // Exécution des requêtes (séparées par point-virgule)
  $statements = explode(';', $sql);

  foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement) && !str_starts_with($statement, '--')) {
      try {
        $pdo->exec($statement);
      } catch (PDOException $e) {
        // Ignorer les erreurs de création de base existante
        if (!str_contains($e->getMessage(), 'database exists')) {
          echo "⚠️  Avertissement: " . $e->getMessage() . "\n";
        }
      }
    }
  }

  echo "✅ Structure de base créée avec succès\n";

  // Vérification des tables créées
  $pdo->exec("USE " . DB_NAME);
  $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

  echo "\n📊 Tables créées (" . count($tables) . "):\n";
  foreach ($tables as $table) {
    echo "   - $table\n";
  }

  // Vérification des données de test
  $adminCount = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'")->fetchColumn();
  echo "\n👤 Utilisateurs admin créés: $adminCount\n";

  $vehiculeCount = $pdo->query("SELECT COUNT(*) FROM vehicules")->fetchColumn();
  echo "🚗 Véhicules de test créés: $vehiculeCount\n";

  echo "\n🎉 Base de données initialisée avec succès!\n";
  echo "\n📝 Comptes de test créés:\n";
  echo "   - admin@ecocovoit.fr (Admin)\n";
  echo "   - employe@ecocovoit.fr (Employé)\n";
  echo "   - chauffeur@test.fr (Chauffeur)\n";
  echo "   - passager@test.fr (Passager)\n";
  echo "   - test@test.fr (Test général)\n";
  echo "\n🔑 Mot de passe pour tous: password\n";
} catch (Exception $e) {
  echo "❌ Erreur: " . $e->getMessage() . "\n";
  exit(1);
}
