<?php

/**
 * API de détail d'un trajet
 *
 * Endpoint: GET /backend/trajets/detail.php?id=123
 * Peut nécessiter une authentification selon le contexte
 *
 * Cette API correspond à l'US 5 (détail d'un covoiturage)
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

try {
  // === RECUPERATION DES PARAMETRES ===

  $trajetId = (int)($_GET['id'] ?? 0);

  if ($trajetId <= 0) {
    jsonResponse(false, 'ID de trajet invalide');
  }

  // Vérifier l'authentification (optionnelle pour la vue publique)
  $currentUser = null;
  try {
    $currentUser = getCurrentUser();
  } catch (Exception $e) {
    // Pas d'authentification - vue anonyme
  }

  // === RECUPERATION DU TRAJET ===

  $trajet = DB::findById('trajets', $trajetId);

  if (!$trajet) {
    jsonResponse(false, 'Trajet introuvable', null, 404);
  }

  // === ENRICHISSEMENT DES DONNEES ===

  // Informations du chauffeur
  $chauffeur = DB::findById('utilisateurs', $trajet['chauffeur_id']);
  if (!$chauffeur) {
    jsonResponse(false, 'Chauffeur introuvable', null, 404);
  }

  // Informations du véhicule
  $vehicule = DB::findById('vehicules', $trajet['vehicule_id']);
  if (!$vehicule) {
    jsonResponse(false, 'Véhicule introuvable', null, 404);
  }

  // Points de passage
  $pointsPassage = DB::findAll('points_passage', [
    'trajet_id' => $trajetId
  ]);

  // Trier par ordre
  usort($pointsPassage, function ($a, $b) {
    return $a['ordre'] <=> $b['ordre'];
  });

  // Participants actuels
  $participants = [];
  $participations = DB::findAll('participations', [
    'trajet_id' => $trajetId,
    'statut' => 'confirmee'
  ]);

  foreach ($participations as $participation) {
    $participant = DB::findById('utilisateurs', $participation['passager_id']);
    if ($participant) {
      $participants[] = [
        'id' => $participant['id'],
        'nom' => $participant['nom'],
        'prenom' => $participant['prenom'],
        'note_moyenne' => $participant['note_moyenne'],
        'date_inscription' => $participant['date_inscription'],
        'nb_trajets_effectues' => $participant['nb_trajets_effectues'],
        'point_prise_en_charge' => $participation['point_prise_en_charge'],
        'point_depose' => $participation['point_depose'],
        'date_participation' => $participation['date_creation']
      ];
    }
  }

  // Avis sur le chauffeur (5 derniers)
  $avis = DB::findAll('avis', [
    'utilisateur_evalue_id' => $chauffeur['id'],
    'statut' => 'valide'
  ]);

  // Trier par date décroissante et prendre les 5 derniers
  usort($avis, function ($a, $b) {
    return strtotime($b['date_creation']) <=> strtotime($a['date_creation']);
  });
  $avis = array_slice($avis, 0, 5);

  // Enrichir les avis avec les infos des évaluateurs
  $avisDetails = [];
  foreach ($avis as $avi) {
    $evaluateur = DB::findById('utilisateurs', $avi['evaluateur_id']);
    if ($evaluateur) {
      $avisDetails[] = [
        'id' => $avi['id'],
        'note' => $avi['note'],
        'commentaire' => $avi['commentaire'],
        'date' => $avi['date_creation'],
        'date_formatted' => date('d/m/Y', strtotime($avi['date_creation'])),
        'evaluateur' => [
          'nom' => $evaluateur['nom'],
          'prenom' => $evaluateur['prenom'],
          'note_moyenne' => $evaluateur['note_moyenne']
        ]
      ];
    }
  }

  // Trajet retour associé
  $trajetRetour = null;
  if (!empty($trajet['trajet_retour_id'])) {
    $trajetRetour = DB::findById('trajets', $trajet['trajet_retour_id']);
    if ($trajetRetour) {
      $trajetRetour['date_formatted'] = date('d/m/Y H:i', strtotime($trajetRetour['date_depart'] . ' ' . $trajetRetour['heure_depart']));
      $trajetRetour['prix_formatted'] = number_format($trajetRetour['prix'], 2, ',', ' ') . ' €';
    }
  }

  // === CALCULS ET FORMATAGE ===

  // Statut d'écologie du véhicule
  $estEcologique = in_array($vehicule['type_carburant'], ['électrique', 'hybride']);

  // Durée formatée
  $dureeFormatted = formatDuration($trajet['duree_estimee']);

  // Prix formaté
  $prixFormatted = number_format($trajet['prix'], 2, ',', ' ') . ' €';

  // Date formatée
  $dateFormatted = date('d/m/Y', strtotime($trajet['date_depart']));
  $heureFormatted = date('H:i', strtotime($trajet['heure_depart']));

  // Statuts
  $estDisponible = $trajet['nombre_places_restantes'] > 0 && $trajet['statut'] === 'planifie';
  $estPasse = strtotime($trajet['date_depart'] . ' ' . $trajet['heure_depart']) < time();
  $estProprietaire = $currentUser && $currentUser['id'] == $trajet['chauffeur_id'];

  // Vérifier si l'utilisateur connecté participe déjà
  $dejaParticipe = false;
  $participationUser = null;
  if ($currentUser) {
    $participationUser = DB::findAll('participations', [
      'trajet_id' => $trajetId,
      'passager_id' => $currentUser['id']
    ]);
    $dejaParticipe = !empty($participationUser);
    if ($dejaParticipe) {
      $participationUser = $participationUser[0];
    }
  }

  // === CONSTRUCTION DE LA REPONSE ===

  $responseData = [
    'trajet' => [
      'id' => $trajet['id'],
      'depart' => $trajet['depart'],
      'arrivee' => $trajet['arrivee'],
      'date_depart' => $trajet['date_depart'],
      'heure_depart' => $trajet['heure_depart'],
      'date_formatted' => $dateFormatted,
      'heure_formatted' => $heureFormatted,
      'datetime_formatted' => $dateFormatted . ' à ' . $heureFormatted,
      'nombre_places' => $trajet['nombre_places'],
      'nombre_places_restantes' => $trajet['nombre_places_restantes'],
      'prix' => $trajet['prix'],
      'prix_formatted' => $prixFormatted,
      'description' => $trajet['description'],
      'duree_estimee' => $trajet['duree_estimee'],
      'duree_formatted' => $dureeFormatted,
      'statut' => $trajet['statut'],
      'est_disponible' => $estDisponible,
      'est_passe' => $estPasse,
      'est_ecologique' => $estEcologique,
      'accepte_animaux' => $trajet['accepte_animaux'],
      'accepte_fumeurs' => $trajet['accepte_fumeurs'],
      'accepte_bagages' => $trajet['accepte_bagages'],
      'max_detour' => $trajet['max_detour'],
      'date_creation' => $trajet['date_creation']
    ],
    'chauffeur' => [
      'id' => $chauffeur['id'],
      'nom' => $chauffeur['nom'],
      'prenom' => $chauffeur['prenom'],
      'email' => $estProprietaire ? $chauffeur['email'] : null, // Email visible seulement au propriétaire
      'telephone' => $estProprietaire || $dejaParticipe ? $chauffeur['telephone'] : null,
      'note_moyenne' => $chauffeur['note_moyenne'],
      'nb_trajets_effectues' => $chauffeur['nb_trajets_effectues'],
      'date_inscription' => $chauffeur['date_inscription'],
      'date_inscription_formatted' => date('d/m/Y', strtotime($chauffeur['date_inscription'])),
      'est_vous' => $estProprietaire
    ],
    'vehicule' => [
      'id' => $vehicule['id'],
      'marque' => $vehicule['marque'],
      'modele' => $vehicule['modele'],
      'couleur' => $vehicule['couleur'],
      'type_carburant' => $vehicule['type_carburant'],
      'est_ecologique' => $estEcologique,
      'badge_eco' => $estEcologique ? '🌱 Ecologique' : null,
      'immatriculation' => $estProprietaire ? $vehicule['immatriculation'] : substr($vehicule['immatriculation'], 0, 2) . 'XX-XXX'
    ],
    'points_passage' => array_map(function ($point) {
      return [
        'ville' => $point['ville'],
        'ordre' => $point['ordre']
      ];
    }, $pointsPassage),
    'participants' => $participants,
    'avis_chauffeur' => $avisDetails,
    'trajet_retour' => $trajetRetour,
    'utilisateur_connecte' => [
      'est_proprietaire' => $estProprietaire,
      'deja_participe' => $dejaParticipe,
      'participation' => $participationUser,
      'peut_participer' => !$estProprietaire && !$dejaParticipe && $estDisponible && !$estPasse
    ],
    'statistiques' => [
      'nb_participants' => count($participants),
      'nb_avis_chauffeur' => count($avisDetails),
      'taux_occupation' => $trajet['nombre_places'] > 0 ?
        round((($trajet['nombre_places'] - $trajet['nombre_places_restantes']) / $trajet['nombre_places']) * 100, 1) : 0
    ]
  ];

  jsonResponse(true, 'Détails du trajet récupérés', $responseData);
} catch (Exception $e) {
  error_log("Erreur detail trajet API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur lors de la récupération du trajet', null, 500);
}

/**
 * Formate une durée en minutes en format lisible
 */
function formatDuration($minutes)
{
  if ($minutes < 60) {
    return $minutes . ' min';
  }

  $hours = intval($minutes / 60);
  $remainingMinutes = $minutes % 60;

  if ($remainingMinutes == 0) {
    return $hours . 'h';
  }

  return $hours . 'h' . sprintf('%02d', $remainingMinutes);
}
