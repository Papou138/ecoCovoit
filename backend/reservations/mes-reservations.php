<?php
session_start();
require_once '../db_mysql.php';
header("Content-Type: application/json");

// Sécurité : vérifie que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les trajets réservés par l'utilisateur en tant que passager
$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.ville_depart,
        t.ville_arrivee,
        t.date,
        t.statut,
        u.pseudo AS pseudo_chauffeur
    FROM participations p
    JOIN trajets t ON t.id = p.id_trajet
    JOIN utilisateurs u ON u.id = t.id_chauffeur
    WHERE p.id_passager = ?
    ORDER BY t.date DESC
");
$stmt->execute([$user_id]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retourne la liste au format JSON
echo json_encode($reservations);
