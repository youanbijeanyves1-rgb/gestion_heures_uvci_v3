-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 19 mai 2026 à 10:39
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_heures_uvci`
--

DELIMITER $$
--
-- Procédures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generer_paiement` (IN `p_id_enseignant` INT, IN `p_id_annee` INT, IN `p_id_taux` INT, IN `p_id_genere_par` INT)   BEGIN
    DECLARE v_volume_total DECIMAL(10,2) DEFAULT 0;
    DECLARE v_charge_statutaire DECIMAL(10,2) DEFAULT 0;
    DECLARE v_statut VARCHAR(20);
    DECLARE v_volume_complementaire DECIMAL(10,2) DEFAULT 0;
    DECLARE v_volume_a_payer DECIMAL(10,2) DEFAULT 0;
    DECLARE v_taux DECIMAL(12,2) DEFAULT 0;
    DECLARE v_montant_total DECIMAL(12,2) DEFAULT 0;

    SELECT e.statut, COALESCE(g.charge_statutaire, 0)
    INTO v_statut, v_charge_statutaire
    FROM enseignant e
    LEFT JOIN grade g ON g.id_grade = e.id_grade
    WHERE e.id_enseignant = p_id_enseignant;

    SELECT COALESCE(SUM(volume_horaire_calcule), 0)
    INTO v_volume_total
    FROM activite_pedagogique
    WHERE id_enseignant = p_id_enseignant
      AND id_annee = p_id_annee
      AND statut_validation = 'VALIDEE';

    SELECT montant
    INTO v_taux
    FROM taux_horaire
    WHERE id_taux = p_id_taux
      AND actif = TRUE;

    IF v_statut = 'VACATAIRE' THEN
        SET v_volume_complementaire = 0;
        SET v_volume_a_payer = v_volume_total;
    ELSE
        SET v_volume_complementaire = GREATEST(0, v_volume_total - v_charge_statutaire);
        SET v_volume_a_payer = v_volume_complementaire;
    END IF;

    SET v_montant_total = v_volume_a_payer * v_taux;

    INSERT INTO paiement (
        volume_total,
        volume_complementaire,
        volume_a_payer,
        montant_total,
        id_enseignant,
        id_annee,
        id_taux,
        id_genere_par
    )
    VALUES (
        v_volume_total,
        v_volume_complementaire,
        v_volume_a_payer,
        v_montant_total,
        p_id_enseignant,
        p_id_annee,
        p_id_taux,
        p_id_genere_par
    )
    ON DUPLICATE KEY UPDATE
        volume_total = VALUES(volume_total),
        volume_complementaire = VALUES(volume_complementaire),
        volume_a_payer = VALUES(volume_a_payer),
        montant_total = VALUES(montant_total),
        id_taux = VALUES(id_taux),
        id_genere_par = VALUES(id_genere_par),
        date_generation = CURRENT_TIMESTAMP,
        statut_paiement = 'GENERE';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `activite_pedagogique`
--

CREATE TABLE `activite_pedagogique` (
  `id_activite` int(11) NOT NULL,
  `type_activite` enum('CREATION_RESSOURCE','MISE_A_JOUR_RESSOURCE') NOT NULL,
  `niveau_complexite` enum('NIVEAU_1','NIVEAU_2','NIVEAU_3') NOT NULL,
  `nombre_heures` decimal(10,2) NOT NULL,
  `nb_sequences` int(11) NOT NULL DEFAULT 0,
  `volume_horaire_calcule` decimal(10,2) NOT NULL DEFAULT 0.00,
  `statut_validation` enum('EN_ATTENTE','VALIDEE','REJETEE') NOT NULL DEFAULT 'EN_ATTENTE',
  `date_saisie` datetime NOT NULL DEFAULT current_timestamp(),
  `observation` text DEFAULT NULL,
  `id_enseignant` int(11) NOT NULL,
  `id_cours` int(11) NOT NULL,
  `id_ressource` int(11) DEFAULT NULL,
  `id_annee` int(11) NOT NULL,
  `id_parametre` int(11) NOT NULL,
  `id_saisi_par` int(11) NOT NULL
) ;

--
-- Déchargement des données de la table `activite_pedagogique`
--

INSERT INTO `activite_pedagogique` (`id_activite`, `type_activite`, `niveau_complexite`, `nombre_heures`, `nb_sequences`, `volume_horaire_calcule`, `statut_validation`, `date_saisie`, `observation`, `id_enseignant`, `id_cours`, `id_ressource`, `id_annee`, `id_parametre`, `id_saisi_par`) VALUES
(2, 'CREATION_RESSOURCE', 'NIVEAU_1', 10.00, 40, 16.00, 'VALIDEE', '2026-05-16 23:13:57', '', 3, 7, 4, 1, 1, 2),
(3, 'MISE_A_JOUR_RESSOURCE', 'NIVEAU_1', 9.00, 36, 7.20, 'VALIDEE', '2026-05-16 23:15:19', '', 5, 7, 5, 1, 4, 2),
(4, 'CREATION_RESSOURCE', 'NIVEAU_2', 5.00, 20, 15.00, 'VALIDEE', '2026-05-16 23:17:44', '', 4, 9, 6, 1, 2, 2),
(5, 'MISE_A_JOUR_RESSOURCE', 'NIVEAU_1', 29.00, 116, 23.20, 'REJETEE', '2026-05-16 23:20:49', '', 5, 8, 7, 1, 4, 2),
(6, 'CREATION_RESSOURCE', 'NIVEAU_1', 39.50, 158, 63.20, 'REJETEE', '2026-05-16 23:22:49', '', 6, 10, 8, 1, 1, 2);

--
-- Déclencheurs `activite_pedagogique`
--
DELIMITER $$
CREATE TRIGGER `trg_activite_bi` BEFORE INSERT ON `activite_pedagogique` FOR EACH ROW BEGIN
    DECLARE v_coefficient DECIMAL(10,3);

    SELECT coefficient
    INTO v_coefficient
    FROM parametre_calcul
    WHERE id_parametre = NEW.id_parametre
      AND type_activite = NEW.type_activite
      AND niveau_complexite = NEW.niveau_complexite
      AND actif = TRUE
    LIMIT 1;

    IF v_coefficient IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Paramètre de calcul invalide ou inactif.';
    END IF;

    SET NEW.nb_sequences = NEW.nombre_heures * 4;
    SET NEW.volume_horaire_calcule = NEW.nb_sequences * v_coefficient;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_activite_bu` BEFORE UPDATE ON `activite_pedagogique` FOR EACH ROW BEGIN
    DECLARE v_coefficient DECIMAL(10,3);

    SELECT coefficient
    INTO v_coefficient
    FROM parametre_calcul
    WHERE id_parametre = NEW.id_parametre
      AND type_activite = NEW.type_activite
      AND niveau_complexite = NEW.niveau_complexite
      AND actif = TRUE
    LIMIT 1;

    IF v_coefficient IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Paramètre de calcul invalide ou inactif.';
    END IF;

    SET NEW.nb_sequences = NEW.nombre_heures * 4;
    SET NEW.volume_horaire_calcule = NEW.nb_sequences * v_coefficient;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `annee_academique`
--

CREATE TABLE `annee_academique` (
  `id_annee` int(11) NOT NULL,
  `libelle_annee` varchar(20) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `est_active` tinyint(1) NOT NULL DEFAULT 0
) ;

--
-- Déchargement des données de la table `annee_academique`
--

INSERT INTO `annee_academique` (`id_annee`, `libelle_annee`, `date_debut`, `date_fin`, `est_active`) VALUES
(1, '2025-2026', '2025-10-01', '2026-09-30', 1),
(3, '2024-2025', '2024-09-16', '2025-07-16', 0);

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL,
  `code_cours` varchar(50) NOT NULL,
  `intitule_cours` varchar(200) NOT NULL,
  `id_enseignant` int(11) DEFAULT NULL,
  `nombre_heures` decimal(10,2) NOT NULL,
  `nb_sequences` int(11) NOT NULL DEFAULT 0,
  `nombre_credits` int(11) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `niveau` enum('LICENCE','MASTER') NOT NULL DEFAULT 'LICENCE'
) ;

--
-- Déchargement des données de la table `cours`
--

INSERT INTO `cours` (`id_cours`, `code_cours`, `intitule_cours`, `id_enseignant`, `nombre_heures`, `nb_sequences`, `nombre_credits`, `actif`, `niveau`) VALUES
(7, 'INF-L2-BD-001', 'Modelisation de bases de données', 5, 40.00, 160, 4, 1, 'LICENCE'),
(8, 'INF-L3-DAS-002', 'Développement Web', 5, 40.00, 160, 4, 1, 'LICENCE'),
(9, 'INF-M1-BDA-001', 'Introduction au Big Data', 4, 20.00, 80, 2, 1, 'LICENCE'),
(10, 'INF-M2-CIO-002', 'Sécurité des systèmes distribués', 6, 40.00, 160, 4, 1, 'LICENCE');

-- --------------------------------------------------------

--
-- Structure de la table `cours_filiere`
--

CREATE TABLE `cours_filiere` (
  `id_cours` int(11) NOT NULL,
  `id_filiere` int(11) NOT NULL,
  `niveau` enum('L1','L2','L3','M1','M2') NOT NULL,
  `semestre` enum('S1','S2','S3','S4','S5','S6') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cours_filiere`
--

INSERT INTO `cours_filiere` (`id_cours`, `id_filiere`, `niveau`, `semestre`) VALUES
(7, 1, 'L2', 'S3'),
(8, 1, 'L3', 'S3'),
(9, 1, 'M1', 'S1'),
(10, 1, 'M2', 'S2');

-- --------------------------------------------------------

--
-- Structure de la table `departement`
--

CREATE TABLE `departement` (
  `id_departement` int(11) NOT NULL,
  `nom_departement` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `departement`
--

INSERT INTO `departement` (`id_departement`, `nom_departement`, `description`, `actif`) VALUES
(1, 'Sciences et Technologies', 'Département actuel de l’UVCI.', 1);

-- --------------------------------------------------------

--
-- Structure de la table `enseignant`
--

CREATE TABLE `enseignant` (
  `id_enseignant` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `statut` enum('PERMANENT','VACATAIRE') NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_departement` int(11) NOT NULL,
  `id_grade` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `enseignant`
--

INSERT INTO `enseignant` (`id_enseignant`, `nom`, `prenoms`, `email`, `telephone`, `statut`, `actif`, `id_departement`, `id_grade`, `id_taux`, `id_utilisateur`) VALUES
(3, 'KOUASSI', 'Jean Michel', 'kouassi@uvci.ci', '0708080808', 'PERMANENT', 1, 1, 1, 6, 6),
(4, 'YAO', 'Stéphane', 'yao@uvci.ci', '0708080807', 'PERMANENT', 1, 1, 2, 8, 7),
(5, 'KOFFI', 'Armand', 'koffi@uvci.ci', '0708080806', 'VACATAIRE', 1, 1, 2, 12, 8),
(6, 'ATTA', 'Clarisse', 'atta@uvci.ci', '0708080805', 'VACATAIRE', 1, 1, 2, 13, 9);

--
-- Déclencheurs `enseignant`
--
DELIMITER $$
CREATE TRIGGER `trg_enseignant_bi` BEFORE INSERT ON `enseignant` FOR EACH ROW BEGIN
    IF NEW.statut = 'PERMANENT' AND NEW.id_grade IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un enseignant permanent doit avoir un grade.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_enseignant_bu` BEFORE UPDATE ON `enseignant` FOR EACH ROW BEGIN
    IF NEW.statut = 'PERMANENT' AND NEW.id_grade IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un enseignant permanent doit avoir un grade.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_taux_horaire`
--

CREATE TABLE `enseignant_taux_horaire` (
  `id_enseignant_taux` int(11) NOT NULL,
  `id_enseignant` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `date_affectation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `enseignant_taux_horaire`
--

INSERT INTO `enseignant_taux_horaire` (`id_enseignant_taux`, `id_enseignant`, `id_taux`, `actif`, `date_affectation`) VALUES
(1, 3, 6, 1, '2026-05-16 22:46:15'),
(2, 3, 7, 1, '2026-05-16 22:46:15'),
(3, 4, 8, 1, '2026-05-16 22:49:35'),
(4, 4, 9, 1, '2026-05-16 22:49:35'),
(5, 5, 12, 1, '2026-05-16 22:51:48'),
(6, 6, 13, 1, '2026-05-16 22:53:28');

-- --------------------------------------------------------

--
-- Structure de la table `etat_paiement`
--

CREATE TABLE `etat_paiement` (
  `id_etat` int(11) NOT NULL,
  `id_annee` int(11) NOT NULL,
  `date_debut_periode` date NOT NULL,
  `date_fin_periode` date NOT NULL,
  `total_enseignants` int(11) NOT NULL DEFAULT 0,
  `total_volume_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_heures_payables` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_global` decimal(12,2) NOT NULL DEFAULT 0.00,
  `statut_paiement` enum('PREPARE','VALIDE','PAYE') NOT NULL DEFAULT 'PREPARE',
  `date_generation` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Structure de la table `etat_paiement_detail`
--

CREATE TABLE `etat_paiement_detail` (
  `id_detail` int(11) NOT NULL,
  `id_etat` int(11) NOT NULL,
  `id_enseignant` int(11) NOT NULL,
  `statut_enseignant` enum('PERMANENT','VACATAIRE') NOT NULL,
  `total_volume_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `charge_statutaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_complementaires` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_payables` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taux_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_individuel` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `filiere`
--

CREATE TABLE `filiere` (
  `id_filiere` int(11) NOT NULL,
  `nom_filiere` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_departement` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `filiere`
--

INSERT INTO `filiere` (`id_filiere`, `nom_filiere`, `description`, `actif`, `id_departement`) VALUES
(1, 'Informatique et Sciences du Numérique', 'Filière actuelle de l’UVCI.', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `grade`
--

CREATE TABLE `grade` (
  `id_grade` int(11) NOT NULL,
  `libelle_grade` varchar(100) NOT NULL,
  `charge_statutaire` decimal(10,2) NOT NULL DEFAULT 0.00
) ;

--
-- Déchargement des données de la table `grade`
--

INSERT INTO `grade` (`id_grade`, `libelle_grade`, `charge_statutaire`) VALUES
(1, 'Assistant', 240.00),
(2, 'Maître-Assistant', 180.00),
(4, 'Professeur', 90.00);

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

CREATE TABLE `paiement` (
  `id_paiement` int(11) NOT NULL,
  `volume_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `volume_complementaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `volume_a_payer` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_generation` datetime NOT NULL DEFAULT current_timestamp(),
  `statut_paiement` enum('GENERE','PAYE','ANNULE') NOT NULL DEFAULT 'GENERE',
  `id_enseignant` int(11) NOT NULL,
  `id_annee` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `id_genere_par` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametre_calcul`
--

CREATE TABLE `parametre_calcul` (
  `id_parametre` int(11) NOT NULL,
  `type_activite` enum('CREATION_RESSOURCE','MISE_A_JOUR_RESSOURCE') NOT NULL,
  `niveau_complexite` enum('NIVEAU_1','NIVEAU_2','NIVEAU_3') NOT NULL,
  `coefficient` decimal(10,3) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Déchargement des données de la table `parametre_calcul`
--

INSERT INTO `parametre_calcul` (`id_parametre`, `type_activite`, `niveau_complexite`, `coefficient`, `actif`) VALUES
(1, 'CREATION_RESSOURCE', 'NIVEAU_1', 0.400, 1),
(2, 'CREATION_RESSOURCE', 'NIVEAU_2', 0.750, 1),
(3, 'CREATION_RESSOURCE', 'NIVEAU_3', 1.500, 1),
(4, 'MISE_A_JOUR_RESSOURCE', 'NIVEAU_1', 0.200, 1),
(5, 'MISE_A_JOUR_RESSOURCE', 'NIVEAU_2', 0.375, 1),
(6, 'MISE_A_JOUR_RESSOURCE', 'NIVEAU_3', 0.750, 1);

-- --------------------------------------------------------

--
-- Structure de la table `ressource_pedagogique`
--

CREATE TABLE `ressource_pedagogique` (
  `id_ressource` int(11) NOT NULL,
  `titre_ressource` varchar(200) NOT NULL,
  `type_ressource` enum('CONTENU TEXTUEL','VIDEO PEDAGOGIQUE','DOCUMENT PEDAGOGIQUE','QUIZ','ACTIVITE INTERACTIVE','EVALUATION') NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_cours` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ressource_pedagogique`
--

INSERT INTO `ressource_pedagogique` (`id_ressource`, `titre_ressource`, `type_ressource`, `description`, `chemin_fichier`, `date_creation`, `actif`, `id_cours`) VALUES
(4, 'documents pédagogiques', '', '', NULL, '2026-05-16 23:13:57', 1, 7),
(5, 'documents pédagogiques', '', '', NULL, '2026-05-16 23:15:19', 1, 7),
(6, 'Vidéo pédagogique', '', '', NULL, '2026-05-16 23:17:44', 1, 9),
(7, 'quiz', '', '', NULL, '2026-05-16 23:20:49', 1, 8),
(8, 'Contenus textuels', '', '', NULL, '2026-05-16 23:22:49', 1, 10);

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

CREATE TABLE `role` (
  `id_role` int(11) NOT NULL,
  `libelle_role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role`
--

INSERT INTO `role` (`id_role`, `libelle_role`) VALUES
(1, 'ADMINISTRATEUR'),
(3, 'ENSEIGNANT'),
(2, 'SECRETAIRE_PRINCIPAL');

-- --------------------------------------------------------

--
-- Structure de la table `taux_horaire`
--

CREATE TABLE `taux_horaire` (
  `id_taux` int(11) NOT NULL,
  `statut` enum('PERMANENT','VACATAIRE') NOT NULL,
  `id_grade` int(11) DEFAULT NULL,
  `niveau` enum('LICENCE','MASTER') NOT NULL,
  `id_annee` int(11) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date_effet` date NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Déchargement des données de la table `taux_horaire`
--

INSERT INTO `taux_horaire` (`id_taux`, `statut`, `id_grade`, `niveau`, `id_annee`, `categorie`, `montant`, `date_effet`, `actif`) VALUES
(6, 'PERMANENT', 1, 'LICENCE', 1, 'PERMANENT_ASSISTANT_LICENCE', 5000.00, '2026-05-16', 1),
(7, 'PERMANENT', 1, 'MASTER', 1, 'PERMANENT_ASSISTANT_MASTER', 7000.00, '2026-05-16', 1),
(8, 'PERMANENT', 2, 'LICENCE', 1, 'PERMANENT_MAîTRE_ASSISTANT_LICENCE', 6000.00, '2026-05-16', 1),
(9, 'PERMANENT', 2, 'MASTER', 1, 'PERMANENT_MAîTRE_ASSISTANT_MASTER', 8000.00, '2026-05-16', 1),
(10, 'PERMANENT', 4, 'LICENCE', 1, 'PERMANENT_PROFESSEUR_LICENCE', 9000.00, '2026-05-16', 1),
(11, 'PERMANENT', 4, 'MASTER', 1, 'PERMANENT_PROFESSEUR_MASTER', 10000.00, '2026-05-16', 1),
(12, 'VACATAIRE', 2, 'LICENCE', 1, 'VACATAIRE_MAîTRE_ASSISTANT_LICENCE', 6000.00, '2026-05-16', 1),
(13, 'VACATAIRE', 2, 'MASTER', 1, 'VACATAIRE_MAîTRE_ASSISTANT_MASTER', 8000.00, '2026-05-16', 1);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11) NOT NULL,
  `login` varchar(100) NOT NULL,
  `mot_de_passe_hash` varchar(255) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_role` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `login`, `mot_de_passe_hash`, `actif`, `date_creation`, `id_role`) VALUES
(1, 'admin', '$2y$10$DmQYfg2zXx0mXKijM6P0guE2gNZSTyvfEvwBQseJljJcN5k2xcW7i', 1, '2026-05-07 19:19:41', 1),
(2, 'sec_principal', '$2y$10$DmQYfg2zXx0mXKijM6P0guE2gNZSTyvfEvwBQseJljJcN5k2xcW7i', 1, '2026-05-07 19:19:41', 2),
(6, 'kouassi_bd', '$2y$10$yzfbMfvJfZUWICOV0rP8z.fQvLIGxpTU9JkdM69oAKz2vS0QU/wY2', 1, '2026-05-16 22:38:37', 3),
(7, 'yao_bda', '$2y$10$PdM7F8/pRg1e9OpL3y6r3eFtKjfqELD4IvP7WZJRvgqaXQDLZoqfO', 1, '2026-05-16 22:39:17', 3),
(8, 'koffi_das', '$2y$10$mm9/.2V/vDbF/2IdICeQEeVGwOOKNo33fYvRReln9Qv9w1f6TrgHq', 1, '2026-05-16 22:39:54', 3),
(9, 'atta_cio', '$2y$10$1oFqZKMN0LeZaTwxKX4axOBamR6VI0SGyA3A/.3uim2ood8ReoplO', 1, '2026-05-16 22:40:28', 3);

-- --------------------------------------------------------

--
-- Structure de la table `validation_activite`
--

CREATE TABLE `validation_activite` (
  `id_validation` int(11) NOT NULL,
  `date_validation` datetime NOT NULL DEFAULT current_timestamp(),
  `decision` enum('VALIDEE','REJETEE') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `id_activite` int(11) NOT NULL,
  `id_validateur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `validation_activite`
--

INSERT INTO `validation_activite` (`id_validation`, `date_validation`, `decision`, `commentaire`, `id_activite`, `id_validateur`) VALUES
(2, '2026-05-16 23:23:52', 'REJETEE', 'Activité rejetée.', 5, 2),
(3, '2026-05-16 23:24:03', 'REJETEE', 'Activité rejetée.', 6, 2),
(4, '2026-05-16 23:24:07', 'VALIDEE', 'Activité validée.', 4, 2),
(5, '2026-05-16 23:24:11', 'VALIDEE', 'Activité validée.', 2, 2),
(6, '2026-05-16 23:24:14', 'VALIDEE', 'Activité validée.', 3, 2);

--
-- Déclencheurs `validation_activite`
--
DELIMITER $$
CREATE TRIGGER `trg_validation_ai` AFTER INSERT ON `validation_activite` FOR EACH ROW BEGIN
    UPDATE activite_pedagogique
    SET statut_validation = NEW.decision
    WHERE id_activite = NEW.id_activite;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_cours_filieres`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_cours_filieres` (
`id_cours` int(11)
,`code_cours` varchar(50)
,`intitule_cours` varchar(200)
,`nombre_heures` decimal(10,2)
,`nombre_credits` int(11)
,`niveau` enum('L1','L2','L3','M1','M2')
,`semestre` enum('S1','S2','S3','S4','S5','S6')
,`nom_filiere` varchar(150)
,`nom_departement` varchar(150)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_enseignants_depasse_charge`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_enseignants_depasse_charge` (
`id_enseignant` int(11)
,`enseignant` varchar(251)
,`nom_departement` varchar(150)
,`libelle_grade` varchar(100)
,`charge_statutaire` decimal(10,2)
,`libelle_annee` varchar(20)
,`volume_total` decimal(32,2)
,`heures_complementaires` decimal(33,2)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_statistiques_globales`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_statistiques_globales` (
`total_enseignants` bigint(21)
,`total_cours` bigint(21)
,`total_ressources` bigint(21)
,`total_activites` bigint(21)
,`volume_horaire_valide` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_volume_par_enseignant`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_volume_par_enseignant` (
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_cours_filieres`
--
DROP TABLE IF EXISTS `vue_cours_filieres`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_cours_filieres`  AS SELECT `c`.`id_cours` AS `id_cours`, `c`.`code_cours` AS `code_cours`, `c`.`intitule_cours` AS `intitule_cours`, `c`.`nombre_heures` AS `nombre_heures`, `c`.`nombre_credits` AS `nombre_credits`, `cf`.`niveau` AS `niveau`, `cf`.`semestre` AS `semestre`, `f`.`nom_filiere` AS `nom_filiere`, `d`.`nom_departement` AS `nom_departement` FROM (((`cours` `c` join `cours_filiere` `cf` on(`cf`.`id_cours` = `c`.`id_cours`)) join `filiere` `f` on(`f`.`id_filiere` = `cf`.`id_filiere`)) join `departement` `d` on(`d`.`id_departement` = `f`.`id_departement`)) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_enseignants_depasse_charge`
--
DROP TABLE IF EXISTS `vue_enseignants_depasse_charge`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_enseignants_depasse_charge`  AS SELECT `e`.`id_enseignant` AS `id_enseignant`, concat(`e`.`nom`,' ',`e`.`prenoms`) AS `enseignant`, `d`.`nom_departement` AS `nom_departement`, `g`.`libelle_grade` AS `libelle_grade`, `g`.`charge_statutaire` AS `charge_statutaire`, `a`.`libelle_annee` AS `libelle_annee`, coalesce(sum(`ap`.`volume_horaire_calcule`),0) AS `volume_total`, greatest(0,coalesce(sum(`ap`.`volume_horaire_calcule`),0) - `g`.`charge_statutaire`) AS `heures_complementaires` FROM ((((`enseignant` `e` join `grade` `g` on(`g`.`id_grade` = `e`.`id_grade`)) join `departement` `d` on(`d`.`id_departement` = `e`.`id_departement`)) join `activite_pedagogique` `ap` on(`ap`.`id_enseignant` = `e`.`id_enseignant`)) join `annee_academique` `a` on(`a`.`id_annee` = `ap`.`id_annee`)) WHERE `e`.`statut` = 'PERMANENT' AND `ap`.`statut_validation` = 'VALIDEE' GROUP BY `e`.`id_enseignant`, `e`.`nom`, `e`.`prenoms`, `d`.`nom_departement`, `g`.`libelle_grade`, `g`.`charge_statutaire`, `a`.`libelle_annee` HAVING `volume_total` > `g`.`charge_statutaire` ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_statistiques_globales`
--
DROP TABLE IF EXISTS `vue_statistiques_globales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_statistiques_globales`  AS SELECT count(distinct `e`.`id_enseignant`) AS `total_enseignants`, count(distinct `c`.`id_cours`) AS `total_cours`, count(distinct `r`.`id_ressource`) AS `total_ressources`, count(distinct `ap`.`id_activite`) AS `total_activites`, coalesce(sum(case when `ap`.`statut_validation` = 'VALIDEE' then `ap`.`volume_horaire_calcule` else 0 end),0) AS `volume_horaire_valide` FROM (((`enseignant` `e` left join `cours` `c` on(`c`.`actif` = 1)) left join `ressource_pedagogique` `r` on(`r`.`actif` = 1)) left join `activite_pedagogique` `ap` on(`ap`.`id_enseignant` = `e`.`id_enseignant`)) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_volume_par_enseignant`
--
DROP TABLE IF EXISTS `vue_volume_par_enseignant`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_volume_par_enseignant`  AS SELECT `e`.`id_enseignant` AS `id_enseignant`, `e`.`matricule` AS `matricule`, concat(`e`.`nom`,' ',`e`.`prenoms`) AS `enseignant`, `e`.`statut` AS `statut`, `d`.`nom_departement` AS `nom_departement`, `a`.`id_annee` AS `id_annee`, `a`.`libelle_annee` AS `libelle_annee`, coalesce(sum(`ap`.`volume_horaire_calcule`),0) AS `volume_total` FROM (((`enseignant` `e` join `departement` `d` on(`d`.`id_departement` = `e`.`id_departement`)) join `annee_academique` `a`) left join `activite_pedagogique` `ap` on(`ap`.`id_enseignant` = `e`.`id_enseignant` and `ap`.`id_annee` = `a`.`id_annee` and `ap`.`statut_validation` = 'VALIDEE')) GROUP BY `e`.`id_enseignant`, `e`.`matricule`, `e`.`nom`, `e`.`prenoms`, `e`.`statut`, `d`.`nom_departement`, `a`.`id_annee`, `a`.`libelle_annee` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activite_pedagogique`
--
ALTER TABLE `activite_pedagogique`
  ADD PRIMARY KEY (`id_activite`),
  ADD KEY `fk_activite_ressource` (`id_ressource`),
  ADD KEY `fk_activite_parametre` (`id_parametre`),
  ADD KEY `fk_activite_saisi_par` (`id_saisi_par`),
  ADD KEY `idx_activite_enseignant` (`id_enseignant`),
  ADD KEY `idx_activite_cours` (`id_cours`),
  ADD KEY `idx_activite_annee` (`id_annee`),
  ADD KEY `idx_activite_statut` (`statut_validation`);

--
-- Index pour la table `annee_academique`
--
ALTER TABLE `annee_academique`
  ADD PRIMARY KEY (`id_annee`),
  ADD UNIQUE KEY `libelle_annee` (`libelle_annee`);

--
-- Index pour la table `cours`
--
ALTER TABLE `cours`
  ADD PRIMARY KEY (`id_cours`),
  ADD UNIQUE KEY `code_cours` (`code_cours`),
  ADD KEY `fk_cours_enseignant` (`id_enseignant`);

--
-- Index pour la table `cours_filiere`
--
ALTER TABLE `cours_filiere`
  ADD PRIMARY KEY (`id_cours`,`id_filiere`,`niveau`,`semestre`),
  ADD KEY `idx_cours_filiere_filiere` (`id_filiere`);

--
-- Index pour la table `departement`
--
ALTER TABLE `departement`
  ADD PRIMARY KEY (`id_departement`),
  ADD UNIQUE KEY `nom_departement` (`nom_departement`);

--
-- Index pour la table `enseignant`
--
ALTER TABLE `enseignant`
  ADD PRIMARY KEY (`id_enseignant`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_enseignant_departement` (`id_departement`),
  ADD KEY `idx_enseignant_grade` (`id_grade`),
  ADD KEY `idx_enseignant_statut` (`statut`),
  ADD KEY `fk_enseignant_taux` (`id_taux`);

--
-- Index pour la table `enseignant_taux_horaire`
--
ALTER TABLE `enseignant_taux_horaire`
  ADD PRIMARY KEY (`id_enseignant_taux`),
  ADD UNIQUE KEY `uk_enseignant_taux_unique` (`id_enseignant`,`id_taux`),
  ADD KEY `fk_enseignant_taux_taux` (`id_taux`);

--
-- Index pour la table `etat_paiement`
--
ALTER TABLE `etat_paiement`
  ADD PRIMARY KEY (`id_etat`),
  ADD UNIQUE KEY `uk_etat_paiement_periode` (`id_annee`,`date_debut_periode`,`date_fin_periode`);

--
-- Index pour la table `etat_paiement_detail`
--
ALTER TABLE `etat_paiement_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD UNIQUE KEY `uk_detail_etat_enseignant` (`id_etat`,`id_enseignant`),
  ADD KEY `fk_detail_enseignant` (`id_enseignant`);

--
-- Index pour la table `filiere`
--
ALTER TABLE `filiere`
  ADD PRIMARY KEY (`id_filiere`),
  ADD UNIQUE KEY `uk_filiere_departement` (`nom_filiere`,`id_departement`),
  ADD KEY `idx_filiere_departement` (`id_departement`);

--
-- Index pour la table `grade`
--
ALTER TABLE `grade`
  ADD PRIMARY KEY (`id_grade`),
  ADD UNIQUE KEY `libelle_grade` (`libelle_grade`);

--
-- Index pour la table `paiement`
--
ALTER TABLE `paiement`
  ADD PRIMARY KEY (`id_paiement`),
  ADD UNIQUE KEY `uk_paiement_enseignant_annee` (`id_enseignant`,`id_annee`),
  ADD KEY `fk_paiement_taux` (`id_taux`),
  ADD KEY `fk_paiement_genere_par` (`id_genere_par`),
  ADD KEY `idx_paiement_enseignant` (`id_enseignant`),
  ADD KEY `idx_paiement_annee` (`id_annee`);

--
-- Index pour la table `parametre_calcul`
--
ALTER TABLE `parametre_calcul`
  ADD PRIMARY KEY (`id_parametre`),
  ADD UNIQUE KEY `uk_parametre_calcul` (`type_activite`,`niveau_complexite`);

--
-- Index pour la table `ressource_pedagogique`
--
ALTER TABLE `ressource_pedagogique`
  ADD PRIMARY KEY (`id_ressource`),
  ADD KEY `idx_ressource_cours` (`id_cours`);

--
-- Index pour la table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id_role`),
  ADD UNIQUE KEY `libelle_role` (`libelle_role`);

--
-- Index pour la table `taux_horaire`
--
ALTER TABLE `taux_horaire`
  ADD PRIMARY KEY (`id_taux`),
  ADD UNIQUE KEY `uk_taux_horaire` (`categorie`,`date_effet`),
  ADD UNIQUE KEY `uk_taux_unique` (`statut`,`id_grade`,`niveau`,`id_annee`),
  ADD KEY `fk_taux_grade` (`id_grade`),
  ADD KEY `fk_taux_annee` (`id_annee`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `idx_utilisateur_role` (`id_role`);

--
-- Index pour la table `validation_activite`
--
ALTER TABLE `validation_activite`
  ADD PRIMARY KEY (`id_validation`),
  ADD UNIQUE KEY `uk_validation_activite` (`id_activite`),
  ADD KEY `idx_validation_validateur` (`id_validateur`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activite_pedagogique`
--
ALTER TABLE `activite_pedagogique`
  MODIFY `id_activite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `annee_academique`
--
ALTER TABLE `annee_academique`
  MODIFY `id_annee` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cours`
--
ALTER TABLE `cours`
  MODIFY `id_cours` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `departement`
--
ALTER TABLE `departement`
  MODIFY `id_departement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `enseignant`
--
ALTER TABLE `enseignant`
  MODIFY `id_enseignant` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `enseignant_taux_horaire`
--
ALTER TABLE `enseignant_taux_horaire`
  MODIFY `id_enseignant_taux` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `etat_paiement`
--
ALTER TABLE `etat_paiement`
  MODIFY `id_etat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etat_paiement_detail`
--
ALTER TABLE `etat_paiement_detail`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `filiere`
--
ALTER TABLE `filiere`
  MODIFY `id_filiere` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `grade`
--
ALTER TABLE `grade`
  MODIFY `id_grade` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiement`
--
ALTER TABLE `paiement`
  MODIFY `id_paiement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `parametre_calcul`
--
ALTER TABLE `parametre_calcul`
  MODIFY `id_parametre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ressource_pedagogique`
--
ALTER TABLE `ressource_pedagogique`
  MODIFY `id_ressource` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `role`
--
ALTER TABLE `role`
  MODIFY `id_role` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `taux_horaire`
--
ALTER TABLE `taux_horaire`
  MODIFY `id_taux` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `validation_activite`
--
ALTER TABLE `validation_activite`
  MODIFY `id_validation` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activite_pedagogique`
--
ALTER TABLE `activite_pedagogique`
  ADD CONSTRAINT `fk_activite_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activite_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activite_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activite_parametre` FOREIGN KEY (`id_parametre`) REFERENCES `parametre_calcul` (`id_parametre`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activite_ressource` FOREIGN KEY (`id_ressource`) REFERENCES `ressource_pedagogique` (`id_ressource`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activite_saisi_par` FOREIGN KEY (`id_saisi_par`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `cours`
--
ALTER TABLE `cours`
  ADD CONSTRAINT `fk_cours_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `cours_filiere`
--
ALTER TABLE `cours_filiere`
  ADD CONSTRAINT `fk_cours_filiere_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cours_filiere_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filiere` (`id_filiere`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `enseignant`
--
ALTER TABLE `enseignant`
  ADD CONSTRAINT `fk_enseignant_departement` FOREIGN KEY (`id_departement`) REFERENCES `departement` (`id_departement`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignant_grade` FOREIGN KEY (`id_grade`) REFERENCES `grade` (`id_grade`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignant_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignant_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `enseignant_taux_horaire`
--
ALTER TABLE `enseignant_taux_horaire`
  ADD CONSTRAINT `fk_enseignant_taux_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enseignant_taux_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `etat_paiement`
--
ALTER TABLE `etat_paiement`
  ADD CONSTRAINT `fk_etat_paiement_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `etat_paiement_detail`
--
ALTER TABLE `etat_paiement_detail`
  ADD CONSTRAINT `fk_detail_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detail_etat` FOREIGN KEY (`id_etat`) REFERENCES `etat_paiement` (`id_etat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `filiere`
--
ALTER TABLE `filiere`
  ADD CONSTRAINT `fk_filiere_departement` FOREIGN KEY (`id_departement`) REFERENCES `departement` (`id_departement`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `paiement`
--
ALTER TABLE `paiement`
  ADD CONSTRAINT `fk_paiement_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_paiement_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_paiement_genere_par` FOREIGN KEY (`id_genere_par`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_paiement_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `ressource_pedagogique`
--
ALTER TABLE `ressource_pedagogique`
  ADD CONSTRAINT `fk_ressource_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `taux_horaire`
--
ALTER TABLE `taux_horaire`
  ADD CONSTRAINT `fk_taux_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_taux_grade` FOREIGN KEY (`id_grade`) REFERENCES `grade` (`id_grade`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `fk_utilisateur_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `validation_activite`
--
ALTER TABLE `validation_activite`
  ADD CONSTRAINT `fk_validation_activite` FOREIGN KEY (`id_activite`) REFERENCES `activite_pedagogique` (`id_activite`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_validation_validateur` FOREIGN KEY (`id_validateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
