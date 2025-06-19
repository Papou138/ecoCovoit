<?php
session_start();
require_once '../db_mysql.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$results = [];

// 1. Trajets en tant que chauffeur
$stmt = $pdo->prepare("SELECT id, ville_depart, ville_arrivee, date, statut
                       FROM trajets
                       WHERE id_chauffeur = ?");
$stmt->execute([$user_id]);
$chauffeur_trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($chauffeur_trajets as $trajet) {
    $results[] = [
        "id" => $trajet['id'],
        "ville_depart" => $trajet['ville_depart'],
        "ville_arrivee" => $trajet['ville_arrivee'],
        "date" => $trajet['date'],
        "statut" => $trajet['statut'],
        "est_chauffeur" => true,
        "annulable" => ($trajet['statut'] !== 'termine')
    ];
}

// 2. Trajets en tant que passager
$stmt = $pdo->prepare("
    SELECT t.id, t.ville_depart, t.ville_arrivee, t.date, t.statut
    FROM participations p
    JOIN trajets t ON t.id = p.id_trajet
    WHERE p.id_passager = ?
");
$stmt->execute([$user_id]);
$passager_trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($passager_trajets as $trajet) {
    $results[] = [
        "id" => $trajet['id'],
        "ville_depart" => $trajet['ville_depart'],
        "ville_arrivee" => $trajet['ville_arrivee'],
        "date" => $trajet['date'],
        "statut" => $trajet['statut'],
        "est_chauffeur" => false,
        "annulable" => ($trajet['statut'] !== 'termine')
    ];
}

echo json_encode($results);
