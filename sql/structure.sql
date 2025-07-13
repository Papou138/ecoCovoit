-- ========================================
-- STRUCTURE BASE DE DONNEES ecoCovoit
-- Version optimisée pour 8 jours de développement
-- ========================================

-- Création de la base si elle n'existe pas
CREATE DATABASE IF NOT EXISTS ecoCovoit_SQL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecoCovoit_SQL;

-- ========================================
-- TABLE : utilisateurs
-- Gestion de tous les types d'utilisateurs
-- ========================================
CREATE TABLE utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pseudo VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('utilisateur', 'employe', 'admin') DEFAULT 'utilisateur',
    credits INT DEFAULT 20,
    statut ENUM('actif', 'suspendu') DEFAULT 'actif',
    photo_profil VARCHAR(255) DEFAULT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_statut (statut)
);

-- ========================================
-- TABLE : vehicules
-- Véhicules des chauffeurs
-- ========================================
CREATE TABLE vehicules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proprietaire_id INT NOT NULL,
    marque VARCHAR(50) NOT NULL,
    modele VARCHAR(50) NOT NULL,
    couleur VARCHAR(30) DEFAULT NULL,
    immatriculation VARCHAR(20) UNIQUE NOT NULL,
    date_premiere_immat DATE DEFAULT NULL,
    nombre_places INT NOT NULL DEFAULT 4,
    type_energie ENUM('essence', 'diesel', 'electrique', 'hybride') DEFAULT 'essence',
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proprietaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_proprietaire (proprietaire_id),
    INDEX idx_type_energie (type_energie)
);

-- ========================================
-- TABLE : preferences
-- Préférences de conduite des chauffeurs
-- ========================================
CREATE TABLE preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    fumeur BOOLEAN DEFAULT FALSE,
    animaux BOOLEAN DEFAULT FALSE,
    musique BOOLEAN DEFAULT TRUE,
    discussion BOOLEAN DEFAULT TRUE,
    autres_preferences TEXT DEFAULT NULL,

    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_pref (utilisateur_id)
);

-- ========================================
-- TABLE : trajets
-- Cœur de l'application - les trajets proposés
-- ========================================
CREATE TABLE trajets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chauffeur_id INT NOT NULL,
    vehicule_id INT NOT NULL,
    ville_depart VARCHAR(100) NOT NULL,
    adresse_depart TEXT DEFAULT NULL,
    ville_arrivee VARCHAR(100) NOT NULL,
    adresse_arrivee TEXT DEFAULT NULL,
    date_depart DATETIME NOT NULL,
    duree_estimee INT DEFAULT NULL, -- en minutes
    prix DECIMAL(8,2) NOT NULL,
    nombre_places_total INT NOT NULL,
    nombre_places_restantes INT NOT NULL,
    statut ENUM('planifie', 'en_cours', 'termine', 'annule') DEFAULT 'planifie',
    description TEXT DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (chauffeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE RESTRICT,

    INDEX idx_chauffeur (chauffeur_id),
    INDEX idx_depart_arrivee (ville_depart, ville_arrivee),
    INDEX idx_date_depart (date_depart),
    INDEX idx_statut (statut),
    INDEX idx_places (nombre_places_restantes)
);

-- ========================================
-- TABLE : participations
-- Réservations des passagers
-- ========================================
CREATE TABLE participations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trajet_id INT NOT NULL,
    passager_id INT NOT NULL,
    nombre_places INT DEFAULT 1,
    prix_paye DECIMAL(8,2) NOT NULL,
    statut ENUM('confirmee', 'annulee', 'terminee') DEFAULT 'confirmee',
    commentaire TEXT DEFAULT NULL,
    date_reservation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,
    FOREIGN KEY (passager_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,

    UNIQUE KEY unique_participation (trajet_id, passager_id),
    INDEX idx_trajet (trajet_id),
    INDEX idx_passager (passager_id),
    INDEX idx_statut (statut)
);

-- ========================================
-- TABLE : transactions
-- Historique des mouvements de crédits
-- ========================================
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type ENUM('credit', 'debit', 'commission') NOT NULL,
    montant INT NOT NULL, -- en crédits
    solde_avant INT NOT NULL,
    solde_apres INT NOT NULL,
    trajet_id INT DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    date_transaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE SET NULL,

    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_type (type),
    INDEX idx_date (date_transaction)
);

-- ========================================
-- TABLE : avis (version simplifiée MySQL)
-- Les avis détaillés seront dans MongoDB plus tard
-- ========================================
CREATE TABLE avis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trajet_id INT NOT NULL,
    evaluateur_id INT NOT NULL,
    evalue_id INT NOT NULL,
    note INT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire TEXT DEFAULT NULL,
    statut ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    valide_par INT DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_validation TIMESTAMP NULL,

    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (evalue_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (valide_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,

    UNIQUE KEY unique_avis (trajet_id, evaluateur_id, evalue_id),
    INDEX idx_evalue (evalue_id),
    INDEX idx_statut (statut)
);

-- ========================================
-- TABLE : incidents
-- Signalement de problèmes pendant les trajets
-- ========================================
CREATE TABLE incidents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trajet_id INT NOT NULL,
    rapporteur_id INT NOT NULL,
    concerne_id INT NOT NULL,
    type_incident ENUM('retard', 'annulation_tardive', 'comportement', 'vehicule', 'autre') NOT NULL,
    description TEXT NOT NULL,
    statut ENUM('ouvert', 'en_cours', 'resolu', 'clos') DEFAULT 'ouvert',
    traite_par INT DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_resolution TIMESTAMP NULL,

    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,
    FOREIGN KEY (rapporteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (concerne_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (traite_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,

    INDEX idx_trajet (trajet_id),
    INDEX idx_statut (statut),
    INDEX idx_type (type_incident)
);

-- ========================================
-- DONNEES DE TEST
-- Utilisateurs et données de base pour tester
-- ========================================

-- Admin par défaut
INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credits) VALUES
('admin', 'admin@ecocovoit.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1000);

-- Employé de test
INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credits) VALUES
('employe1', 'employe@ecocovoit.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', 100);

-- Utilisateurs de test
INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credits) VALUES
('chauffeur1', 'chauffeur@test.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'utilisateur', 50),
('passager1', 'passager@test.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'utilisateur', 30),
('testeur', 'test@test.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'utilisateur', 20);

-- Véhicule de test
INSERT INTO vehicules (proprietaire_id, marque, modele, couleur, immatriculation, nombre_places, type_energie) VALUES
(3, 'Tesla', 'Model 3', 'Blanc', 'AB-123-CD', 4, 'electrique'),
(3, 'Renault', 'Clio', 'Rouge', 'EF-456-GH', 4, 'essence');

-- Préférences de test
INSERT INTO preferences (utilisateur_id, fumeur, animaux, musique, discussion) VALUES
(3, FALSE, TRUE, TRUE, TRUE);

-- ========================================
-- VUES UTILES
-- ========================================

-- Vue pour les trajets avec détails
CREATE VIEW v_trajets_complets AS
SELECT
    t.*,
    u.pseudo as chauffeur_pseudo,
    u.photo_profil as chauffeur_photo,
    v.marque,
    v.modele,
    v.type_energie,
    CASE WHEN v.type_energie = 'electrique' THEN 1 ELSE 0 END as est_ecologique,
    COALESCE(AVG(a.note), 0) as note_moyenne_chauffeur,
    COUNT(a.id) as nombre_avis
FROM trajets t
JOIN utilisateurs u ON t.chauffeur_id = u.id
JOIN vehicules v ON t.vehicule_id = v.id
LEFT JOIN avis a ON a.evalue_id = u.id AND a.statut = 'valide'
GROUP BY t.id;

-- ========================================
-- TRIGGERS pour automatiser certaines actions
-- ========================================

-- Trigger pour mettre à jour les places restantes
DELIMITER //
CREATE TRIGGER after_participation_insert
AFTER INSERT ON participations
FOR EACH ROW
BEGIN
    UPDATE trajets
    SET nombre_places_restantes = nombre_places_restantes - NEW.nombre_places
    WHERE id = NEW.trajet_id;
END//

CREATE TRIGGER after_participation_delete
AFTER DELETE ON participations
FOR EACH ROW
BEGIN
    UPDATE trajets
    SET nombre_places_restantes = nombre_places_restantes + OLD.nombre_places
    WHERE id = OLD.trajet_id;
END//
DELIMITER ;

-- ========================================
-- INDEX POUR OPTIMISER LES RECHERCHES
-- ========================================

-- Index composé pour la recherche de trajets
CREATE INDEX idx_recherche_trajets ON trajets(ville_depart, ville_arrivee, date_depart, statut, nombre_places_restantes);

-- Index pour les statistiques admin
CREATE INDEX idx_stats_date ON trajets(DATE(date_creation));
CREATE INDEX idx_stats_transactions ON transactions(DATE(date_transaction), type);

COMMIT;
