<?php

/**
 * API de participation à un trajet
 *
 * Endpoint: POST /backend/trajets/participate.php
 * Nécessite une authentification
 *
 * Cette API correspond à l'US 6 (réservation d'un covoiturage)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier l'authentification
$user = requireAuth();

try {
  $trajetId = (int)($_GET['id'] ?? $_POST['trajet_id'] ?? 0);

  if ($trajetId <= 0) {
    jsonResponse(false, 'ID de trajet invalide');
  }

  // === GESTION DES DIFFERENTES METHODES ===

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
      participateToTrajet($user, $trajetId);
      break;

    case 'DELETE':
      cancelParticipation($user, $trajetId);
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur participate API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de l\'opération', null, 500);
}

/**
 * Gérer la participation à un trajet
 */
function participateToTrajet($user, $trajetId)
{
  // === RECUPERATION DES DONNEES ===

  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    jsonResponse(false, 'Données JSON invalides');
  }

  $nombrePlaces = (int)($input['nombre_places'] ?? 1);
  $pointPriseEnCharge = trim($input['point_prise_en_charge'] ?? '');
  $pointDepose = trim($input['point_depose'] ?? '');
  $message = trim($input['message'] ?? '');

  // === VALIDATION DES PARAMETRES ===

  $errors = [];

  if ($nombrePlaces < 1 || $nombrePlaces > 4) {
    $errors[] = 'Le nombre de places doit être entre 1 et 4';
  }

  if (empty($pointPriseEnCharge)) {
    $errors[] = 'Le point de prise en charge est requis';
  }

  if (empty($pointDepose)) {
    $errors[] = 'Le point de dépose est requis';
  }

  if (strlen($message) > 200) {
    $errors[] = 'Le message ne peut pas dépasser 200 caractères';
  }

  if (!empty($errors)) {
    jsonResponse(false, 'Erreurs de validation', ['errors' => $errors], 400);
  }

  // === VERIFICATIONS METIER ===

  // Récupérer le trajet
  $trajet = DB::findById('trajets', $trajetId);

  if (!$trajet) {
    jsonResponse(false, 'Trajet introuvable', null, 404);
  }

  // Vérifier que le trajet est disponible
  if ($trajet['statut'] !== 'planifie') {
    jsonResponse(false, 'Ce trajet n\'est plus disponible');
  }

  // Vérifier que c'est une date future
  $departDateTime = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']);
  if ($departDateTime <= time()) {
    jsonResponse(false, 'Impossible de participer à un trajet passé');
  }

  // Vérifier que l'utilisateur n'est pas le chauffeur
  if ($trajet['chauffeur_id'] == $user['id']) {
    jsonResponse(false, 'Vous ne pouvez pas participer à votre propre trajet');
  }

  // Vérifier que l'utilisateur ne participe pas déjà
  $existingParticipation = DB::findAll('participations', [
    'trajet_id' => $trajetId,
    'passager_id' => $user['id']
  ]);

  if (!empty($existingParticipation)) {
    jsonResponse(false, 'Vous participez déjà à ce trajet');
  }

  // Vérifier le nombre de places disponibles
  if ($trajet['nombre_places_restantes'] < $nombrePlaces) {
    jsonResponse(false, 'Pas assez de places disponibles (' . $trajet['nombre_places_restantes'] . ' restantes)');
  }

  // Vérifier les crédits de l'utilisateur
  $montantTotal = $trajet['prix'] * $nombrePlaces;
  if ($user['credits'] < $montantTotal) {
    jsonResponse(false, 'Crédits insuffisants. Montant requis: ' . $montantTotal . '€');
  }

  // === CREATION DE LA PARTICIPATION ===

  $participationData = [
    'trajet_id' => $trajetId,
    'passager_id' => $user['id'],
    'nombre_places' => $nombrePlaces,
    'point_prise_en_charge' => $pointPriseEnCharge,
    'point_depose' => $pointDepose,
    'message' => $message,
    'statut' => 'confirmee', // Auto-confirmation pour simplifier
    'montant' => $montantTotal,
    'date_creation' => date('Y-m-d H:i:s')
  ];

  $participationId = DB::insert('participations', $participationData);

  if (!$participationId) {
    jsonResponse(false, 'Erreur lors de la création de la participation');
  }

  // === MISE A JOUR DU TRAJET ===

  // Réduire le nombre de places restantes
  $newPlacesRestantes = $trajet['nombre_places_restantes'] - $nombrePlaces;
  DB::update('trajets', $trajetId, [
    'nombre_places_restantes' => $newPlacesRestantes
  ]);

  // === GESTION DES CREDITS ===

  // Débiter le passager
  $newCreditsPassager = $user['credits'] - $montantTotal;
  DB::update('utilisateurs', $user['id'], ['credits' => $newCreditsPassager]);

  // Enregistrer la transaction du passager
  $transactionPassagerData = [
    'utilisateur_id' => $user['id'],
    'type' => 'debit',
    'montant' => $montantTotal,
    'description' => 'Participation trajet #' . $trajetId,
    'trajet_id' => $trajetId,
    'date' => date('Y-m-d H:i:s')
  ];
  DB::insert('transactions', $transactionPassagerData);

  // Créditer le chauffeur (95% du montant, 5% de commission pour la plateforme)
  $commission = $montantTotal * 0.05;
  $montantChauffeur = $montantTotal - $commission;

  $chauffeur = DB::findById('utilisateurs', $trajet['chauffeur_id']);
  $newCreditsChauffeur = $chauffeur['credits'] + $montantChauffeur;
  DB::update('utilisateurs', $trajet['chauffeur_id'], ['credits' => $newCreditsChauffeur]);

  // Enregistrer la transaction du chauffeur
  $transactionChauffeurData = [
    'utilisateur_id' => $trajet['chauffeur_id'],
    'type' => 'credit',
    'montant' => $montantChauffeur,
    'description' => 'Gain trajet #' . $trajetId . ' (' . $nombrePlaces . ' place(s))',
    'trajet_id' => $trajetId,
    'date' => date('Y-m-d H:i:s')
  ];
  DB::insert('transactions', $transactionChauffeurData);

  // === NOTIFICATIONS (simulation) ===

  // TODO: Envoyer des notifications email/push
  // - Au chauffeur : nouvelle participation
  // - Au passager : confirmation de participation

  // === RECUPERATION DES DONNEES COMPLETES ===

  $participation = DB::findById('participations', $participationId);
  $trajetMisAJour = DB::findById('trajets', $trajetId);

  // === REPONSE ===

  $responseData = [
    'participation' => $participation,
    'montant_debite' => $montantTotal,
    'nouveau_solde' => $newCreditsPassager,
    'places_restantes' => $newPlacesRestantes,
    'commission_prelevee' => $commission,
    'statut_trajet' => $newPlacesRestantes == 0 ? 'complet' : 'disponible'
  ];

  jsonResponse(true, 'Participation confirmée avec succès', $responseData, 201);
}

/**
 * Annuler une participation
 */
function cancelParticipation($user, $trajetId)
{
  // === VERIFICATIONS ===

  // Récupérer la participation
  $participation = DB::findAll('participations', [
    'trajet_id' => $trajetId,
    'passager_id' => $user['id']
  ]);

  if (empty($participation)) {
    jsonResponse(false, 'Aucune participation trouvée pour ce trajet');
  }

  $participation = $participation[0];

  // Récupérer le trajet
  $trajet = DB::findById('trajets', $trajetId);

  if (!$trajet) {
    jsonResponse(false, 'Trajet introuvable', null, 404);
  }

  // Vérifier que l'annulation est possible (pas trop tard)
  $departDateTime = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']);
  $heuresAvantDepart = ($departDateTime - time()) / 3600;

  if ($heuresAvantDepart < 2) {
    jsonResponse(false, 'Annulation impossible moins de 2h avant le départ');
  }

  // === CALCUL DES FRAIS D'ANNULATION ===

  $fraisAnnulation = 0;
  if ($heuresAvantDepart < 24) {
    // Frais de 20% si annulation moins de 24h avant
    $fraisAnnulation = $participation['montant'] * 0.20;
  } elseif ($heuresAvantDepart < 48) {
    // Frais de 10% si annulation moins de 48h avant
    $fraisAnnulation = $participation['montant'] * 0.10;
  }
  // Pas de frais si annulation plus de 48h avant

  $montantRembourse = $participation['montant'] - $fraisAnnulation;

  // === SUPPRESSION DE LA PARTICIPATION ===

  DB::delete('participations', $participation['id']);

  // === MISE A JOUR DU TRAJET ===

  $newPlacesRestantes = $trajet['nombre_places_restantes'] + $participation['nombre_places'];
  DB::update('trajets', $trajetId, [
    'nombre_places_restantes' => $newPlacesRestantes
  ]);

  // === GESTION DES REMBOURSEMENTS ===

  // Créditer le passager (montant - frais)
  $newCreditsPassager = $user['credits'] + $montantRembourse;
  DB::update('utilisateurs', $user['id'], ['credits' => $newCreditsPassager]);

  // Enregistrer la transaction de remboursement
  if ($montantRembourse > 0) {
    $transactionRemboursementData = [
      'utilisateur_id' => $user['id'],
      'type' => 'credit',
      'montant' => $montantRembourse,
      'description' => 'Remboursement annulation trajet #' . $trajetId,
      'trajet_id' => $trajetId,
      'date' => date('Y-m-d H:i:s')
    ];
    DB::insert('transactions', $transactionRemboursementData);
  }

  // Enregistrer les frais d'annulation
  if ($fraisAnnulation > 0) {
    $transactionFraisData = [
      'utilisateur_id' => $user['id'],
      'type' => 'debit',
      'montant' => $fraisAnnulation,
      'description' => 'Frais annulation trajet #' . $trajetId,
      'trajet_id' => $trajetId,
      'date' => date('Y-m-d H:i:s')
    ];
    DB::insert('transactions', $transactionFraisData);
  }

  // Débiter le chauffeur (il perd le gain de cette participation)
  $commission = $participation['montant'] * 0.05;
  $montantChauffeur = $participation['montant'] - $commission;

  $chauffeur = DB::findById('utilisateurs', $trajet['chauffeur_id']);
  $newCreditsChauffeur = $chauffeur['credits'] - $montantChauffeur;
  DB::update('utilisateurs', $trajet['chauffeur_id'], ['credits' => $newCreditsChauffeur]);

  // Enregistrer la transaction de débit pour le chauffeur
  $transactionChauffeurData = [
    'utilisateur_id' => $trajet['chauffeur_id'],
    'type' => 'debit',
    'montant' => $montantChauffeur,
    'description' => 'Annulation participation trajet #' . $trajetId,
    'trajet_id' => $trajetId,
    'date' => date('Y-m-d H:i:s')
  ];
  DB::insert('transactions', $transactionChauffeurData);

  // === REPONSE ===

  $responseData = [
    'montant_rembourse' => $montantRembourse,
    'frais_annulation' => $fraisAnnulation,
    'nouveau_solde' => $newCreditsPassager,
    'places_liberees' => $participation['nombre_places'],
    'nouvelles_places_restantes' => $newPlacesRestantes
  ];

  jsonResponse(true, 'Participation annulée avec succès', $responseData);
}
