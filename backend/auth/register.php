<?php
require_once '../db_mysql.php';
header('Content-Type: application/json');

$pseudo = trim($_POST['pseudo'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$pseudo || !$email || !$password) {
    echo json_encode(["success" => false, "message" => "Tous les champs sont requis."]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$creditInitial = 20;

try {
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credit) VALUES (?, ?, ?, 'utilisateur', ?)");
    $stmt->execute([$pseudo, $email, $hashedPassword, $creditInitial]);

    echo json_encode(["success" => true, "message" => "Inscription réussie !"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
