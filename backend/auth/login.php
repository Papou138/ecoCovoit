<?php
// backend/auth/login.php
session_start();
require_once '../db_mysql.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Champs requis."]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        // Connexion réussie : stocker l'utilisateur en session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['pseudo'] = $user['pseudo'];
        $_SESSION['role'] = $user['role'];

        echo json_encode(["success" => true, "message" => "Connexion réussie !"]);
    } else {
        echo json_encode(["success" => false, "message" => "Identifiants invalides."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur serveur."]);
}
