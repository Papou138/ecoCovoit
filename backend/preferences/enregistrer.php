<?php
session_start();
require_once '../db_mongo.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté."]);
    exit;
}

$uid = $_SESSION['user_id'];

// Préférences par défaut : false
$preferences = [
    "fumeur" => isset($_POST['fumeur']),
    "animaux" => isset($_POST['animaux']),
    "musique" => isset($_POST['musique']),
    "autres" => []
];

// Préférences personnalisées (champ texte)
if (!empty($_POST['autres'])) {
    $preferences["autres"] = array_map(
        "trim",
        explode(",", $_POST['autres'])
    );
}

try {
    // Upsert (insertion ou mise à jour)
    $collection->updateOne(
        ["utilisateur_id" => $uid],
        ['$set' => ["preferences" => $preferences]],
        ['upsert' => true]
    );

    echo json_encode(["success" => true, "message" => "Préférences enregistrées ✅"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur MongoDB : " . $e->getMessage()]);
}
