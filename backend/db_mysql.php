<?php
/**
 * FICHIER OBSOLETE - Utilisez Database.php à la place
 *
 * Ce fichier est conservé pour compatibilité avec l'ancien code
 * mais la nouvelle classe Database dans models/Database.php est recommandée
 */

// Redirection vers la nouvelle classe
require_once __DIR__ . '/models/Database.php';

// Variables pour compatibilité avec l'ancien code
$pdo = Database::getPDO();

// Configuration héritée (à supprimer progressivement)
$host = 'localhost';
$dbname = 'ecoCovoit_SQL';
$user = 'root';
$password = '';nd/db_mysql.php

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
