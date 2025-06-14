<?php
session_start();
require_once '../db_mysql.php';
require_once '../credits/crediter.php'; // inclure le système de crédits
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Vous devez être connecté."]);
    exit;
}

$trajet_id = $_POST['trajet_id'] ?? null;
$user_id = $_SESSION['user_id'];
$credit_cout = 2;

if (!$trajet_id) {
    echo json_encode(["success" => false, "message" => "Trajet non spécifié."]);
    exit;
}

try {
    // Vérifier que le trajet existe et a des places
    $stmt = $pdo->prepare("SELECT * FROM trajets WHERE id = ?");
    $stmt->execute([$trajet_id]);
    $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trajet) {
        echo json_encode(["success" => false, "message" => "Trajet introuvable."]);
        exit;
    }

    if ((int)$trajet['nb_places_dispo'] < 1) {
        echo json_encode(["success" => false, "message" => "Aucune place disponible pour ce trajet."]);
        exit;
    }

    // Débiter les crédits
    $deb_result = debiter($user_id, $credit_cout);

    if ($deb_result === "insuffisant") {
        echo json_encode(["success" => false, "message" => "Crédits insuffisants pour participer à ce trajet."]);
        exit;
    }

    if ($deb_result !== true) {
        echo json_encode(["success" => false, "message" => "Erreur lors du traitement du paiement."]);
        exit;
    }

    // Enregistrer la participation et mettre à jour les places disponibles
    $pdo->beginTransaction();

    // Ajouter la réservation
    $stmt = $pdo->prepare("INSERT INTO participations (id_trajet, id_passager, statut) VALUES (?, ?, 'confirmé')");
    $stmt->execute([$trajet_id, $user_id]);

    // Mettre à jour les places restantes
    $stmt = $pdo->prepare("UPDATE trajets SET nb_places_dispo = nb_places_dispo - 1 WHERE id = ?");
    $stmt->execute([$trajet_id]);

    $pdo->commit();

    // Envoi de la réponse JSON
    echo json_encode(["success" => true, "message" => "Réservation réussie."]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "Erreur serveur lors de la réservation."]);
}
