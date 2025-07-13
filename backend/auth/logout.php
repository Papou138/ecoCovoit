<?php

/**
 * API de déconnexion utilisateur
 *
 * Endpoint: POST /backend/auth/logout.php
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  // Vérifier si une session existe
  if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Aucune session active');
  }

  $userId = $_SESSION['user_id'];
  $pseudo = $_SESSION['pseudo'] ?? 'Utilisateur';

  // Détruire la session
  session_unset();
  session_destroy();

  // Optionnel : supprimer le cookie de session
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }

  jsonResponse(true, "Déconnexion réussie. A bientôt {$pseudo} !", [
    'user_id' => $userId,
    'redirect_url' => '/frontend/login.html'
  ]);
} catch (Exception $e) {
  error_log("Erreur logout: " . $e->getMessage());
  jsonResponse(false, 'Erreur lors de la déconnexion', null, 500);
}
