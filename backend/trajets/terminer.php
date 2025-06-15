<?php
session_start();
require_once '../db_mysql.php';
require_once '../credits/crediter.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Non connecté"]);
    exit;
}

$chauffeur_id = $_SESSION['user_id'];
$trajet_id = $_POST['trajet_id'] ?? null;

if (!$trajet_id) {
    echo json_encode(["success" => false, "message" => "Trajet non spécifié"]);
    exit;
}

try {
    // Vérifie que le trajet appartient au chauffeur
    $stmt = $pdo->prepare("SELECT * FROM trajets WHERE id = ? AND id_chauffeur = ?");
    $stmt->execute([$trajet_id, $chauffeur_id]);
    $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trajet) {
        echo json_encode(["success" => false, "message" => "Trajet non trouvé ou non autorisé"]);
        exit;
    }

    // Récupérer les passagers confirmés
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM participations WHERE id_trajet = ? AND statut = 'confirmé'");
    $stmt->execute([$trajet_id]);
    $passagers = $stmt->fetchColumn();

    // Créditer le chauffeur : 2 crédits par passager
    $totalCredits = $passagers * 2;
    $creditSuccess = crediter($chauffeur_id, $totalCredits);

    if (!$creditSuccess) {
        echo json_encode(["success" => false, "message" => "Erreur crédit"]);
        exit;
    }

    // Mettre à jour le statut du trajet
    $stmt = $pdo->prepare("UPDATE trajets SET statut = 'termine' WHERE id = ?");
    $stmt->execute([$trajet_id]);

    echo json_encode([
        "success" => true,
        "message" => "Trajet terminé ! ✅ Vous avez gagné $totalCredits crédits."
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur serveur"]);
}
