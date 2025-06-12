<?php
// backend/db_mysql.php

$host = 'localhost';
$dbname = 'ecoCovoit_SQL';
$user = 'root'; // ou 'admin' selon ton phpMyAdmin
$password = ''; // par défaut sous XAMPP, c’est vide

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage();
    exit;
}
