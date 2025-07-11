<?php

/**
 * API de vérification de session et récupération des données utilisateur
 *
 * Endpoint: GET /backend/auth/get-user.php
 * Retourne les informations de l'utilisateur connecté
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Session expirée. Veuillez vous reconnecter.', null, 401);
    }

    // Vérifier la durée de la session
    if (isset($_SESSION['login_time'])) {
        $sessionAge = time() - $_SESSION['login_time'];
        if ($sessionAge > SESSION_LIFETIME) {
            // Session expirée
            session_unset();
            session_destroy();
            jsonResponse(false, 'Session expirée. Veuillez vous reconnecter.', null, 401);
        }
    }

    $userId = $_SESSION['user_id'];

    // Récupérer les données actualisées de l'utilisateur
    $user = DB::findById('utilisateurs', $userId);

    if (!$user) {
        // L'utilisateur n'existe plus (supprimé?)
        session_unset();
        session_destroy();
        jsonResponse(false, 'Compte utilisateur introuvable.', null, 404);
    }

    // Vérifier si le compte n'est pas suspendu
    if ($user['statut'] === 'suspendu') {
        session_unset();
        session_destroy();
        jsonResponse(false, 'Compte suspendu. Contactez l\'administrateur.', null, 403);
    }

    // Mettre à jour la session avec les données actuelles
    $_SESSION['pseudo'] = $user['pseudo'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['credits'] = $user['credits'];

    // Données utilisateur à retourner (sans informations sensibles)
    $userData = [
        'id' => $user['id'],
        'pseudo' => $user['pseudo'],
        'email' => $user['email'],
        'role' => $user['role'],
        'credits' => $user['credits'],
        'photo_profil' => $user['photo_profil'],
        'telephone' => $user['telephone'],
        'date_creation' => $user['date_creation'],
        'derniere_connexion' => $user['derniere_connexion']
    ];

    // Informations de session
    $sessionInfo = [
        'session_id' => session_id(),
        'login_time' => $_SESSION['login_time'] ?? null,
        'session_age' => isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : null
    ];

    jsonResponse(true, 'Session valide', [
        'user' => $userData,
        'session' => $sessionInfo
    ]);
} catch (Exception $e) {
    error_log("Erreur get-user: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}
