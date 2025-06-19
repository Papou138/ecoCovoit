<?php
session_start();
require_once '../db_mysql.php';
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Utilisateur non connecté"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$trajet_id = $_POST['trajet_id'] ?? null;

if (!$trajet_id) {
    echo json_encode(["success" => false, "message" => "ID trajet manquant"]);
    exit;
}

// 1. Vérifie si utilisateur est chauffeur du trajet
$stmt = $pdo->prepare("SELECT id_chauffeur FROM trajets WHERE id = ?");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trajet) {
    echo json_encode(["success" => false, "message" => "Trajet introuvable"]);
    exit;
}

$isChauffeur = ($trajet['id_chauffeur'] == $user_id);

if ($isChauffeur) {
    // === ANNULATION PAR CHAUFFEUR ===

    // Récupère les participants
    $stmt = $pdo->prepare("
        SELECT u.email, u.pseudo
        FROM participations p
        JOIN utilisateurs u ON u.id = p.id_passager
        WHERE p.id_trajet = ?
    ");
    $stmt->execute([$trajet_id]);
    $passagers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rembourse chaque passager (exemple : 2 crédits)
    $pdo->beginTransaction();
    $updateCredits = $pdo->prepare("UPDATE utilisateurs SET credit = credit + 2 WHERE email = ?");
    $deleteParticipations = $pdo->prepare("DELETE FROM participations WHERE id_trajet = ?");
    $deleteTrajet = $pdo->prepare("DELETE FROM trajets WHERE id = ?");

    foreach ($passagers as $p) {
        $updateCredits->execute([$p['email']]);
        // Simuler un envoi d’email
        mail($p['email'], "ecoCovoit - Annulation du trajet", "Le chauffeur a annulé le trajet. Vous avez été remboursé.");
    }

    $deleteParticipations->execute([$trajet_id]);
    $deleteTrajet->execute([$trajet_id]);
    $pdo->commit();

    echo json_encode(["success" => true, "message" => "Trajet annulé. Tous les passagers ont été remboursés."]);
    exit;
}

// === ANNULATION PAR PASSAGER ===

// Supprime la participation
$stmt = $pdo->prepare("DELETE FROM participations WHERE id_trajet = ? AND id_passager = ?");
$stmt->execute([$trajet_id, $user_id]);

// Rembourse le passager (exemple : 2 crédits)
$stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit + 2 WHERE id = ?");
$stmt->execute([$user_id]);

// Libère une place dans le trajet
$stmt = $pdo->prepare("UPDATE trajets SET nb_places_dispo = nb_places_dispo + 1 WHERE id = ?");
$stmt->execute([$trajet_id]);

echo json_encode(["success" => true, "message" => "Votre participation a été annulée. Crédit remboursé."]);
