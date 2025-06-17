<?php
header('Content-Type: application/json');

$host = "http://localhost:5984";
$db = "ecoCovoit_nosql";

// CouchDB : requête pour tous les documents type=avis où valide=false
$url = "$host/$db/_find";

$payload = json_encode([
    "selector" => [
        "type" => "avis",
        "valide" => false
    ],
    "sort" => [["date_creation" => "asc"]]
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
