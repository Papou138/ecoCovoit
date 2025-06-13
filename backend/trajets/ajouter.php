<?php
session_start();
require_once '../db_mysql.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté."]);
    exit;
}

$chauffeur_id = $_SESSION['user_id'];

$depart     = $_POST['depart'] ?? '';
$arrivee    = $_POST['arrivee'] ?? '';
$date       = $_POST['date'] ?? '';
$heure_dep  = $_POST['heure_depart'] ?? '';
$heure_arr  = $_POST['heure_arrivee'] ?? '';
$prix       = $_POST['prix'] ?? '';
$vehicule   = $_POST['vehicule_id'] ?? '';
$places     = $_POST['places'] ?? '';

if (!$depart || !$arrivee || !$date || !$heure_dep || !$heure_arr || !$prix || !$vehicule || !$places) {
    echo json_encode(["success" => false, "message" => "Champs manquants."]);
    exit;
}

// Prix net après déduction des 2 crédits pour la plateforme
$prix_final = $prix - 2;

try {
    $stmt = $pdo->prepare("INSERT INTO trajets
        (id_chauffeur, id_vehicule, ville_depart, ville_arrivee, date_depart, heure_depart, heure_arrivee, prix, nb_places_dispo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $chauffeur_id,
        $vehicule,
        $depart,
        $arrivee,
        $date,
        $heure_dep,
        $heure_arr,
        $prix_final,
        $places
    ]);

    echo json_encode(["success" => true, "message" => "Trajet créé avec succès ✅"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
