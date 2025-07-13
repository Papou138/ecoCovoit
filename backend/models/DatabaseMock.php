<?php

/**
 * Classe Database Mock - Simulation pour développement
 *
 * Cette classe simule une base de données avec des fichiers JSON
 * pour permettre le développement sans MySQL configuré
 */

class DatabaseMock
{
  private static $dataDir = null;

  public static function init()
  {
    if (self::$dataDir === null) {
      self::$dataDir = __DIR__ . '/../data';
      if (!is_dir(self::$dataDir)) {
        if (!mkdir(self::$dataDir, 0777, true)) {
          throw new Exception("Impossible de créer le dossier data");
        }
      }
      self::initData();
    }
  }

  /**
   * Initialise les données de base si elles n'existent pas
   */
  private static function initData()
  {
    $tables = ['utilisateurs', 'vehicules', 'trajets', 'participations', 'preferences', 'avis'];

    foreach ($tables as $table) {
      $file = self::$dataDir . "/$table.json";
      if (!file_exists($file)) {
        switch ($table) {
          case 'utilisateurs':
            self::saveData($table, [
              [
                'id' => 1,
                'pseudo' => 'admin',
                'email' => 'admin@ecocovoit.fr',
                'mot_de_passe' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'admin',
                'credits' => 1000,
                'statut' => 'actif',
                'date_creation' => date('Y-m-d H:i:s')
              ],
              [
                'id' => 2,
                'pseudo' => 'chauffeur1',
                'email' => 'chauffeur@test.fr',
                'mot_de_passe' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'utilisateur',
                'credits' => 50,
                'statut' => 'actif',
                'date_creation' => date('Y-m-d H:i:s')
              ],
              [
                'id' => 3,
                'pseudo' => 'passager1',
                'email' => 'passager@test.fr',
                'mot_de_passe' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'utilisateur',
                'credits' => 30,
                'statut' => 'actif',
                'date_creation' => date('Y-m-d H:i:s')
              ]
            ]);
            break;

          case 'vehicules':
            self::saveData($table, [
              [
                'id' => 1,
                'proprietaire_id' => 2,
                'marque' => 'Tesla',
                'modele' => 'Model 3',
                'couleur' => 'Blanc',
                'immatriculation' => 'AB-123-CD',
                'nombre_places' => 4,
                'type_energie' => 'electrique',
                'actif' => true
              ]
            ]);
            break;

          case 'trajets':
            self::saveData($table, [
              [
                'id' => 1,
                'chauffeur_id' => 2,
                'vehicule_id' => 1,
                'ville_depart' => 'Paris',
                'ville_arrivee' => 'Lyon',
                'date_depart' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'prix' => 25.00,
                'nombre_places_total' => 3,
                'nombre_places_restantes' => 3,
                'statut' => 'planifie',
                'date_creation' => date('Y-m-d H:i:s')
              ]
            ]);
            break;

          default:
            self::saveData($table, []);
            break;
        }
      }
    }
  }

  /**
   * Charge les données d'une table
   */
  public static function loadData($table)
  {
    self::init();
    $file = self::$dataDir . "/$table.json";
    if (file_exists($file)) {
      $data = json_decode(file_get_contents($file), true);
      return $data ?: [];
    }
    return [];
  }

  /**
   * Sauvegarde les données d'une table
   */
  public static function saveData($table, $data)
  {
    self::init();
    $file = self::$dataDir . "/$table.json";
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  }

  /**
   * Trouve un enregistrement par ID
   */
  public static function findById($table, $id)
  {
    $data = self::loadData($table);
    foreach ($data as $item) {
      if ($item['id'] == $id) {
        return $item;
      }
    }
    return null;
  }

  /**
   * Trouve un enregistrement par critère
   */
  public static function findBy($table, $field, $value)
  {
    $data = self::loadData($table);
    foreach ($data as $item) {
      if (isset($item[$field]) && $item[$field] == $value) {
        return $item;
      }
    }
    return null;
  }

  /**
   * Trouve tous les enregistrements correspondant à des critères
   */
  public static function findAllBy($table, $criteria = [])
  {
    $data = self::loadData($table);
    if (empty($criteria)) {
      return $data;
    }

    $results = [];
    foreach ($data as $item) {
      $match = true;
      foreach ($criteria as $field => $value) {
        if (!isset($item[$field]) || $item[$field] != $value) {
          $match = false;
          break;
        }
      }
      if ($match) {
        $results[] = $item;
      }
    }
    return $results;
  }

  /**
   * Insère un nouvel enregistrement
   */
  public static function insert($table, $data)
  {
    $allData = self::loadData($table);

    // Génère un nouvel ID
    $maxId = 0;
    foreach ($allData as $item) {
      if (isset($item['id']) && $item['id'] > $maxId) {
        $maxId = $item['id'];
      }
    }

    $data['id'] = $maxId + 1;
    $data['date_creation'] = $data['date_creation'] ?? date('Y-m-d H:i:s');

    $allData[] = $data;
    self::saveData($table, $allData);

    return $data['id'];
  }

  /**
   * Met à jour un enregistrement
   */
  public static function update($table, $id, $updates)
  {
    $allData = self::loadData($table);

    foreach ($allData as $index => $item) {
      if ($item['id'] == $id) {
        $allData[$index] = array_merge($item, $updates);
        $allData[$index]['date_modification'] = date('Y-m-d H:i:s');
        self::saveData($table, $allData);
        return true;
      }
    }
    return false;
  }

  /**
   * Supprime un enregistrement
   */
  public static function delete($table, $id)
  {
    $allData = self::loadData($table);

    foreach ($allData as $index => $item) {
      if ($item['id'] == $id) {
        unset($allData[$index]);
        $allData = array_values($allData); // Réindexe le tableau
        self::saveData($table, $allData);
        return true;
      }
    }
    return false;
  }
}
