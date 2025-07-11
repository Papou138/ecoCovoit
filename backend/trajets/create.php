<?php

/**
 * API de création de trajets
 *
 * Endpoint: POST /backend/trajets/create.php
 * Nécessite une authentification
 *
 * Cette API correspond aux US 7-8 (gestion de trajets par chauffeur)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Méthode non autorisée', null, 405);
}

// Vérifier l'authentification
$user = requireAuth();

try {
  // === RÉCUPÉRATION DES DONNÉES ===

  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    jsonResponse(false, 'Données JSON invalides');
  }

  // Extraire les champs requis
  $depart = trim($input['depart'] ?? '');
  $arrivee = trim($input['arrivee'] ?? '');
  $dateDepart = trim($input['date_depart'] ?? '');
  $heureDepart = trim($input['heure_depart'] ?? '');
  $nombrePlaces = (int)($input['nombre_places'] ?? 0);
  $prix = (float)($input['prix'] ?? 0);
  $vehiculeId = (int)($input['vehicule_id'] ?? 0);

  // Champs optionnels
  $description = trim($input['description'] ?? '');
  $pointsPassage = $input['points_passage'] ?? [];
  $retourPrevu = $input['retour_prevu'] ?? false;
  $dateRetour = trim($input['date_retour'] ?? '');
  $heureRetour = trim($input['heure_retour'] ?? '');
  $accepteAnimaux = $input['accepte_animaux'] ?? false;
  $accepteFumeurs = $input['accepte_fumeurs'] ?? false;
  $accepteBagages = $input['accepte_bagages'] ?? true;
  $maxDetour = (int)($input['max_detour'] ?? 10); // km de détour max

  // === VALIDATION DES DONNÉES ===

  $errors = [];

  // Validation départ/arrivée
  if (empty($depart)) {
    $errors[] = 'La ville de départ est requise';
  }

  if (empty($arrivee)) {
    $errors[] = 'La ville d\'arrivée est requise';
  }

  if (strtolower($depart) === strtolower($arrivee)) {
    $errors[] = 'Les villes de départ et d\'arrivée doivent être différentes';
  }

  // Validation date et heure
  if (empty($dateDepart)) {
    $errors[] = 'La date de départ est requise';
  } else {
    $dateObj = DateTime::createFromFormat('Y-m-d', $dateDepart);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $dateDepart) {
      $errors[] = 'Format de date invalide (YYYY-MM-DD attendu)';
    } else {
      // Vérifier que c'est une date future
      $now = new DateTime();
      $departDateTime = DateTime::createFromFormat('Y-m-d H:i', $dateDepart . ' ' . $heureDepart);

      if ($departDateTime <= $now) {
        $errors[] = 'La date et heure de départ doivent être dans le futur';
      }
    }
  }

  if (empty($heureDepart)) {
    $errors[] = 'L\'heure de départ est requise';
  } else {
    $heureObj = DateTime::createFromFormat('H:i', $heureDepart);
    if (!$heureObj || $heureObj->format('H:i') !== $heureDepart) {
      $errors[] = 'Format d\'heure invalide (HH:MM attendu)';
    }
  }

  // Validation places et prix
  if ($nombrePlaces < 1 || $nombrePlaces > 8) {
    $errors[] = 'Le nombre de places doit être entre 1 et 8';
  }

  if ($prix < 0 || $prix > 500) {
    $errors[] = 'Le prix doit être entre 0 et 500€';
  }

  // Validation véhicule
  if ($vehiculeId <= 0) {
    $errors[] = 'Un véhicule valide doit être sélectionné';
  } else {
    $vehicule = DB::findById('vehicules', $vehiculeId);
    if (!$vehicule || $vehicule['proprietaire_id'] != $user['id']) {
      $errors[] = 'Véhicule introuvable ou vous n\'en êtes pas le propriétaire';
    }
  }

  // Validation du retour si prévu
  if ($retourPrevu) {
    if (empty($dateRetour) || empty($heureRetour)) {
      $errors[] = 'Date et heure de retour requises si retour prévu';
    } else {
      $retourDateTime = DateTime::createFromFormat('Y-m-d H:i', $dateRetour . ' ' . $heureRetour);
      $departDateTime = DateTime::createFromFormat('Y-m-d H:i', $dateDepart . ' ' . $heureDepart);

      if ($retourDateTime <= $departDateTime) {
        $errors[] = 'La date de retour doit être après la date de départ';
      }
    }
  }

  // Validation description
  if (strlen($description) > 500) {
    $errors[] = 'La description ne peut pas dépasser 500 caractères';
  }

  // Validation points de passage
  if (!empty($pointsPassage) && !is_array($pointsPassage)) {
    $errors[] = 'Les points de passage doivent être un tableau';
  } elseif (count($pointsPassage) > 5) {
    $errors[] = 'Maximum 5 points de passage autorisés';
  }

  if (!empty($errors)) {
    jsonResponse(false, 'Erreurs de validation', ['errors' => $errors], 400);
  }

  // === VÉRIFICATIONS MÉTIER ===

  // Vérifier que l'utilisateur n'a pas déjà un trajet à la même date/heure
  $existingTrajets = DB::query(
    "SELECT * FROM trajets WHERE chauffeur_id = ? AND date_depart = ? AND heure_depart = ? AND statut != 'annule'",
    [$user['id'], $dateDepart, $heureDepart]
  );

  if (!empty($existingTrajets)) {
    jsonResponse(false, 'Vous avez déjà un trajet prévu à cette date et heure');
  }

  // Vérifier les crédits pour la création (commission de 2€)
  $commission = 2.00;
  if ($user['credits'] < $commission) {
    jsonResponse(false, 'Crédits insuffisants pour créer un trajet (commission: ' . $commission . '€)');
  }

  // === CRÉATION DU TRAJET ===

  // Données du trajet principal
  $trajetData = [
    'chauffeur_id' => $user['id'],
    'depart' => $depart,
    'arrivee' => $arrivee,
    'date_depart' => $dateDepart,
    'heure_depart' => $heureDepart,
    'nombre_places' => $nombrePlaces,
    'nombre_places_restantes' => $nombrePlaces,
    'prix' => $prix,
    'vehicule_id' => $vehiculeId,
    'description' => $description,
    'accepte_animaux' => $accepteAnimaux,
    'accepte_fumeurs' => $accepteFumeurs,
    'accepte_bagages' => $accepteBagages,
    'max_detour' => $maxDetour,
    'statut' => 'planifie',
    'date_creation' => date('Y-m-d H:i:s'),
    'duree_estimee' => estimateJourneyDuration($depart, $arrivee)
  ];

  // Insérer le trajet
  $trajetId = DB::insert('trajets', $trajetData);

  if (!$trajetId) {
    jsonResponse(false, 'Erreur lors de la création du trajet');
  }

  // === GESTION DES POINTS DE PASSAGE ===

  if (!empty($pointsPassage)) {
    foreach ($pointsPassage as $index => $point) {
      if (!empty(trim($point))) {
        $pointData = [
          'trajet_id' => $trajetId,
          'ville' => trim($point),
          'ordre' => $index + 1
        ];
        DB::insert('points_passage', $pointData);
      }
    }
  }

  // === CRÉATION DU TRAJET RETOUR ===

  $trajetRetourId = null;
  if ($retourPrevu) {
    $trajetRetourData = [
      'chauffeur_id' => $user['id'],
      'depart' => $arrivee, // Inverse départ/arrivée
      'arrivee' => $depart,
      'date_depart' => $dateRetour,
      'heure_depart' => $heureRetour,
      'nombre_places' => $nombrePlaces,
      'nombre_places_restantes' => $nombrePlaces,
      'prix' => $prix,
      'vehicule_id' => $vehiculeId,
      'description' => $description . ' (Trajet retour)',
      'accepte_animaux' => $accepteAnimaux,
      'accepte_fumeurs' => $accepteFumeurs,
      'accepte_bagages' => $accepteBagages,
      'max_detour' => $maxDetour,
      'statut' => 'planifie',
      'date_creation' => date('Y-m-d H:i:s'),
      'trajet_aller_id' => $trajetId,
      'duree_estimee' => estimateJourneyDuration($arrivee, $depart)
    ];

    $trajetRetourId = DB::insert('trajets', $trajetRetourData);

    // Mettre à jour le trajet aller avec l'ID du retour
    DB::update('trajets', $trajetId, ['trajet_retour_id' => $trajetRetourId]);
  }

  // === DÉDUCTION DE LA COMMISSION ===

  // Débiter la commission
  $newCredits = $user['credits'] - $commission;
  DB::update('utilisateurs', $user['id'], ['credits' => $newCredits]);

  // Enregistrer la transaction
  $transactionData = [
    'utilisateur_id' => $user['id'],
    'type' => 'debit',
    'montant' => $commission,
    'description' => 'Commission création trajet #' . $trajetId,
    'trajet_id' => $trajetId,
    'date' => date('Y-m-d H:i:s')
  ];
  DB::insert('transactions', $transactionData);

  // === RÉCUPÉRATION DES DONNÉES COMPLÈTES ===

  $trajetComplete = DB::query(
    "SELECT t.*, u.nom, u.prenom, u.email, u.note_moyenne,
                v.marque, v.modele, v.couleur, v.immatriculation, v.type_carburant
         FROM trajets t
         JOIN utilisateurs u ON t.chauffeur_id = u.id
         JOIN vehicules v ON t.vehicule_id = v.id
         WHERE t.id = ?",
    [$trajetId]
  )[0] ?? null;

  if ($trajetComplete) {
    $trajetComplete['est_ecologique'] = in_array($trajetComplete['type_carburant'], ['électrique', 'hybride']);
    $trajetComplete['points_passage'] = DB::query(
      "SELECT ville, ordre FROM points_passage WHERE trajet_id = ? ORDER BY ordre",
      [$trajetId]
    );
  }

  // === RÉPONSE ===

  $responseData = [
    'trajet' => $trajetComplete,
    'trajet_retour_id' => $trajetRetourId,
    'nouveau_solde' => $newCredits,
    'commission_debitee' => $commission
  ];

  jsonResponse(true, 'Trajet créé avec succès', $responseData, 201);
} catch (Exception $e) {
  error_log("Erreur create trajet API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la création du trajet', null, 500);
}

/**
 * Estime la durée d'un trajet en minutes
 */
function estimateJourneyDuration($depart, $arrivee)
{
  // Estimation basique basée sur des distances approximatives
  $distances = [
    'paris-lyon' => 120,
    'paris-marseille' => 180,
    'lyon-marseille' => 80,
    'paris-bordeaux' => 150,
    'lyon-bordeaux' => 140,
    'toulouse-bordeaux' => 60
  ];

  $key = strtolower($depart) . '-' . strtolower($arrivee);
  $reverseKey = strtolower($arrivee) . '-' . strtolower($depart);

  if (isset($distances[$key])) {
    return $distances[$key];
  } elseif (isset($distances[$reverseKey])) {
    return $distances[$reverseKey];
  }

  // Estimation par défaut
  return 90;
}
