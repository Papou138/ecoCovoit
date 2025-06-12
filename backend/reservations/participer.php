<?php
session_start();
require_once '../db_mysql.php';
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

    if (!$trajet || $trajet['nb_places_dispo'] <= 0) {
        echo json_encode(["success" => false, "message" => "Aucune place disponible."]);
        exit;
    }

    // Vérifier les crédits de l'utilisateur
    $stmt = $pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user['credit'] < $credit_cout) {
        echo json_encode(["success" => false, "message" => "Pas assez de crédits."]);
        exit;
    }

    // Enregistrer la participation
    $pdo->beginTransaction();

    // Ajouter la participation
    $stmt = $pdo->prepare("INSERT INTO participations (id_trajet, id_passager, statut) VALUES (?, ?, 'confirmé')");
    $stmt->execute([$trajet_id, $user_id]);

    // Mettre à jour le nombre de places
    $stmt = $pdo->prepare("UPDATE trajets SET nb_places_dispo = nb_places_dispo - 1 WHERE id = ?");
    $stmt->execute([$trajet_id]);

    // Décrémenter les crédits
    $stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
    $stmt->execute([$credit_cout, $user_id]);

    $pdo->commit();

    echo json_encode(["success" => true, "message" => "Participation enregistrée ✅"]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "Erreur lors de la réservation."]);
}
