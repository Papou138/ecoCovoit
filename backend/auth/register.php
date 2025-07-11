<?php

/**
 * API d'inscription utilisateur
 *
 * Endpoint: POST /backend/auth/register.php
 * Paramètres: pseudo, email, password, confirm_password
 */

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
    $pseudo = trim($input['pseudo'] ?? $_POST['pseudo'] ?? '');
    $email = trim($input['email'] ?? $_POST['email'] ?? '');
    $password = $input['password'] ?? $_POST['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? $_POST['confirm_password'] ?? '';

    // === VALIDATION DES DONNÉES ===

    // Champs requis
    if (empty($pseudo)) {
        jsonResponse(false, 'Le pseudo est requis');
    }

    if (empty($email)) {
        jsonResponse(false, 'L\'email est requis');
    }

    if (empty($password)) {
        jsonResponse(false, 'Le mot de passe est requis');
    }

    // Validation du pseudo
    if (strlen($pseudo) < 3) {
        jsonResponse(false, 'Le pseudo doit contenir au moins 3 caractères');
    }

    if (strlen($pseudo) > 50) {
        jsonResponse(false, 'Le pseudo ne peut pas dépasser 50 caractères');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pseudo)) {
        jsonResponse(false, 'Le pseudo ne peut contenir que des lettres, chiffres, _ et -');
    }

    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Format d\'email invalide');
    }

    if (strlen($email) > 100) {
        jsonResponse(false, 'L\'email ne peut pas dépasser 100 caractères');
    }

    // Validation du mot de passe
    if (strlen($password) < 8) {
        jsonResponse(false, 'Le mot de passe doit contenir au moins 8 caractères');
    }

    if ($confirmPassword && $password !== $confirmPassword) {
        jsonResponse(false, 'Les mots de passe ne correspondent pas');
    }

    // Vérification de la complexité du mot de passe
    if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        jsonResponse(false, 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre');
    }

    // === VÉRIFICATION DE L'UNICITÉ ===

    // Vérifier si le pseudo existe déjà
    $existingPseudo = DB::findBy('utilisateurs', 'pseudo', $pseudo);
    if ($existingPseudo) {
        jsonResponse(false, 'Ce pseudo est déjà utilisé');
    }

    // Vérifier si l'email existe déjà
    $existingEmail = DB::findBy('utilisateurs', 'email', $email);
    if ($existingEmail) {
        jsonResponse(false, 'Cette adresse email est déjà utilisée');
    }

    // === CRÉATION DU COMPTE ===

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $userData = [
        'pseudo' => $pseudo,
        'email' => $email,
        'mot_de_passe' => $hashedPassword,
        'role' => 'utilisateur',
        'credits' => CREDITS_INITIAL,
        'statut' => 'actif',
        'date_creation' => date('Y-m-d H:i:s')
    ];

    $userId = DB::insert('utilisateurs', $userData);

    if (!$userId) {
        jsonResponse(false, 'Erreur lors de la création du compte', null, 500);
    }

    // Enregistrer la transaction de crédit initial
    $transactionData = [
        'utilisateur_id' => $userId,
        'type' => 'credit',
        'montant' => CREDITS_INITIAL,
        'solde_avant' => 0,
        'solde_apres' => CREDITS_INITIAL,
        'description' => 'Crédits de bienvenue',
        'date_transaction' => date('Y-m-d H:i:s')
    ];

    DB::insert('transactions', $transactionData);

    // Données utilisateur à retourner
    $newUser = [
        'id' => $userId,
        'pseudo' => $pseudo,
        'email' => $email,
        'role' => 'utilisateur',
        'credits' => CREDITS_INITIAL
    ];

    jsonResponse(true, 'Inscription réussie ! Vous pouvez maintenant vous connecter.', [
        'user' => $newUser,
        'credits_initial' => CREDITS_INITIAL
    ]);
} catch (Exception $e) {
    error_log("Erreur registration: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}
