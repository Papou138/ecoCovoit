<?php

/**
 * Classe Database - Gestion des connexions aux bases de données
 *
 * Cette classe gère les connexions MySQL et MongoDB
 * et fournit des méthodes utilitaires pour les requêtes
 */

require_once __DIR__ . '/../config/config.php';

class Database
{
  private static $pdo = null;
  private static $mongo = null;

  /**
   * Connexion à MySQL avec PDO
   */
  public static function getPDO()
  {
    if (self::$pdo === null) {
      try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false
        ]);
      } catch (PDOException $e) {
        error_log("Erreur de connexion MySQL : " . $e->getMessage());
        jsonResponse(false, "Erreur de base de données", null, 500);
      }
    }
    return self::$pdo;
  }

  /**
   * Connexion à MongoDB (pour plus tard)
   */
  public static function getMongo()
  {
    if (self::$mongo === null) {
      try {
        // Pour l'instant, on simule MongoDB avec un fichier JSON
        // Plus tard on pourra ajouter la vraie connexion MongoDB
        self::$mongo = new stdClass();
      } catch (Exception $e) {
        error_log("Erreur de connexion MongoDB : " . $e->getMessage());
      }
    }
    return self::$mongo;
  }

  /**
   * Exécuter une requête préparée avec gestion d'erreurs
   */
  public static function query($sql, $params = [])
  {
    try {
      $pdo = self::getPDO();
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt;
    } catch (PDOException $e) {
      error_log("Erreur SQL : " . $e->getMessage() . " - SQL: " . $sql);
      throw $e;
    }
  }

  /**
   * Récupérer un seul enregistrement
   */
  public static function fetchOne($sql, $params = [])
  {
    $stmt = self::query($sql, $params);
    return $stmt->fetch();
  }

  /**
   * Récupérer plusieurs enregistrements
   */
  public static function fetchAll($sql, $params = [])
  {
    $stmt = self::query($sql, $params);
    return $stmt->fetchAll();
  }

  /**
   * Insérer et récupérer l'ID
   */
  public static function insert($sql, $params = [])
  {
    self::query($sql, $params);
    return self::getPDO()->lastInsertId();
  }

  /**
   * Compter le nombre de lignes affectées
   */
  public static function count($sql, $params = [])
  {
    $stmt = self::query($sql, $params);
    return $stmt->rowCount();
  }
}
