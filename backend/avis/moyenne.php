<?php
header('Content-Type: application/json');

$chauffeur_id = $_GET['chauffeur_id'] ?? null;
if (!$chauffeur_id) {
    echo json_encode(["success" => false, "message" => "ID chauffeur manquant"]);
    exit;
}

$host = "http://localhost:5984";
$db = "ecoCovoit_nosql";

// Requête CouchDB : tous les avis validés du chauffeur
$url = "$host/$db/_find";

$payload = json_encode([
    "selector" => [
        "type" => "avis",
        "valide" => true,
        "chauffeur_id" => (int)$chauffeur_id
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);
$avis = $res['docs'] ?? [];

$total = count($avis);
$sum = 0;
foreach ($avis as $a) {
    $sum += $a['note'];
}

$moyenne = $total > 0 ? round($sum / $total, 1) : null;
// Explication du code précedent :
// Calcul de la moyenne :
// - Si $total est supérieur à 0 : divise $sum par $total et arrondit à 1 décimale
// - Sinon : retourne null pour éviter une division par zéro

echo json_encode([
    "success" => true,
    "moyenne" => $moyenne,
    "total" => $total
]);
