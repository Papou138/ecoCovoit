<?php

/**
 * API de gestion des véhicules utilisateur
 *
 * Endpoint: GET/POST/PUT/DELETE /backend/users/vehicles.php
 * - GET: Récupérer tous les véhicules de l'utilisateur
 * - POST: Ajouter un nouveau véhicule
 * - PUT: Modifier un véhicule (avec ID)
 * - DELETE: Supprimer un véhicule (avec ID)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/../middleware/auth.php';

// Gestion CORS et méthodes HTTP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
      getUserVehicles();
      break;

    case 'POST':
      addVehicle();
      break;

    case 'PUT':
      updateVehicle();
      break;

    case 'DELETE':
      deleteVehicle();
      break;

    default:
      jsonResponse(false, 'Méthode non autorisée', null, 405);
  }
} catch (Exception $e) {
  error_log("Erreur vehicles API: " . $e->getMessage());
  jsonResponse(false, 'Erreur serveur. Veuillez réessayer.', null, 500);
}

/**
 * Récupère tous les véhicules de l'utilisateur connecté
 */
function getUserVehicles()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupérer tous les véhicules de l'utilisateur
  $vehicles = DB::findAll('vehicules', ['proprietaire_id' => $currentUser['id']]);

  // Ajouter des informations supplémentaires pour chaque véhicule
  foreach ($vehicles as &$vehicle) {
    $vehicle['est_ecologique'] = ($vehicle['type_energie'] === 'electrique');
    $vehicle['age_vehicule'] = $vehicle['date_premiere_immat'] ?
      date('Y') - date('Y', strtotime($vehicle['date_premiere_immat'])) : null;
  }

  AuthMiddleware::logAction('view_vehicles');

  jsonResponse(true, 'Véhicules récupérés avec succès', [
    'vehicules' => $vehicles,
    'total' => count($vehicles)
  ]);
}

/**
 * Ajoute un nouveau véhicule
 */
function addVehicle()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupération des données
  $input = json_decode(file_get_contents('php://input'), true);

  $marque = trim($input['marque'] ?? '');
  $modele = trim($input['modele'] ?? '');
  $couleur = trim($input['couleur'] ?? '');
  $immatriculation = trim(strtoupper($input['immatriculation'] ?? ''));
  $datePremierImmat = $input['date_premiere_immat'] ?? '';
  $nombrePlaces = (int)($input['nombre_places'] ?? 4);
  $typeEnergie = $input['type_energie'] ?? 'essence';

  // === VALIDATION DES DONNEES ===

  if (empty($marque)) {
    jsonResponse(false, 'La marque est requise');
  }
  if (strlen($marque) > 50) {
    jsonResponse(false, 'La marque ne peut pas dépasser 50 caractères');
  }

  if (empty($modele)) {
    jsonResponse(false, 'Le modèle est requis');
  }
  if (strlen($modele) > 50) {
    jsonResponse(false, 'Le modèle ne peut pas dépasser 50 caractères');
  }

  if (!empty($couleur) && strlen($couleur) > 30) {
    jsonResponse(false, 'La couleur ne peut pas dépasser 30 caractères');
  }

  if (empty($immatriculation)) {
    jsonResponse(false, 'L\'immatriculation est requise');
  }

  // Validation format immatriculation française
  if (!preg_match('/^[A-Z]{1,2}-?\d{3}-?[A-Z]{1,2}$/', $immatriculation)) {
    jsonResponse(false, 'Format d\'immatriculation invalide (ex: AB-123-CD)');
  }

  // Vérifier l'unicité de l'immatriculation
  $existingVehicle = DB::findBy('vehicules', 'immatriculation', $immatriculation);
  if ($existingVehicle) {
    jsonResponse(false, 'Cette immatriculation est déjà enregistrée');
  }

  // Validation de la date
  if (!empty($datePremierImmat)) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $datePremierImmat);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $datePremierImmat) {
      jsonResponse(false, 'Format de date invalide (YYYY-MM-DD attendu)');
    }

    // Vérifier que la date n'est pas dans le futur
    if ($dateObj > new DateTime()) {
      jsonResponse(false, 'La date de première immatriculation ne peut pas être dans le futur');
    }

    // Vérifier que la date n'est pas trop ancienne (>50 ans)
    $minDate = new DateTime('-50 years');
    if ($dateObj < $minDate) {
      jsonResponse(false, 'La date de première immatriculation ne peut pas être antérieure à 50 ans');
    }
  }

  // Validation du nombre de places
  if ($nombrePlaces < 1 || $nombrePlaces > 9) {
    jsonResponse(false, 'Le nombre de places doit être entre 1 et 9');
  }

  // Validation du type d'énergie
  $typesEnergie = ['essence', 'diesel', 'electrique', 'hybride'];
  if (!in_array($typeEnergie, $typesEnergie)) {
    jsonResponse(false, 'Type d\'énergie invalide: ' . implode(', ', $typesEnergie));
  }

  // === CREATION DU VEHICULE ===

  $vehicleData = [
    'proprietaire_id' => $currentUser['id'],
    'marque' => $marque,
    'modele' => $modele,
    'couleur' => $couleur ?: null,
    'immatriculation' => $immatriculation,
    'date_premiere_immat' => $datePremierImmat ?: null,
    'nombre_places' => $nombrePlaces,
    'type_energie' => $typeEnergie,
    'actif' => true,
    'date_creation' => date('Y-m-d H:i:s')
  ];

  $vehicleId = DB::insert('vehicules', $vehicleData);

  if (!$vehicleId) {
    jsonResponse(false, 'Erreur lors de l\'ajout du véhicule', null, 500);
  }

  AuthMiddleware::logAction('add_vehicle', [
    'vehicle_id' => $vehicleId,
    'marque' => $marque,
    'modele' => $modele,
    'type_energie' => $typeEnergie
  ]);

  // Récupérer le véhicule créé
  $newVehicle = DB::findById('vehicules', $vehicleId);
  $newVehicle['est_ecologique'] = ($newVehicle['type_energie'] === 'electrique');

  jsonResponse(true, 'Véhicule ajouté avec succès', [
    'vehicule' => $newVehicle
  ]);
}

/**
 * Met à jour un véhicule existant
 */
function updateVehicle()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupération des données
  $input = json_decode(file_get_contents('php://input'), true);
  $vehicleId = (int)($input['id'] ?? 0);

  if (!$vehicleId) {
    jsonResponse(false, 'ID du véhicule requis');
  }

  // Vérifier que le véhicule appartient à l'utilisateur
  $vehicle = DB::findById('vehicules', $vehicleId);
  if (!$vehicle) {
    jsonResponse(false, 'Véhicule introuvable');
  }

  if ($vehicle['proprietaire_id'] != $currentUser['id']) {
    jsonResponse(false, 'Vous ne pouvez modifier que vos propres véhicules');
  }

  // Vérifier si le véhicule est utilisé dans des trajets actifs
  $trajetsActifs = DB::findAll('trajets', [
    'vehicule_id' => $vehicleId,
    'statut' => 'planifie'
  ]);

  if (!empty($trajetsActifs)) {
    jsonResponse(false, 'Impossible de modifier un véhicule utilisé dans des trajets planifiés');
  }

  // Préparer les données à mettre à jour (même validation que pour l'ajout)
  $updatedData = [];

  if (isset($input['marque'])) {
    $marque = trim($input['marque']);
    if (empty($marque) || strlen($marque) > 50) {
      jsonResponse(false, 'Marque invalide');
    }
    $updatedData['marque'] = $marque;
  }

  if (isset($input['modele'])) {
    $modele = trim($input['modele']);
    if (empty($modele) || strlen($modele) > 50) {
      jsonResponse(false, 'Modèle invalide');
    }
    $updatedData['modele'] = $modele;
  }

  if (isset($input['couleur'])) {
    $couleur = trim($input['couleur']);
    if (strlen($couleur) > 30) {
      jsonResponse(false, 'Couleur invalide');
    }
    $updatedData['couleur'] = $couleur ?: null;
  }

  if (isset($input['nombre_places'])) {
    $nombrePlaces = (int)$input['nombre_places'];
    if ($nombrePlaces < 1 || $nombrePlaces > 9) {
      jsonResponse(false, 'Nombre de places invalide');
    }
    $updatedData['nombre_places'] = $nombrePlaces;
  }

  if (isset($input['type_energie'])) {
    $typeEnergie = $input['type_energie'];
    $typesEnergie = ['essence', 'diesel', 'electrique', 'hybride'];
    if (!in_array($typeEnergie, $typesEnergie)) {
      jsonResponse(false, 'Type d\'énergie invalide');
    }
    $updatedData['type_energie'] = $typeEnergie;
  }

  if (isset($input['actif'])) {
    $updatedData['actif'] = (bool)$input['actif'];
  }

  if (empty($updatedData)) {
    jsonResponse(false, 'Aucune modification détectée');
  }

  // Mettre à jour
  $success = DB::update('vehicules', $vehicleId, $updatedData);

  if (!$success) {
    jsonResponse(false, 'Erreur lors de la mise à jour', null, 500);
  }

  AuthMiddleware::logAction('update_vehicle', [
    'vehicle_id' => $vehicleId,
    'fields_updated' => array_keys($updatedData)
  ]);

  // Récupérer le véhicule mis à jour
  $updatedVehicle = DB::findById('vehicules', $vehicleId);
  $updatedVehicle['est_ecologique'] = ($updatedVehicle['type_energie'] === 'electrique');

  jsonResponse(true, 'Véhicule mis à jour avec succès', [
    'vehicule' => $updatedVehicle
  ]);
}

/**
 * Supprime un véhicule
 */
function deleteVehicle()
{
  // Vérifier l'authentification
  $currentUser = AuthMiddleware::getCurrentUser();

  // Récupération de l'ID
  $input = json_decode(file_get_contents('php://input'), true);
  $vehicleId = (int)($input['id'] ?? $_GET['id'] ?? 0);

  if (!$vehicleId) {
    jsonResponse(false, 'ID du véhicule requis');
  }

  // Vérifier que le véhicule appartient à l'utilisateur
  $vehicle = DB::findById('vehicules', $vehicleId);
  if (!$vehicle) {
    jsonResponse(false, 'Véhicule introuvable');
  }

  if ($vehicle['proprietaire_id'] != $currentUser['id']) {
    jsonResponse(false, 'Vous ne pouvez supprimer que vos propres véhicules');
  }

  // Vérifier si le véhicule est utilisé dans des trajets
  $trajets = DB::findAll('trajets', ['vehicule_id' => $vehicleId]);
  if (!empty($trajets)) {
    jsonResponse(false, 'Impossible de supprimer un véhicule utilisé dans des trajets. Désactivez-le plutôt.');
  }

  // Supprimer le véhicule
  $success = DB::delete('vehicules', $vehicleId);

  if (!$success) {
    jsonResponse(false, 'Erreur lors de la suppression', null, 500);
  }

  AuthMiddleware::logAction('delete_vehicle', [
    'vehicle_id' => $vehicleId,
    'marque' => $vehicle['marque'],
    'modele' => $vehicle['modele']
  ]);

  jsonResponse(true, 'Véhicule supprimé avec succès');
}
