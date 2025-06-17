<?php
header('Content-Type: application/json');

$chauffeur_id = $_GET['chauffeur_id'] ?? null;
if (!$chauffeur_id) {
    echo json_encode([]);
    exit;
}

$host = "http://localhost:5984";
$db   = "ecoCovoit_nosql";

// Requête _find CouchDB
$url = "$host/$db/_find";

$payload = json_encode([
    "selector" => [
        "type" => "avis",
        "valide" => true,
        "chauffeur_id" => (int)$chauffeur_id
    ],
    "sort" => [["date_creation" => "desc"]]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);
echo json_encode($res['docs'] ?? []);
