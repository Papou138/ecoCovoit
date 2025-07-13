<?php

/**
 * Test simple de connexion à la base de données
 */

echo "🔍 Test de configuration PHP et MySQL...\n\n";

// Vérification des extensions PHP
echo "📋 Extensions PHP disponibles:\n";
$extensions = ['pdo', 'pdo_mysql', 'mysqli', 'mysqlnd'];
foreach ($extensions as $ext) {
  $status = extension_loaded($ext) ? '✅' : '❌';
  echo "   $status $ext\n";
}

echo "\n";

// Test de connexion simple
try {
  echo "🔗 Test de connexion MySQL...\n";

  // Test avec mysqli d'abord
  if (extension_loaded('mysqli')) {
    $mysqli = new mysqli('localhost', 'root', '', 'mysql');
    if ($mysqli->connect_error) {
      echo "❌ Connexion mysqli échouée: " . $mysqli->connect_error . "\n";
    } else {
      echo "✅ Connexion mysqli réussie\n";
      $mysqli->close();
    }
  }

  // Test avec PDO
  if (extension_loaded('pdo_mysql')) {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "✅ Connexion PDO réussie\n";

    // Créer la base si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ecoCovoit_SQL");
    echo "✅ Base de données créée/vérifiée\n";
  } else {
    echo "❌ Extension pdo_mysql non disponible\n";
    echo "💡 Solution: Activez l'extension dans php.ini\n";
    echo "   Décommentez: extension=pdo_mysql\n";
  }
} catch (Exception $e) {
  echo "❌ Erreur: " . $e->getMessage() . "\n";
  echo "\n💡 Solutions possibles:\n";
  echo "   1. Vérifiez que MySQL/XAMPP est démarré\n";
  echo "   2. Vérifiez les identifiants de connexion\n";
  echo "   3. Activez l'extension pdo_mysql dans php.ini\n";
}

echo "\n🎯 Status: Prêt pour le développement des APIs\n";
