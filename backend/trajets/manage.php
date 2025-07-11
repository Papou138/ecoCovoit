<?php

/**
 * API de gestion du cycle de vie des trajets
 *
 * Endpoints:
 * - PUT /backend/trajets/manage.php?id=123&action=start (Démarrer un trajet)
 * - PUT /backend/trajets/manage.php?id=123&action=finish (Terminer un trajet)
 * - PUT /backend/trajets/manage.php?id=123&action=cancel (Annuler un trajet)
 *
 * Nécessite une authentification et d'être le chauffeur du trajet
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier que c'est une requête PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
  jsonResponse(false, 'Méthode non autorisée', null, 405);
}

// Vérifier l'authentification
$user = requireAuth();

try {
  // === RÉCUPÉRATION DES PARAMÈTRES ===

  $trajetId = (int)($_GET['id'] ?? 0);
  $action = $_GET['action'] ?? '';

  if ($trajetId <= 0) {
    jsonResponse(false, 'ID de trajet invalide');
  }

  if (!in_array($action, ['start', 'finish', 'cancel'])) {
    jsonResponse(false, 'Action invalide. Actions autorisées: start, finish, cancel');
  }

  // === VÉRIFICATIONS DE BASE ===

  $trajet = DB::findById('trajets', $trajetId);

  if (!$trajet) {
    jsonResponse(false, 'Trajet introuvable', null, 404);
  }

  // Vérifier que l'utilisateur est le chauffeur
  if ($trajet['chauffeur_id'] != $user['id']) {
    jsonResponse(false, 'Seul le chauffeur peut gérer ce trajet', null, 403);
  }

  // === TRAITEMENT SELON L'ACTION ===

  switch ($action) {
    case 'start':
      startTrajet($trajet, $user);
      break;

    case 'finish':
      finishTrajet($trajet, $user);
      break;

    case 'cancel':
      cancelTrajet($trajet, $user);
      break;
  }
} catch (Exception $e) {
  error_log("Erreur manage trajet API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la gestion du trajet', null, 500);
}

/**
 * Démarrer un trajet
 */
function startTrajet($trajet, $user)
{
  // === VALIDATIONS ===

  if ($trajet['statut'] !== 'planifie') {
    jsonResponse(false, 'Ce trajet ne peut pas être démarré (statut: ' . $trajet['statut'] . ')');
  }

  // Vérifier que la date/heure de départ est proche (pas plus de 30 min d'avance)
  $departDateTime = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']);
  $now = time();
  $diffMinutes = ($departDateTime - $now) / 60;

  if ($diffMinutes > 30) {
    jsonResponse(false, 'Impossible de démarrer le trajet plus de 30 minutes avant l\'heure prévue');
  }

  if ($diffMinutes < -60) {
    jsonResponse(false, 'Impossible de démarrer un trajet avec plus d\'1 heure de retard');
  }

  // === MISE À JOUR DU TRAJET ===

  $updateData = [
    'statut' => 'en_cours',
    'heure_depart_reelle' => date('H:i:s'),
    'date_mise_a_jour' => date('Y-m-d H:i:s')
  ];

  DB::update('trajets', $trajet['id'], $updateData);

  // === MISE À JOUR DES PARTICIPATIONS ===

  $participations = DB::findAll('participations', [
    'trajet_id' => $trajet['id'],
    'statut' => 'confirmee'
  ]);

  foreach ($participations as $participation) {
    DB::update('participations', $participation['id'], [
      'statut' => 'en_cours',
      'date_mise_a_jour' => date('Y-m-d H:i:s')
    ]);
  }

  // === NOTIFICATIONS ===

  // Notifier tous les participants que le trajet a démarré
  foreach ($participations as $participation) {
    createNotification(
      $participation['passager_id'],
      'trajet_demarre',
      "Le trajet {$trajet['depart']} → {$trajet['arrivee']} a démarré",
      $trajet['id']
    );
  }

  // === RÉPONSE ===

  $responseData = [
    'trajet_id' => $trajet['id'],
    'nouveau_statut' => 'en_cours',
    'heure_depart_reelle' => date('H:i'),
    'participants_notifies' => count($participations),
    'message' => 'Trajet démarré avec succès'
  ];

  jsonResponse(true, 'Trajet démarré avec succès', $responseData);
}

/**
 * Terminer un trajet
 */
function finishTrajet($trajet, $user)
{
  // === VALIDATIONS ===

  if ($trajet['statut'] !== 'en_cours') {
    jsonResponse(false, 'Ce trajet ne peut pas être terminé (statut: ' . $trajet['statut'] . ')');
  }

  // === RÉCUPÉRATION DES DONNÉES ===

  $input = json_decode(file_get_contents('php://input'), true) ?? [];

  $heureArriveeReelle = $input['heure_arrivee'] ?? date('H:i');
  $commentaireChauffeur = trim($input['commentaire'] ?? '');
  $kilometrage = (float)($input['kilometrage'] ?? 0);

  // === MISE À JOUR DU TRAJET ===

  $updateData = [
    'statut' => 'termine',
    'heure_arrivee_reelle' => $heureArriveeReelle,
    'commentaire_chauffeur' => $commentaireChauffeur,
    'kilometrage_reel' => $kilometrage,
    'date_fin' => date('Y-m-d H:i:s'),
    'date_mise_a_jour' => date('Y-m-d H:i:s')
  ];

  DB::update('trajets', $trajet['id'], $updateData);

  // === MISE À JOUR DES PARTICIPATIONS ===

  $participations = DB::findAll('participations', [
    'trajet_id' => $trajet['id'],
    'statut' => 'en_cours'
  ]);

  foreach ($participations as $participation) {
    DB::update('participations', $participation['id'], [
      'statut' => 'terminee',
      'date_fin' => date('Y-m-d H:i:s'),
      'date_mise_a_jour' => date('Y-m-d H:i:s')
    ]);
  }

  // === MISE À JOUR DES STATISTIQUES UTILISATEURS ===

  // Incrémenter le nombre de trajets effectués pour le chauffeur
  $chauffeur = DB::findById('utilisateurs', $user['id']);
  $newNbTrajets = ($chauffeur['nb_trajets_effectues'] ?? 0) + 1;
  DB::update('utilisateurs', $user['id'], [
    'nb_trajets_effectues' => $newNbTrajets
  ]);

  // Incrémenter pour chaque passager
  foreach ($participations as $participation) {
    $passager = DB::findById('utilisateurs', $participation['passager_id']);
    $newNbTrajetsPassager = ($passager['nb_trajets_effectues'] ?? 0) + 1;
    DB::update('utilisateurs', $participation['passager_id'], [
      'nb_trajets_effectues' => $newNbTrajetsPassager
    ]);
  }

  // === NOTIFICATIONS ===

  foreach ($participations as $participation) {
    createNotification(
      $participation['passager_id'],
      'trajet_termine',
      "Le trajet {$trajet['depart']} → {$trajet['arrivee']} s'est terminé. N'oubliez pas de laisser un avis !",
      $trajet['id']
    );
  }

  // === CALCUL DES ÉCONOMIES CO2 ===

  $economiesCO2 = 0;
  if ($kilometrage > 0) {
    // Estimation: 120g CO2/km en voiture solo vs covoiturage
    $facteurEconomie = count($participations) + 1; // +1 pour le chauffeur
    $economiesCO2 = round(($kilometrage * 120 * ($facteurEconomie - 1)) / 1000, 2); // kg CO2
  }

  // === RÉPONSE ===

  $responseData = [
    'trajet_id' => $trajet['id'],
    'nouveau_statut' => 'termine',
    'heure_arrivee_reelle' => $heureArriveeReelle,
    'participants_termines' => count($participations),
    'nouveau_nb_trajets_chauffeur' => $newNbTrajets,
    'economies_co2_kg' => $economiesCO2,
    'message' => 'Trajet terminé avec succès'
  ];

  jsonResponse(true, 'Trajet terminé avec succès', $responseData);
}

/**
 * Annuler un trajet
 */
function cancelTrajet($trajet, $user)
{
  // === VALIDATIONS ===

  if (!in_array($trajet['statut'], ['planifie', 'en_cours'])) {
    jsonResponse(false, 'Ce trajet ne peut pas être annulé (statut: ' . $trajet['statut'] . ')');
  }

  // === RÉCUPÉRATION DES DONNÉES ===

  $input = json_decode(file_get_contents('php://input'), true) ?? [];

  $raisonAnnulation = trim($input['raison'] ?? '');
  $remboursementIntegral = $input['remboursement_integral'] ?? false;

  if (empty($raisonAnnulation)) {
    jsonResponse(false, 'La raison de l\'annulation est requise');
  }

  // === RÉCUPÉRATION DES PARTICIPATIONS ===

  $participations = DB::findAll('participations', [
    'trajet_id' => $trajet['id'],
    'statut' => ['confirmee', 'en_cours']
  ]);

  // === CALCUL DES REMBOURSEMENTS ===

  $totalRembourse = 0;
  $participantsRembourses = 0;

  foreach ($participations as $participation) {
    $montantRemboursement = $participation['montant'];

    // Si pas de remboursement intégral, appliquer les frais selon le timing
    if (!$remboursementIntegral) {
      $departDateTime = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']);
      $heuresAvantDepart = ($departDateTime - time()) / 3600;

      if ($heuresAvantDepart < 2) {
        $montantRemboursement = $participation['montant'] * 0.5; // 50% si < 2h
      } elseif ($heuresAvantDepart < 24) {
        $montantRemboursement = $participation['montant'] * 0.8; // 80% si < 24h
      }
      // 100% si > 24h avant
    }

    // Rembourser le passager
    $passager = DB::findById('utilisateurs', $participation['passager_id']);
    $newCredits = $passager['credits'] + $montantRemboursement;
    DB::update('utilisateurs', $participation['passager_id'], ['credits' => $newCredits]);

    // Enregistrer la transaction de remboursement
    $transactionData = [
      'utilisateur_id' => $participation['passager_id'],
      'type' => 'credit',
      'montant' => $montantRemboursement,
      'description' => 'Remboursement annulation trajet #' . $trajet['id'],
      'trajet_id' => $trajet['id'],
      'date' => date('Y-m-d H:i:s')
    ];
    DB::insert('transactions', $transactionData);

    // Mettre à jour la participation
    DB::update('participations', $participation['id'], [
      'statut' => 'annulee',
      'montant_rembourse' => $montantRemboursement,
      'date_annulation' => date('Y-m-d H:i:s')
    ]);

    // Notification au passager
    createNotification(
      $participation['passager_id'],
      'trajet_annule',
      "Le trajet {$trajet['depart']} → {$trajet['arrivee']} a été annulé. Remboursement: {$montantRemboursement}€",
      $trajet['id']
    );

    $totalRembourse += $montantRemboursement;
    $participantsRembourses++;
  }

  // === GESTION DES PÉNALITÉS CHAUFFEUR ===

  $penaliteChauffeur = 0;
  if (!$remboursementIntegral) {
    // Pénalité de 5€ pour annulation tardive
    $departDateTime = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']);
    $heuresAvantDepart = ($departDateTime - time()) / 3600;

    if ($heuresAvantDepart < 24) {
      $penaliteChauffeur = 5.0;
      $newCreditsChauffeur = $user['credits'] - $penaliteChauffeur;
      DB::update('utilisateurs', $user['id'], ['credits' => $newCreditsChauffeur]);

      // Transaction de pénalité
      $transactionPenaliteData = [
        'utilisateur_id' => $user['id'],
        'type' => 'debit',
        'montant' => $penaliteChauffeur,
        'description' => 'Pénalité annulation tardive trajet #' . $trajet['id'],
        'trajet_id' => $trajet['id'],
        'date' => date('Y-m-d H:i:s')
      ];
      DB::insert('transactions', $transactionPenaliteData);
    }
  }

  // === MISE À JOUR DU TRAJET ===

  $updateData = [
    'statut' => 'annule',
    'raison_annulation' => $raisonAnnulation,
    'date_annulation' => date('Y-m-d H:i:s'),
    'remboursement_integral' => $remboursementIntegral,
    'date_mise_a_jour' => date('Y-m-d H:i:s')
  ];

  DB::update('trajets', $trajet['id'], $updateData);

  // === RÉPONSE ===

  $responseData = [
    'trajet_id' => $trajet['id'],
    'nouveau_statut' => 'annule',
    'participants_rembourses' => $participantsRembourses,
    'total_rembourse' => $totalRembourse,
    'penalite_chauffeur' => $penaliteChauffeur,
    'remboursement_integral' => $remboursementIntegral,
    'message' => 'Trajet annulé et participants remboursés'
  ];

  jsonResponse(true, 'Trajet annulé avec succès', $responseData);
}

/**
 * Créer une notification pour un utilisateur
 */
function createNotification($userId, $type, $message, $trajetId = null)
{
  $notificationData = [
    'utilisateur_id' => $userId,
    'type' => $type,
    'message' => $message,
    'trajet_id' => $trajetId,
    'lu' => false,
    'date_creation' => date('Y-m-d H:i:s')
  ];

  return DB::insert('notifications', $notificationData);
}
