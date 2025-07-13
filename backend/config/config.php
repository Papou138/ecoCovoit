<?php

/**
 * Configuration générale de l'application ecoCovoit
 *
 * Ce fichier contient toutes les configurations nécessaires
 * pour le bon fonctionnement de l'application
 */

// === CONFIGURATION BASE DE DONNÉES ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecoCovoit_SQL');
define('DB_USER', 'root');
define('DB_PASS', '');

// === CONFIGURATION MONGODB (pour les avis) ===
define('MONGO_URI', 'mongodb://localhost:27017');
define('MONGO_DB', 'ecoCovoit_NoSQL');

// === CONFIGURATION SÉCURITÉ ===
define('JWT_SECRET', 'votre_clé_secrète_très_longue_et_complexe_2025');
define('SESSION_LIFETIME', 3600); // 1 heure

// === CONFIGURATION APPLICATION ===
define('CREDITS_INITIAL', 20); // Crédits donnés à l'inscription
define('CREDITS_COMMISSION', 2); // Commission plateforme par trajet
define('MAX_UPLOAD_SIZE', 5242880); // 5MB pour les photos

// === CONFIGURATION EMAIL ===
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USER', 'admin@ecocovoit.fr');
define('SMTP_PASS', '');

// === URLS DE L'APPLICATION ===
define('BASE_URL', 'http://localhost:8080');
define('API_BASE_URL', 'http://localhost/ecoCovoit/backend/api');

// === GESTION DES ERREURS ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === TIMEZONE ===
date_default_timezone_set('Europe/Paris');

/**
 * Fonction utilitaire pour débugger
 */
function debug($data, $die = false)
{
  echo '<pre>';
  print_r($data);
  echo '</pre>';
  if ($die) die();
}

/**
 * Fonction pour générer des réponses JSON standardisées
 */
function jsonResponse($success = true, $message = '', $data = null, $code = 200)
{
  http_response_code($code);
  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');

  $response = [
    'success' => $success,
    'message' => $message,
    'timestamp' => date('Y-m-d H:i:s')
  ];

  if ($data !== null) {
    $response['data'] = $data;
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE);
  exit;
}
