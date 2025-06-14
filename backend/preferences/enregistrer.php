<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté."]);
    exit;
}

$uid = $_SESSION['user_id'];
$host = "http://localhost:5984";
$db   = "ecoCovoit_nosql";

// Préparer les données
$preferences = [
    "utilisateur_id" => $uid,
    "type" => "preferences",
    "fumeur" => isset($_POST['fumeur']),
    "animaux" => isset($_POST['animaux']),
    "musique" => isset($_POST['musique']),
    "autres" => !empty($_POST['autres']) ? array_map('trim', explode(',', $_POST['autres'])) : []
];

// ID unique par utilisateur
$doc_id = "preferences_user_" . $uid;
$url = "$host/$db/$doc_id";

// Vérifie si le document existe déjà (pour le _rev)
$rev = null;
$existing = @file_get_contents($url);
if ($existing) {
    $data = json_decode($existing, true);
    if (isset($data['_rev'])) {
        $preferences['_rev'] = $data['_rev'];
    }
}

// Envoi via PUT
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferences));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result['ok'])) {
    echo json_encode(["success" => true, "message" => "Préférences enregistrées ✅"]);
} else {
    echo json_encode(["success" => false, "message" => "Erreur CouchDB"]);
}
