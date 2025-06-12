<?php
// backend/trajets/rechercher.php
require_once '../db_mysql.php';

header('Content-Type: application/json');

$depart = $_POST['depart'] ?? '';
$arrivee = $_POST['arrivee'] ?? '';
$date = $_POST['date'] ?? '';

if (empty($depart) || empty($arrivee) || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Champs manquants']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM trajets
        WHERE ville_depart = ?
          AND ville_arrivee = ?
          AND date_depart = ?
          AND nb_places_dispo > 0");
    $stmt->execute([$depart, $arrivee, $date]);

    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'trajets' => $resultats]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
