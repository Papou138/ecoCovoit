<?php

/**
 * Middleware d'authentification
 *
 * Fonctions utilitaires pour vérifier l'authentification
 * et les autorisations dans les APIs
 */

require_once __DIR__ . '/../config/config.php';

class AuthMiddleware
{

  /**
   * Vérifie si l'utilisateur est connecté
   */
  public static function requireAuth()
  {
    session_start();

    if (!isset($_SESSION['user_id'])) {
      jsonResponse(false, 'Authentification requise', null, 401);
    }

    // Vérifier la durée de la session
    if (isset($_SESSION['login_time'])) {
      $sessionAge = time() - $_SESSION['login_time'];
      if ($sessionAge > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        jsonResponse(false, 'Session expirée', null, 401);
      }
    }

    return $_SESSION['user_id'];
  }

  /**
   * Vérifie si l'utilisateur a un rôle spécifique
   */
  public static function requireRole($requiredRole)
  {
    $userId = self::requireAuth();

    if (!isset($_SESSION['role'])) {
      jsonResponse(false, 'Rôle non défini', null, 403);
    }

    $userRole = $_SESSION['role'];

    // Hiérarchie des rôles : admin > employe > utilisateur
    $roleHierarchy = [
      'utilisateur' => 1,
      'employe' => 2,
      'admin' => 3
    ];

    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

    if ($userLevel < $requiredLevel) {
      jsonResponse(false, 'Permissions insuffisantes', null, 403);
    }

    return $userId;
  }

  /**
   * Vérifie si l'utilisateur est admin
   */
  public static function requireAdmin()
  {
    return self::requireRole('admin');
  }

  /**
   * Vérifie si l'utilisateur est employé ou admin
   */
  public static function requireEmployee()
  {
    return self::requireRole('employe');
  }

  /**
   * Vérifie si l'utilisateur peut accéder à un profil
   * (son propre profil ou admin)
   */
  public static function canAccessProfile($targetUserId)
  {
    $currentUserId = self::requireAuth();

    // L'utilisateur peut accéder à son propre profil
    if ($currentUserId == $targetUserId) {
      return true;
    }

    // Les admins peuvent accéder à tous les profils
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
      return true;
    }

    jsonResponse(false, 'Accès non autorisé à ce profil', null, 403);
  }

  /**
   * Vérifie si l'utilisateur peut modifier un trajet
   */
  public static function canModifyTrajet($trajetId)
  {
    require_once __DIR__ . '/../models/DB.php';

    $currentUserId = self::requireAuth();

    // Récupérer le trajet
    $trajet = DB::findById('trajets', $trajetId);

    if (!$trajet) {
      jsonResponse(false, 'Trajet introuvable', null, 404);
    }

    // Le chauffeur peut modifier son trajet
    if ($trajet['chauffeur_id'] == $currentUserId) {
      return true;
    }

    // Les admins peuvent modifier tous les trajets
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
      return true;
    }

    jsonResponse(false, 'Vous ne pouvez pas modifier ce trajet', null, 403);
  }

  /**
   * Récupère les informations de l'utilisateur connecté
   */
  public static function getCurrentUser()
  {
    require_once __DIR__ . '/../models/DB.php';

    $userId = self::requireAuth();

    $user = DB::findById('utilisateurs', $userId);

    if (!$user) {
      session_unset();
      session_destroy();
      jsonResponse(false, 'Utilisateur introuvable', null, 404);
    }

    // Vérifier si le compte n'est pas suspendu
    if ($user['statut'] === 'suspendu') {
      session_unset();
      session_destroy();
      jsonResponse(false, 'Compte suspendu', null, 403);
    }

    return $user;
  }

  /**
   * Logs l'action de l'utilisateur (pour audit)
   */
  public static function logAction($action, $details = [])
  {
    try {
      if (isset($_SESSION['user_id'])) {
        $logData = [
          'user_id' => $_SESSION['user_id'],
          'action' => $action,
          'details' => json_encode($details),
          'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
          'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
          'timestamp' => date('Y-m-d H:i:s')
        ];

        // Pour l'instant, on log dans error_log
        error_log("USER_ACTION: " . json_encode($logData));
      }
    } catch (Exception $e) {
      // Ne pas faire échouer l'action si le log échoue
      error_log("Error logging action: " . $e->getMessage());
    }
  }
}
