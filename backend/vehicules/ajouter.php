<?php
session_start();
require_once '../db_mysql.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté."]);
    exit;
}

$uid = $_SESSION['user_id'];

$immat = trim($_POST['immatriculation'] ?? '');
$marque = trim($_POST['marque'] ?? '');
$modele = trim($_POST['modele'] ?? '');
$couleur = trim($_POST['couleur'] ?? '');
$energie = trim($_POST['energie'] ?? '');
$date = $_POST['date_immatriculation'] ?? '';

if (!$immat || !$marque || !$modele || !$couleur || !$energie || !$date) {
    echo json_encode(["success" => false, "message" => "Tous les champs sont requis."]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO vehicules (id_utilisateur, immatriculation, marque, modele, couleur, type_energie, date_premiere_circu)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $immat, $marque, $modele, $couleur, $energie, $date]);

    echo json_encode(["success" => true, "message" => "Véhicule ajouté avec succès ✅"]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(["success" => false, "message" => "Immatriculation déjà utilisée."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur serveur : " . $e->getMessage()]);
    }
}
