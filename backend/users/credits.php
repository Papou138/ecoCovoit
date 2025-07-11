<?php

/**
 * API de gestion des crédits utilisateur
 *
 * Endpoint: GET/POST /backend/users/credits.php
 * - GET: Récupérer le solde et l'historique des transactions
 * - POST: Effectuer des opérations sur les crédits (pour admin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $method = $_SERVER['REQUEST_METHOD'];

  switch ($method) {
    case 'GET':
      getUserCredits();
      break;

    case 'POST':
      manageCredits();
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur credits API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}

/**
 * Récupère le solde et l'historique des crédits
 */
function getUserCredits()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Paramètres de pagination
  $page = (int)($_GET['page'] ?? 1);
  $limit = (int)($_GET['limit'] ?? 20);
  $offset = ($page - 1) * $limit;

  // Récupérer toutes les transactions de l'utilisateur
  $allTransactions = DB::findAll('transactions', ['utilisateur_id' => $currentUser['id']]);

  // Trier par date décroissante (simuler ORDER BY pour la version mock)
  if (!empty($allTransactions)) {
    usort($allTransactions, function ($a, $b) {
      return strtotime($b['date_transaction']) - strtotime($a['date_transaction']);
    });
  }

  // Pagination
  $totalTransactions = count($allTransactions);
  $transactions = array_slice($allTransactions, $offset, $limit);

  // Calculer les statistiques
  $totalCredite = 0;
  $totalDebite = 0;
  $totalCommissions = 0;

  foreach ($allTransactions as $transaction) {
    switch ($transaction['type']) {
      case 'credit':
        $totalCredite += $transaction['montant'];
        break;
      case 'debit':
        $totalDebite += $transaction['montant'];
        break;
      case 'commission':
        $totalCommissions += $transaction['montant'];
        break;
    }
  }

  // Enrichir les transactions avec des détails
  foreach ($transactions as &$transaction) {
    // Ajouter le type d'icône pour l'affichage
    $transaction['icon'] = getTransactionIcon($transaction['type']);
    $transaction['color'] = getTransactionColor($transaction['type']);

    // Formater la date
    $transaction['date_formatted'] = date('d/m/Y H:i', strtotime($transaction['date_transaction']));

    // Ajouter des détails sur le trajet si applicable
    if ($transaction['trajet_id']) {
      $trajet = DB::findById('trajets', $transaction['trajet_id']);
      if ($trajet) {
        $transaction['trajet_details'] = [
          'depart' => $trajet['ville_depart'],
          'arrivee' => $trajet['ville_arrivee'],
          'date' => date('d/m/Y', strtotime($trajet['date_depart']))
        ];
      }
    }
  }

  AuthMiddleware::logAction('view_credits');

  $responseData = [
    'solde_actuel' => $currentUser['credits'],
    'statistiques' => [
      'total_credite' => $totalCredite,
      'total_debite' => $totalDebite,
      'total_commissions' => $totalCommissions,
      'nombre_transactions' => $totalTransactions
    ],
    'transactions' => $transactions,
    'pagination' => [
      'page_actuelle' => $page,
      'total_pages' => ceil($totalTransactions / $limit),
      'total_transactions' => $totalTransactions,
      'limite_par_page' => $limit
    ]
  ];

  jsonResponse(true, 'Historique des crédits récupéré avec succès', $responseData);
}

/**
 * Gère les opérations sur les crédits (admin uniquement)
 */
function manageCredits()
{
  // Vérifier les permissions admin
  AuthMiddleware::requireAdmin();

  // Récupération des données
  $input = json_decode(file_get_contents('php://input'), true);

  $targetUserId = (int)($input['user_id'] ?? 0);
  $operation = $input['operation'] ?? ''; // 'credit' ou 'debit'
  $montant = (int)($input['montant'] ?? 0);
  $description = trim($input['description'] ?? '');

  // Validation
  if (!$targetUserId) {
    jsonResponse(false, 'ID utilisateur requis');
  }

  if (!in_array($operation, ['credit', 'debit'])) {
    jsonResponse(false, 'Opération invalide (credit ou debit)');
  }

  if ($montant <= 0 || $montant > 1000) {
    jsonResponse(false, 'Montant invalide (1-1000 crédits)');
  }

  if (empty($description)) {
    jsonResponse(false, 'Description requise');
  }

  // Récupérer l'utilisateur cible
  $targetUser = DB::findById('utilisateurs', $targetUserId);
  if (!$targetUser) {
    jsonResponse(false, 'Utilisateur introuvable');
  }

  // Calculer le nouveau solde
  $soldeBefore = $targetUser['credits'];

  if ($operation === 'credit') {
    $soldeAfter = $soldeBefore + $montant;
  } else {
    if ($soldeBefore < $montant) {
      jsonResponse(false, 'Solde insuffisant pour cette opération');
    }
    $soldeAfter = $soldeBefore - $montant;
  }

  // Créer la transaction
  $transactionData = [
    'utilisateur_id' => $targetUserId,
    'type' => $operation,
    'montant' => $montant,
    'solde_avant' => $soldeBefore,
    'solde_apres' => $soldeAfter,
    'description' => "Admin: $description",
    'date_transaction' => date('Y-m-d H:i:s')
  ];

  $transactionId = DB::insert('transactions', $transactionData);

  if (!$transactionId) {
    jsonResponse(false, 'Erreur lors de la création de la transaction', null, 500);
  }

  // Mettre à jour le solde de l'utilisateur
  $success = DB::update('utilisateurs', $targetUserId, ['credits' => $soldeAfter]);

  if (!$success) {
    jsonResponse(false, 'Erreur lors de la mise à jour du solde', null, 500);
  }

  AuthMiddleware::logAction('admin_manage_credits', [
    'target_user_id' => $targetUserId,
    'operation' => $operation,
    'montant' => $montant,
    'transaction_id' => $transactionId
  ]);

  jsonResponse(true, "Opération réussie: $operation de $montant crédits", [
    'transaction' => $transactionData,
    'nouveau_solde' => $soldeAfter
  ]);
}

/**
 * Fonction utilitaire pour traiter une transaction de crédit/débit
 */
function processTransaction($userId, $type, $montant, $description, $trajetId = null)
{
  // Récupérer l'utilisateur
  $user = DB::findById('utilisateurs', $userId);
  if (!$user) {
    throw new Exception('Utilisateur introuvable');
  }

  $soldeBefore = $user['credits'];

  // Calculer le nouveau solde
  switch ($type) {
    case 'credit':
      $soldeAfter = $soldeBefore + $montant;
      break;
    case 'debit':
    case 'commission':
      if ($soldeBefore < $montant) {
        throw new Exception('Solde insuffisant');
      }
      $soldeAfter = $soldeBefore - $montant;
      break;
    default:
      throw new Exception('Type de transaction invalide');
  }

  // Créer la transaction
  $transactionData = [
    'utilisateur_id' => $userId,
    'type' => $type,
    'montant' => $montant,
    'solde_avant' => $soldeBefore,
    'solde_apres' => $soldeAfter,
    'trajet_id' => $trajetId,
    'description' => $description,
    'date_transaction' => date('Y-m-d H:i:s')
  ];

  $transactionId = DB::insert('transactions', $transactionData);

  if (!$transactionId) {
    throw new Exception('Erreur lors de la création de la transaction');
  }

  // Mettre à jour le solde
  $success = DB::update('utilisateurs', $userId, ['credits' => $soldeAfter]);

  if (!$success) {
    throw new Exception('Erreur lors de la mise à jour du solde');
  }

  return [
    'transaction_id' => $transactionId,
    'solde_before' => $soldeBefore,
    'solde_after' => $soldeAfter
  ];
}

/**
 * Obtient l'icône pour un type de transaction
 */
function getTransactionIcon($type)
{
  switch ($type) {
    case 'credit':
      return '💰';
    case 'debit':
      return '💸';
    case 'commission':
      return '🏢';
    default:
      return '📝';
  }
}

/**
 * Obtient la couleur pour un type de transaction
 */
function getTransactionColor($type)
{
  switch ($type) {
    case 'credit':
      return 'green';
    case 'debit':
      return 'red';
    case 'commission':
      return 'orange';
    default:
      return 'gray';
  }
}
