<?php

/**
 * API de connexion utilisateur
 *
 * Endpoint: POST /backend/auth/login.php
 * Paramètres: email, password
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée', null, 405);
}

try {
    // Récupération des données (POST ou JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? $_POST['email'] ?? '');
    $password = $input['password'] ?? $_POST['password'] ?? '';

    // Validation des données
    if (empty($email)) {
        jsonResponse(false, 'L\'email est requis');
    }

    if (empty($password)) {
        jsonResponse(false, 'Le mot de passe est requis');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Format d\'email invalide');
    }

    // Recherche de l'utilisateur
    $user = DB::findBy('utilisateurs', 'email', $email);

    if (!$user) {
        jsonResponse(false, 'Identifiants invalides');
    }

    // Vérification du statut du compte
    if ($user['statut'] === 'suspendu') {
        jsonResponse(false, 'Compte suspendu. Contactez l\'administrateur.');
    }

    // Vérification du mot de passe
    if (!password_verify($password, $user['mot_de_passe'])) {
        jsonResponse(false, 'Identifiants invalides');
    }

    // Mise à jour de la dernière connexion
    DB::update('utilisateurs', $user['id'], [
        'derniere_connexion' => date('Y-m-d H:i:s')
    ]);

    // Création de la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['pseudo'] = $user['pseudo'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['credits'] = $user['credits'];
    $_SESSION['login_time'] = time();

    // Données utilisateur à retourner (sans mot de passe)
    $userData = [
        'id' => $user['id'],
        'pseudo' => $user['pseudo'],
        'email' => $user['email'],
        'role' => $user['role'],
        'credits' => $user['credits'],
        'photo_profil' => $user['photo_profil'],
        'telephone' => $user['telephone']
    ];

    jsonResponse(true, 'Connexion réussie !', [
        'user' => $userData,
        'session_id' => session_id()
    ]);
} catch (Exception $e) {
    error_log("Erreur login: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}
