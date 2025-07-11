<?php

/**
 * API de gestion du profil utilisateur
 *
 * Endpoint: GET/POST/PUT /backend/users/profile.php
 * - GET: Récupérer le profil de l'utilisateur connecté
 * - POST/PUT: Mettre à jour le profil
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
      getUserProfile();
      break;

    case 'POST':
    case 'PUT':
      updateUserProfile();
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur profile API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}

/**
 * Récupère le profil complet de l'utilisateur connecté
 */
function getUserProfile()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupérer les véhicules de l'utilisateur
  $vehicules = DB::findAll('vehicules', ['proprietaire_id' => $currentUser['id']]);

  // Récupérer les préférences
  $preferences = DB::findBy('preferences', 'utilisateur_id', $currentUser['id']);

  // Récupérer l'historique des transactions (10 dernières)
  $transactions = DB::findAll('transactions', ['utilisateur_id' => $currentUser['id']]);

  // Trier les transactions par date (simuler ORDER BY pour la version mock)
  if (!empty($transactions)) {
    usort($transactions, function ($a, $b) {
      return strtotime($b['date_transaction']) - strtotime($a['date_transaction']);
    });
    $transactions = array_slice($transactions, 0, 10); // 10 dernières
  }

  // Calculer les statistiques utilisateur
  $statsTrajetsChauffeur = count(DB::findAll('trajets', ['chauffeur_id' => $currentUser['id']]));
  $statsTrajetsPassager = count(DB::findAll('participations', ['passager_id' => $currentUser['id']]));

  // Calculer la note moyenne en tant que chauffeur
  $avisReçus = DB::findAll('avis', ['evalue_id' => $currentUser['id'], 'statut' => 'valide']);
  $noteMoyenne = 0;
  if (!empty($avisReçus)) {
    $totalNotes = array_sum(array_column($avisReçus, 'note'));
    $noteMoyenne = round($totalNotes / count($avisReçus), 1);
  }

  // Préparer les données du profil
  $profileData = [
    'utilisateur' => [
      'id' => $currentUser['id'],
      'pseudo' => $currentUser['pseudo'],
      'email' => $currentUser['email'],
      'role' => $currentUser['role'],
      'credits' => $currentUser['credits'],
      'photo_profil' => $currentUser['photo_profil'],
      'telephone' => $currentUser['telephone'],
      'date_creation' => $currentUser['date_creation'],
      'derniere_connexion' => $currentUser['derniere_connexion']
    ],
    'vehicules' => $vehicules,
    'preferences' => $preferences ?: [
      'fumeur' => false,
      'animaux' => false,
      'musique' => true,
      'discussion' => true,
      'autres_preferences' => null
    ],
    'statistiques' => [
      'trajets_chauffeur' => $statsTrajetsChauffeur,
      'trajets_passager' => $statsTrajetsPassager,
      'note_moyenne' => $noteMoyenne,
      'nombre_avis' => count($avisReçus)
    ],
    'transactions_recentes' => $transactions
  ];

  AuthMiddleware::logAction('view_profile');

  jsonResponse(true, 'Profil récupéré avec succès', $profileData);
}

/**
 * Met à jour le profil de l'utilisateur
 */
function updateUserProfile()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupération des données
  $input = json_decode(file_get_contents('php://input'), true);
  $pseudo = trim($input['pseudo'] ?? $_POST['pseudo'] ?? '');
  $email = trim($input['email'] ?? $_POST['email'] ?? '');
  $telephone = trim($input['telephone'] ?? $_POST['telephone'] ?? '');
  $currentPassword = $input['current_password'] ?? $_POST['current_password'] ?? '';
  $newPassword = $input['new_password'] ?? $_POST['new_password'] ?? '';

  $updatedData = [];
  $requirePasswordCheck = false;

  // === VALIDATION ET PRÉPARATION DES DONNÉES ===

  // Mise à jour du pseudo
  if (!empty($pseudo) && $pseudo !== $currentUser['pseudo']) {
    if (strlen($pseudo) < 3 || strlen($pseudo) > 50) {
      jsonResponse(false, 'Le pseudo doit contenir entre 3 et 50 caractères');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pseudo)) {
      jsonResponse(false, 'Le pseudo ne peut contenir que des lettres, chiffres, _ et -');
    }

    // Vérifier l'unicité
    $existingUser = DB::findBy('utilisateurs', 'pseudo', $pseudo);
    if ($existingUser && $existingUser['id'] != $currentUser['id']) {
      jsonResponse(false, 'Ce pseudo est déjà utilisé');
    }

    $updatedData['pseudo'] = $pseudo;
    $requirePasswordCheck = true;
  }

  // Mise à jour de l'email
  if (!empty($email) && $email !== $currentUser['email']) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      jsonResponse(false, 'Format d\'email invalide');
    }

    // Vérifier l'unicité
    $existingUser = DB::findBy('utilisateurs', 'email', $email);
    if ($existingUser && $existingUser['id'] != $currentUser['id']) {
      jsonResponse(false, 'Cette adresse email est déjà utilisée');
    }

    $updatedData['email'] = $email;
    $requirePasswordCheck = true;
  }

  // Mise à jour du téléphone
  if ($telephone !== $currentUser['telephone']) {
    if (!empty($telephone)) {
      if (!preg_match('/^(?:\+33|0)[1-9](?:[0-9]{8})$/', $telephone)) {
        jsonResponse(false, 'Format de téléphone invalide (format français attendu)');
      }
    }
    $updatedData['telephone'] = $telephone ?: null;
  }

  // Mise à jour du mot de passe
  if (!empty($newPassword)) {
    if (strlen($newPassword) < 8) {
      jsonResponse(false, 'Le nouveau mot de passe doit contenir au moins 8 caractères');
    }

    if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
      jsonResponse(false, 'Le nouveau mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre');
    }

    $updatedData['mot_de_passe'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $requirePasswordCheck = true;
  }

  // === VÉRIFICATION DU MOT DE PASSE ACTUEL ===

  if ($requirePasswordCheck && empty($currentPassword)) {
    jsonResponse(false, 'Le mot de passe actuel est requis pour cette modification');
  }

  if ($requirePasswordCheck && !password_verify($currentPassword, $currentUser['mot_de_passe'])) {
    jsonResponse(false, 'Mot de passe actuel incorrect');
  }

  // === MISE À JOUR ===

  if (empty($updatedData)) {
    jsonResponse(false, 'Aucune modification détectée');
  }

  $success = DB::update('utilisateurs', $currentUser['id'], $updatedData);

  if (!$success) {
    jsonResponse(false, 'Erreur lors de la mise à jour', null, 500);
  }

  // Mettre à jour la session si nécessaire
  if (isset($updatedData['pseudo'])) {
    $_SESSION['pseudo'] = $updatedData['pseudo'];
  }
  if (isset($updatedData['email'])) {
    $_SESSION['email'] = $updatedData['email'];
  }

  AuthMiddleware::logAction('update_profile', [
    'fields_updated' => array_keys($updatedData),
    'password_changed' => isset($updatedData['mot_de_passe'])
  ]);

  // Récupérer les données mises à jour
  $updatedUser = DB::findById('utilisateurs', $currentUser['id']);

  $responseData = [
    'utilisateur' => [
      'id' => $updatedUser['id'],
      'pseudo' => $updatedUser['pseudo'],
      'email' => $updatedUser['email'],
      'telephone' => $updatedUser['telephone'],
      'photo_profil' => $updatedUser['photo_profil']
    ]
  ];

  jsonResponse(true, 'Profil mis à jour avec succès', $responseData);
}
