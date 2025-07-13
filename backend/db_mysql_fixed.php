<?php

/**
 * Configuration MySQL - OBSOLETE - Utilisez Database.php à la place
 *
 * Ce fichier est conservé pour compatibilité avec l'ancien code
 * mais la nouvelle classe Database dans models/Database.php est recommandée
 */

// Redirection vers la nouvelle classe
require_once __DIR__ . '/models/Database.php';

// Configuration héritée
$host = 'localhost';
$dbname = 'ecoCovoit_SQL';
$user = 'root';
$password = '';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  echo "Erreur de connexion : " . $e->getMessage();
}
