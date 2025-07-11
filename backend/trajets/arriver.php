<?php
session_start();
require_once '../db_mysql.php';
require_once '../utils/email.php'; // si tu as un module mail

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

// Vérifie le trajet et le rôle
$stmt = $pdo->prepare("SELECT id_chauffeur, statut FROM trajets WHERE id = ?");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trajet || $trajet['id_chauffeur'] != $user_id) {
    echo json_encode(["success" => false, "message" => "Accès refusé"]);
    exit;
}

if ($trajet['statut'] !== 'en_cours') {
    echo json_encode(["success" => false, "message" => "Le trajet n'est pas en cours"]);
    exit;
}

// 1. Marque le trajet comme terminé
$stmt = $pdo->prepare("UPDATE trajets SET statut = 'termine' WHERE id = ?");
$stmt->execute([$trajet_id]);

// 2. Récupère les passagers
$stmt = $pdo->prepare("
    SELECT u.email, u.pseudo
    FROM participations p
    JOIN utilisateurs u ON u.id = p.id_passager
    WHERE p.id_trajet = ?
");
$stmt->execute([$trajet_id]);
$passagers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Envoie une notification (email simulé)
foreach ($passagers as $p) {
    envoyer_email(
        $p['email'],
        "ecoCovoit - Evaluation du trajet",
        "Bonjour {$p['pseudo']}, le trajet est terminé.\nVeuillez vous connecter pour évaluer votre chauffeur."
    );
}

echo json_encode(["success" => true, "message" => "Trajet terminé. Avis demandés aux passagers."]);
