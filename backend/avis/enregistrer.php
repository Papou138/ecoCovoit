<?php
session_start();
header('Content-Type: application/json');
require_once '../db_mysql.php';

// CouchDB
$host = "http://localhost:5984";
$db   = "ecoCovoit_nosql";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté."]);
    exit;
}

$passager_id = $_SESSION['user_id'];
$trajet_id = $_POST['trajet_id'] ?? null;
$note = $_POST['note'] ?? null;
$commentaire = trim($_POST['commentaire'] ?? '');

if (!$trajet_id || !$note || !$commentaire) {
    echo json_encode(["success" => false, "message" => "Champs manquants."]);
    exit;
}

// Récupère le chauffeur depuis le trajet
$stmt = $pdo->prepare("SELECT id_chauffeur FROM trajets WHERE id = ?");
$stmt->execute([$trajet_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(["success" => false, "message" => "Trajet introuvable."]);
    exit;
}
$chauffeur_id = $row['id_chauffeur'];

// Crée le document CouchDB
$doc = [
    "_id" => "avis_" . uniqid(),
    "type" => "avis",
    "trajet_id" => (int)$trajet_id,
    "chauffeur_id" => (int)$chauffeur_id,
    "passager_id" => (int)$passager_id,
    "note" => (int)$note,
    "commentaire" => $commentaire,
    "valide" => false,
    "date_creation" => date("c")
];

$url = "$host/$db/" . $doc["_id"];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

if (!empty($res['ok'])) {
    echo json_encode(["success" => true, "message" => "Avis envoyé. Merci 🙏"]);
} else {
    echo json_encode(["success" => false, "message" => "Erreur d’enregistrement de l’avis."]);
}
