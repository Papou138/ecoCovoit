<?php
session_start();
require_once '../db_mysql.php';
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté"]);
    exit;
}

$trajet_id = $_POST['trajet_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$trajet_id) {
    echo json_encode(["success" => false, "message" => "ID manquant"]);
    exit;
}

// Vérifie que l’utilisateur est bien le chauffeur du trajet
$stmt = $pdo->prepare("SELECT id_chauffeur, statut FROM trajets WHERE id = ?");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trajet || $trajet['id_chauffeur'] != $user_id) {
    echo json_encode(["success" => false, "message" => "Accès refusé"]);
    exit;
}

if ($trajet['statut'] === 'termine') {
    echo json_encode(["success" => false, "message" => "Trajet déjà terminé"]);
    exit;
}

// Met à jour le statut
$stmt = $pdo->prepare("UPDATE trajets SET statut = 'en_cours' WHERE id = ?");
$stmt->execute([$trajet_id]);

echo json_encode(["success" => true, "message" => "Trajet démarré avec succès 🚗"]);
