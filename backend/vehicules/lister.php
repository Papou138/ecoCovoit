<?php
session_start();
require_once '../db_mysql.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, marque, modele, immatriculation FROM vehicules WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
