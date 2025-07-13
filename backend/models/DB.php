<?php

/**
 * Utilitaire de base de données - Détection automatique
 *
 * Utilise la vraie base MySQL si disponible, sinon bascule sur les fichiers JSON
 */

require_once __DIR__ . '/../config/config.php';

class DB
{
  private static $useMock = null;

  /**
   * Détecte si on doit utiliser la base mock ou réelle
   */
  private static function shouldUseMock()
  {
    if (self::$useMock === null) {
      try {
        // Test de connexion MySQL
        if (!extension_loaded('pdo_mysql')) {
          throw new Exception("Extension pdo_mysql non disponible");
        }

        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo = null; // Ferme la connexion test

        self::$useMock = false;
        // error_log("✅ Utilisation de MySQL");

      } catch (Exception $e) {
        self::$useMock = true;
        // error_log("⚠️ Utilisation du mode Mock (JSON): " . $e->getMessage());
      }
    }

    return self::$useMock;
  }

  /**
   * Récupère un enregistrement par ID
   */
  public static function findById($table, $id)
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::findById($table, $id);
    } else {
      require_once __DIR__ . '/Database.php';
      return Database::fetchOne("SELECT * FROM $table WHERE id = ?", [$id]);
    }
  }

  /**
   * Récupère un enregistrement par critère
   */
  public static function findBy($table, $field, $value)
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::findBy($table, $field, $value);
    } else {
      require_once __DIR__ . '/Database.php';
      return Database::fetchOne("SELECT * FROM $table WHERE $field = ?", [$value]);
    }
  }

  /**
   * Récupère tous les enregistrements avec critères optionnels
   */
  public static function findAll($table, $criteria = [], $orderBy = '', $limit = '')
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::findAllBy($table, $criteria);
    } else {
      require_once __DIR__ . '/Database.php';

      $sql = "SELECT * FROM $table";
      $params = [];

      if (!empty($criteria)) {
        $conditions = [];
        foreach ($criteria as $field => $value) {
          $conditions[] = "$field = ?";
          $params[] = $value;
        }
        $sql .= " WHERE " . implode(" AND ", $conditions);
      }

      if ($orderBy) $sql .= " ORDER BY $orderBy";
      if ($limit) $sql .= " LIMIT $limit";

      return Database::fetchAll($sql, $params);
    }
  }

  /**
   * Insère un nouvel enregistrement
   */
  public static function insert($table, $data)
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::insert($table, $data);
    } else {
      require_once __DIR__ . '/Database.php';

      $fields = array_keys($data);
      $placeholders = array_fill(0, count($fields), '?');

      $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

      return Database::insert($sql, array_values($data));
    }
  }

  /**
   * Met à jour un enregistrement
   */
  public static function update($table, $id, $data)
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::update($table, $id, $data);
    } else {
      require_once __DIR__ . '/Database.php';

      $fields = [];
      $params = [];

      foreach ($data as $field => $value) {
        $fields[] = "$field = ?";
        $params[] = $value;
      }
      $params[] = $id;

      $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE id = ?";

      return Database::query($sql, $params)->rowCount() > 0;
    }
  }

  /**
   * Supprime un enregistrement
   */
  public static function delete($table, $id)
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      return DatabaseMock::delete($table, $id);
    } else {
      require_once __DIR__ . '/Database.php';
      return Database::query("DELETE FROM $table WHERE id = ?", [$id])->rowCount() > 0;
    }
  }

  /**
   * Recherche de trajets avec filtres (spécifique à l'application)
   */
  public static function searchTrajets($depart, $arrivee, $date, $filters = [])
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      $trajets = DatabaseMock::loadData('trajets');

      // Filtrage manuel pour la version mock
      $results = [];
      foreach ($trajets as $trajet) {
        // Filtre par ville (recherche souple)
        if (
          stripos($trajet['depart'], $depart) === false &&
          stripos($trajet['arrivee'], $arrivee) === false
        ) {
          continue;
        }

        // Filtre par date (même jour)
        if ($date && date('Y-m-d', strtotime($trajet['date_depart'])) !== $date) {
          continue;
        }

        // Seulement les trajets avec places disponibles et statut planifié
        if ($trajet['nombre_places_restantes'] <= 0 || $trajet['statut'] !== 'planifie') {
          continue;
        }

        // Ajouter les infos du chauffeur et véhicule
        $chauffeur = DatabaseMock::findById('utilisateurs', $trajet['chauffeur_id']);
        $vehicule = DatabaseMock::findById('vehicules', $trajet['vehicule_id']);

        $trajet['nom'] = $chauffeur['nom'] ?? 'Inconnu';
        $trajet['prenom'] = $chauffeur['prenom'] ?? 'Inconnu';
        $trajet['note_moyenne'] = $chauffeur['note_moyenne'] ?? 4.5;
        $trajet['marque'] = $vehicule['marque'] ?? '';
        $trajet['modele'] = $vehicule['modele'] ?? '';
        $trajet['type_carburant'] = $vehicule['type_carburant'] ?? 'essence';
        $trajet['est_ecologique'] = in_array($vehicule['type_carburant'] ?? '', ['électrique', 'hybride']);

        $results[] = $trajet;
      }

      return $results;
    } else {
      require_once __DIR__ . '/Database.php';

      $sql = "SELECT t.*, u.pseudo as chauffeur_pseudo, u.photo_profil as chauffeur_photo,
                           v.marque as vehicule_marque, v.modele as vehicule_modele,
                           v.type_energie, (v.type_energie = 'electrique') as est_ecologique,
                           COALESCE(AVG(a.note), 0) as note_moyenne
                    FROM trajets t
                    JOIN utilisateurs u ON t.chauffeur_id = u.id
                    JOIN vehicules v ON t.vehicule_id = v.id
                    LEFT JOIN avis a ON a.evalue_id = u.id AND a.statut = 'valide'
                    WHERE t.ville_depart LIKE ?
                    AND t.ville_arrivee LIKE ?
                    AND DATE(t.date_depart) = ?
                    AND t.nombre_places_restantes > 0
                    AND t.statut = 'planifie'
                    GROUP BY t.id
                    ORDER BY t.date_depart ASC";

      return Database::fetchAll($sql, ["%$depart%", "%$arrivee%", $date]);
    }
  }

  /**
   * Exécute une requête SQL personnalisée
   */
  public static function query($sql, $params = [])
  {
    if (self::shouldUseMock()) {
      require_once __DIR__ . '/DatabaseMock.php';
      // Pour les requêtes complexes, retourner un tableau vide en mock
      // TODO: Implémenter un parser SQL basique pour les requêtes mock
      return [];
    } else {
      require_once __DIR__ . '/Database.php';
      return Database::fetchAll($sql, $params);
    }
  }
}
