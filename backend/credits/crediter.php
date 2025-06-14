<?php
require_once __DIR__ . '/../db_mysql.php';

/**
 * Ajoute des crédits à un utilisateur
 * @param int $user_id
 * @param int $montant
 * @return bool
 */
function crediter($user_id, $montant) {
    global $pdo;

    if ($montant <= 0) {
      return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
        return $stmt->execute([$montant, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Retire des crédits à un utilisateur (si solde suffisant)
 * @param int $user_id
 * @param int $montant
 * @return bool|string  true si OK, "insuffisant" si pas assez
 */
function debiter($user_id, $montant) {
    global $pdo;

    if ($montant <= 0) {
      return false;
    }

    try {
        // Vérifie solde actuel
        $stmt = $pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['credit'] < $montant) {
            return "insuffisant";
        }

        // Débit
        $stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
        return $stmt->execute([$montant, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}
