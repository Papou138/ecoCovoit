<?php

/**
 * API d'historique des trajets
 *
 * Endpoint: GET /backend/trajets/historique.php
 * Nécessite une authentification
 *
 * Paramètres optionnels:
 * - type: 'chauffeur', 'passager', 'all' (défaut: all)
 * - statut: 'termine', 'annule', 'all' (défaut: all)
 * - limite: nombre de résultats (défaut: 20)
 * - page: numéro de page (défaut: 1)
 * - depuis: date de début (YYYY-MM-DD)
 * - jusqu: date de fin (YYYY-MM-DD)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Vérifier que c'est une requête GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  jsonResponse(false, 'Méthode non autorisée', null, 405);
}

// Vérifier l'authentification
$user = requireAuth();

try {
  // === RÉCUPÉRATION DES PARAMÈTRES ===

  $type = $_GET['type'] ?? 'all'; // 'chauffeur', 'passager', 'all'
  $statut = $_GET['statut'] ?? 'all'; // 'termine', 'annule', 'all'
  $limite = min((int)($_GET['limite'] ?? 20), 100); // Max 100 résultats par page
  $page = max((int)($_GET['page'] ?? 1), 1);
  $depuis = $_GET['depuis'] ?? null;
  $jusqu = $_GET['jusqu'] ?? null;

  // === VALIDATION DES PARAMÈTRES ===

  if (!in_array($type, ['chauffeur', 'passager', 'all'])) {
    jsonResponse(false, 'Type invalide. Valeurs autorisées: chauffeur, passager, all');
  }

  if (!in_array($statut, ['termine', 'annule', 'all'])) {
    jsonResponse(false, 'Statut invalide. Valeurs autorisées: termine, annule, all');
  }

  // Validation des dates
  if ($depuis && !DateTime::createFromFormat('Y-m-d', $depuis)) {
    jsonResponse(false, 'Format de date invalide pour "depuis" (YYYY-MM-DD attendu)');
  }

  if ($jusqu && !DateTime::createFromFormat('Y-m-d', $jusqu)) {
    jsonResponse(false, 'Format de date invalide pour "jusqu" (YYYY-MM-DD attendu)');
  }

  // === RÉCUPÉRATION DES TRAJETS COMME CHAUFFEUR ===

  $trajetsChaufeur = [];
  if ($type === 'chauffeur' || $type === 'all') {
    $trajetsChaufeur = getTrajetsAsDriver($user['id'], $statut, $depuis, $jusqu);
  }

  // === RÉCUPÉRATION DES TRAJETS COMME PASSAGER ===

  $trajetsPassager = [];
  if ($type === 'passager' || $type === 'all') {
    $trajetsPassager = getTrajetsAsPassenger($user['id'], $statut, $depuis, $jusqu);
  }

  // === FUSION ET TRI DES RÉSULTATS ===

  $allTrajets = array_merge($trajetsChaufeur, $trajetsPassager);

  // Trier par date décroissante (plus récents en premier)
  usort($allTrajets, function ($a, $b) {
    $dateA = strtotime($a['date_depart']);
    $dateB = strtotime($b['date_depart']);
    return $dateB <=> $dateA;
  });

  // === PAGINATION ===

  $totalTrajets = count($allTrajets);
  $totalPages = ceil($totalTrajets / $limite);
  $offset = ($page - 1) * $limite;

  $trajetsPagines = array_slice($allTrajets, $offset, $limite);

  // === CALCUL DES STATISTIQUES ===

  $stats = calculateUserStats($user['id'], $trajetsChaufeur, $trajetsPassager);

  // === RÉPONSE ===

  $responseData = [
    'trajets' => $trajetsPagines,
    'pagination' => [
      'page_actuelle' => $page,
      'total_pages' => $totalPages,
      'total_trajets' => $totalTrajets,
      'limite_par_page' => $limite,
      'a_page_suivante' => $page < $totalPages,
      'a_page_precedente' => $page > 1
    ],
    'filtres_appliques' => [
      'type' => $type,
      'statut' => $statut,
      'periode' => [
        'depuis' => $depuis,
        'jusqu' => $jusqu
      ]
    ],
    'statistiques' => $stats
  ];

  jsonResponse(true, 'Historique récupéré avec succès', $responseData);
} catch (Exception $e) {
  error_log("Erreur historique API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la récupération de l\'historique', null, 500);
}

/**
 * Récupère les trajets où l'utilisateur était chauffeur
 */
function getTrajetsAsDriver($userId, $statut, $depuis, $jusqu)
{
  $trajets = DB::findAll('trajets', ['chauffeur_id' => $userId]);
  $results = [];

  foreach ($trajets as $trajet) {
    // Filtrer par statut
    if ($statut !== 'all' && $trajet['statut'] !== $statut) {
      continue;
    }

    // Filtrer par dates
    if ($depuis && $trajet['date_depart'] < $depuis) {
      continue;
    }

    if ($jusqu && $trajet['date_depart'] > $jusqu) {
      continue;
    }

    // Enrichir avec les données du véhicule
    $vehicule = DB::findById('vehicules', $trajet['vehicule_id']);

    // Compter les participants
    $participations = DB::findAll('participations', [
      'trajet_id' => $trajet['id']
    ]);

    $participantsActifs = array_filter($participations, function ($p) {
      return in_array($p['statut'], ['confirmee', 'en_cours', 'terminee']);
    });

    // Calculer les gains
    $gainsTotal = 0;
    foreach ($participantsActifs as $participation) {
      $commission = $participation['montant'] * 0.05; // 5% de commission plateforme
      $gainsTotal += $participation['montant'] - $commission;
    }

    $trajetEnrichi = $trajet;
    $trajetEnrichi['role'] = 'chauffeur';
    $trajetEnrichi['vehicule'] = $vehicule;
    $trajetEnrichi['nombre_participants'] = count($participantsActifs);
    $trajetEnrichi['gains_total'] = $gainsTotal;
    $trajetEnrichi['gains_formatted'] = number_format($gainsTotal, 2, ',', ' ') . ' €';
    $trajetEnrichi['date_formatted'] = date('d/m/Y', strtotime($trajet['date_depart']));
    $trajetEnrichi['heure_formatted'] = date('H:i', strtotime($trajet['heure_depart']));

    // Statut détaillé
    $trajetEnrichi['statut_detail'] = getStatutDetail($trajet['statut'], true);

    $results[] = $trajetEnrichi;
  }

  return $results;
}

/**
 * Récupère les trajets où l'utilisateur était passager
 */
function getTrajetsAsPassenger($userId, $statut, $depuis, $jusqu)
{
  $participations = DB::findAll('participations', ['passager_id' => $userId]);
  $results = [];

  foreach ($participations as $participation) {
    // Filtrer par statut de la participation
    if ($statut !== 'all') {
      $statusMapping = [
        'termine' => ['terminee'],
        'annule' => ['annulee']
      ];

      if (!in_array($participation['statut'], $statusMapping[$statut] ?? [])) {
        continue;
      }
    }

    $trajet = DB::findById('trajets', $participation['trajet_id']);
    if (!$trajet) continue;

    // Filtrer par dates
    if ($depuis && $trajet['date_depart'] < $depuis) {
      continue;
    }

    if ($jusqu && $trajet['date_depart'] > $jusqu) {
      continue;
    }

    // Enrichir avec les données du chauffeur
    $chauffeur = DB::findById('utilisateurs', $trajet['chauffeur_id']);
    $vehicule = DB::findById('vehicules', $trajet['vehicule_id']);

    $trajetEnrichi = $trajet;
    $trajetEnrichi['role'] = 'passager';
    $trajetEnrichi['chauffeur'] = [
      'nom' => $chauffeur['nom'],
      'prenom' => $chauffeur['prenom'],
      'note_moyenne' => $chauffeur['note_moyenne']
    ];
    $trajetEnrichi['vehicule'] = $vehicule;
    $trajetEnrichi['participation'] = $participation;
    $trajetEnrichi['cout_total'] = $participation['montant'];
    $trajetEnrichi['cout_formatted'] = number_format($participation['montant'], 2, ',', ' ') . ' €';
    $trajetEnrichi['date_formatted'] = date('d/m/Y', strtotime($trajet['date_depart']));
    $trajetEnrichi['heure_formatted'] = date('H:i', strtotime($trajet['heure_depart']));

    // Statut détaillé
    $trajetEnrichi['statut_detail'] = getStatutDetail($participation['statut'], false);

    $results[] = $trajetEnrichi;
  }

  return $results;
}

/**
 * Calcule les statistiques de l'utilisateur
 */
function calculateUserStats($userId, $trajetsChaufeur, $trajetsPassager)
{
  $stats = [
    'chauffeur' => [
      'total_trajets' => 0,
      'trajets_termines' => 0,
      'trajets_annules' => 0,
      'total_gains' => 0,
      'total_participants_transportes' => 0,
      'taux_completion' => 0
    ],
    'passager' => [
      'total_trajets' => 0,
      'trajets_termines' => 0,
      'trajets_annules' => 0,
      'total_depenses' => 0,
      'taux_completion' => 0
    ],
    'global' => [
      'total_trajets' => 0,
      'kilometres_parcourus' => 0,
      'co2_economise_kg' => 0,
      'membre_depuis' => null
    ]
  ];

  // Statistiques chauffeur
  foreach ($trajetsChaufeur as $trajet) {
    $stats['chauffeur']['total_trajets']++;

    if ($trajet['statut'] === 'termine') {
      $stats['chauffeur']['trajets_termines']++;
      $stats['chauffeur']['total_gains'] += $trajet['gains_total'];
      $stats['chauffeur']['total_participants_transportes'] += $trajet['nombre_participants'];

      if (isset($trajet['kilometrage_reel']) && $trajet['kilometrage_reel'] > 0) {
        $stats['global']['kilometres_parcourus'] += $trajet['kilometrage_reel'];
      }
    } elseif ($trajet['statut'] === 'annule') {
      $stats['chauffeur']['trajets_annules']++;
    }
  }

  // Statistiques passager
  foreach ($trajetsPassager as $trajet) {
    $stats['passager']['total_trajets']++;

    if ($trajet['participation']['statut'] === 'terminee') {
      $stats['passager']['trajets_termines']++;
      $stats['passager']['total_depenses'] += $trajet['cout_total'];
    } elseif ($trajet['participation']['statut'] === 'annulee') {
      $stats['passager']['trajets_annules']++;
    }
  }

  // Calculs de pourcentages
  if ($stats['chauffeur']['total_trajets'] > 0) {
    $stats['chauffeur']['taux_completion'] = round(
      ($stats['chauffeur']['trajets_termines'] / $stats['chauffeur']['total_trajets']) * 100,
      1
    );
  }

  if ($stats['passager']['total_trajets'] > 0) {
    $stats['passager']['taux_completion'] = round(
      ($stats['passager']['trajets_termines'] / $stats['passager']['total_trajets']) * 100,
      1
    );
  }

  // Statistiques globales
  $stats['global']['total_trajets'] = $stats['chauffeur']['total_trajets'] + $stats['passager']['total_trajets'];

  // Estimation CO2 économisé (120g/km par personne transportée)
  $stats['global']['co2_economise_kg'] = round(
    ($stats['global']['kilometres_parcourus'] * $stats['chauffeur']['total_participants_transportes'] * 120) / 1000,
    2
  );

  // Date d'inscription
  $user = DB::findById('utilisateurs', $userId);
  $stats['global']['membre_depuis'] = $user['date_inscription'] ?? null;

  // Formatage des montants
  $stats['chauffeur']['total_gains_formatted'] = number_format($stats['chauffeur']['total_gains'], 2, ',', ' ') . ' €';
  $stats['passager']['total_depenses_formatted'] = number_format($stats['passager']['total_depenses'], 2, ',', ' ') . ' €';

  return $stats;
}

/**
 * Retourne un statut détaillé avec icône et couleur
 */
function getStatutDetail($statut, $estChauffeur)
{
  $details = [
    'planifie' => ['🕐', 'Planifié', 'blue'],
    'en_cours' => ['🚗', 'En cours', 'green'],
    'termine' => ['✅', 'Terminé', 'success'],
    'annule' => ['❌', 'Annulé', 'danger'],
    'confirmee' => ['✅', 'Confirmé', 'success'],
    'terminee' => ['✅', 'Terminé', 'success'],
    'annulee' => ['❌', 'Annulé', 'danger']
  ];

  $detail = $details[$statut] ?? ['❓', 'Inconnu', 'secondary'];

  return [
    'icone' => $detail[0],
    'libelle' => $detail[1],
    'couleur' => $detail[2],
    'statut_brut' => $statut
  ];
}
