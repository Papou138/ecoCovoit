<?php
require 'vendor/autoload.php'; // Utilise Composer avec MongoDB PHP Library

$client = new MongoDB\Client("mongodb://localhost:27017"); // ou MongoDB Atlas URI
$db = $client->ecoCovoit_nosql;
$collection = $db->preferences;
