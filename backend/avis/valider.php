<?php
header('Content-Type: application/json');

$host = "http://localhost:5984";
$db = "ecoCovoit_nosql";

$id = $_POST['id'] ?? null;
$accepte = $_POST['accepte'] === 'true';

if (!$id) {
    echo json_encode(["success" => false, "message" => "ID manquant."]);
    exit;
}

// 1. Lire le document actuel
$url = "$host/$db/$id";
$data = json_decode(@file_get_contents($url), true);

if (!$data || !isset($data['_rev'])) {
    echo json_encode(["success" => false, "message" => "Avis introuvable."]);
    exit;
}

// 2. Supprimer ou valider
if ($accepte) {
    $data['valide'] = true;
} else {
    // Supprimer le document
    $deleteUrl = "$url?rev=" . $data['_rev'];
    $ch = curl_init($deleteUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    echo json_encode(["success" => true, "message" => "Avis supprimé."]);
    exit;
}

// 3. Valider = mise à jour
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

echo json_encode(["success" => true, "message" => "Avis validé avec succès ✅"]);
