<?php

/**
 * API de gestion des préférences utilisateur
 *
 * Endpoint: GET/POST /backend/users/preferences.php
 * - GET: Récupérer les préférences de l'utilisateur connecté
 * - POST: Créer/Mettre à jour les préférences
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $method = $_SERVER['REQUEST_METHOD'];

  switch ($method) {
    case 'GET':
      getUserPreferences();
      break;

    case 'POST':
      updateUserPreferences();
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur preferences API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}

/**
 * Récupère les préférences de l'utilisateur connecté
 */
function getUserPreferences()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupérer les préférences existantes
  $preferences = DB::findBy('preferences', 'utilisateur_id', $currentUser['id']);

  // Si pas de préférences, retourner les valeurs par défaut
  if (!$preferences) {
    $preferences = [
      'utilisateur_id' => $currentUser['id'],
      'fumeur' => false,
      'animaux' => false,
      'musique' => true,
      'discussion' => true,
      'autres_preferences' => null
    ];
  }

  AuthMiddleware::logAction('view_preferences');

  jsonResponse(true, 'Préférences récupérées avec succès', $preferences);
}

/**
 * Met à jour ou crée les préférences de l'utilisateur
 */
function updateUserPreferences()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupération des données
  $input = json_decode(file_get_contents('php://input'), true);

  // Validation des données booléennes
  $fumeur = isset($input['fumeur']) ? (bool)$input['fumeur'] : false;
  $animaux = isset($input['animaux']) ? (bool)$input['animaux'] : false;
  $musique = isset($input['musique']) ? (bool)$input['musique'] : true;
  $discussion = isset($input['discussion']) ? (bool)$input['discussion'] : true;

  // Validation des autres préférences
  $autresPreferences = $input['autres_preferences'] ?? '';
  if (!empty($autresPreferences)) {
    $autresPreferences = trim($autresPreferences);
    if (strlen($autresPreferences) > 500) {
      jsonResponse(false, 'Les autres préférences ne peuvent pas dépasser 500 caractères');
    }
  } else {
    $autresPreferences = null;
  }

  // Préparer les données
  $preferencesData = [
    'utilisateur_id' => $currentUser['id'],
    'fumeur' => $fumeur,
    'animaux' => $animaux,
    'musique' => $musique,
    'discussion' => $discussion,
    'autres_preferences' => $autresPreferences
  ];

  // Vérifier si les préférences existent déjà
  $existingPreferences = DB::findBy('preferences', 'utilisateur_id', $currentUser['id']);

  if ($existingPreferences) {
    // Mettre à jour
    $success = DB::update('preferences', $existingPreferences['id'], $preferencesData);
    $message = 'Préférences mises à jour avec succès';
  } else {
    // Créer
    $preferencesId = DB::insert('preferences', $preferencesData);
    $success = $preferencesId > 0;
    $message = 'Préférences créées avec succès';
  }

  if (!$success) {
    jsonResponse(false, 'Erreur lors de la sauvegarde des préférences', null, 500);
  }

  AuthMiddleware::logAction('update_preferences', [
    'fumeur' => $fumeur,
    'animaux' => $animaux,
    'musique' => $musique,
    'discussion' => $discussion,
    'has_autres' => !empty($autresPreferences)
  ]);

  // Récupérer les préférences mises à jour
  $updatedPreferences = DB::findBy('preferences', 'utilisateur_id', $currentUser['id']);

  jsonResponse(true, $message, $updatedPreferences);
}
