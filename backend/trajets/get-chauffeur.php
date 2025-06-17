<?php
require_once '../db_mysql.php';
header('Content-Type: application/json');

$trajet_id = $_GET['trajet_id'] ?? null;

if (!$trajet_id) {
    echo json_encode(["success" => false, "message" => "ID trajet manquant"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id_chauffeur FROM trajets WHERE id = ?");
$stmt->execute([$trajet_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(["success" => true, "chauffeur_id" => $row['id_chauffeur']]);
} else {
    echo json_encode(["success" => false, "message" => "Trajet introuvable"]);
}
