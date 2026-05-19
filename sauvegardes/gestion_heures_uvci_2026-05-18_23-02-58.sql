-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gestion_heures_uvci
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `gestion_heures_uvci`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `gestion_heures_uvci` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `gestion_heures_uvci`;

--
-- Table structure for table `activite_pedagogique`
--

DROP TABLE IF EXISTS `activite_pedagogique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activite_pedagogique` (
  `id_activite` int(11) NOT NULL AUTO_INCREMENT,
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
  `id_saisi_par` int(11) NOT NULL,
  PRIMARY KEY (`id_activite`),
  KEY `fk_activite_ressource` (`id_ressource`),
  KEY `fk_activite_parametre` (`id_parametre`),
  KEY `fk_activite_saisi_par` (`id_saisi_par`),
  KEY `idx_activite_enseignant` (`id_enseignant`),
  KEY `idx_activite_cours` (`id_cours`),
  KEY `idx_activite_annee` (`id_annee`),
  KEY `idx_activite_statut` (`statut_validation`),
  CONSTRAINT `fk_activite_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_parametre` FOREIGN KEY (`id_parametre`) REFERENCES `parametre_calcul` (`id_parametre`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_ressource` FOREIGN KEY (`id_ressource`) REFERENCES `ressource_pedagogique` (`id_ressource`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_saisi_par` FOREIGN KEY (`id_saisi_par`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE,
  CONSTRAINT `chk_activite_nombre_heures` CHECK (`nombre_heures` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activite_pedagogique`
--

LOCK TABLES `activite_pedagogique` WRITE;
/*!40000 ALTER TABLE `activite_pedagogique` DISABLE KEYS */;
INSERT INTO `activite_pedagogique` VALUES (2,'CREATION_RESSOURCE','NIVEAU_1',10.00,40,16.00,'VALIDEE','2026-05-16 23:13:57','',3,7,4,1,1,2),(3,'MISE_A_JOUR_RESSOURCE','NIVEAU_1',9.00,36,7.20,'VALIDEE','2026-05-16 23:15:19','',5,7,5,1,4,2),(4,'CREATION_RESSOURCE','NIVEAU_2',5.00,20,15.00,'VALIDEE','2026-05-16 23:17:44','',4,9,6,1,2,2),(5,'MISE_A_JOUR_RESSOURCE','NIVEAU_1',29.00,116,23.20,'REJETEE','2026-05-16 23:20:49','',5,8,7,1,4,2),(6,'CREATION_RESSOURCE','NIVEAU_1',39.50,158,63.20,'REJETEE','2026-05-16 23:22:49','',6,10,8,1,1,2);
/*!40000 ALTER TABLE `activite_pedagogique` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_activite_bi
BEFORE INSERT ON activite_pedagogique
FOR EACH ROW
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_activite_bu
BEFORE UPDATE ON activite_pedagogique
FOR EACH ROW
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `annee_academique`
--

DROP TABLE IF EXISTS `annee_academique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annee_academique` (
  `id_annee` int(11) NOT NULL AUTO_INCREMENT,
  `libelle_annee` varchar(20) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `est_active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_annee`),
  UNIQUE KEY `libelle_annee` (`libelle_annee`),
  CONSTRAINT `chk_annee_dates` CHECK (`date_fin` > `date_debut`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annee_academique`
--

LOCK TABLES `annee_academique` WRITE;
/*!40000 ALTER TABLE `annee_academique` DISABLE KEYS */;
INSERT INTO `annee_academique` VALUES (1,'2025-2026','2025-10-01','2026-09-30',1),(3,'2024-2025','2024-09-16','2025-07-16',0);
/*!40000 ALTER TABLE `annee_academique` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cours`
--

DROP TABLE IF EXISTS `cours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL AUTO_INCREMENT,
  `code_cours` varchar(50) NOT NULL,
  `intitule_cours` varchar(200) NOT NULL,
  `id_enseignant` int(11) DEFAULT NULL,
  `nombre_heures` decimal(10,2) NOT NULL,
  `nb_sequences` int(11) NOT NULL DEFAULT 0,
  `nombre_credits` int(11) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `niveau` enum('LICENCE','MASTER') NOT NULL DEFAULT 'LICENCE',
  PRIMARY KEY (`id_cours`),
  UNIQUE KEY `code_cours` (`code_cours`),
  KEY `fk_cours_enseignant` (`id_enseignant`),
  CONSTRAINT `fk_cours_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_cours_heures` CHECK (`nombre_heures` > 0),
  CONSTRAINT `chk_cours_credits` CHECK (`nombre_credits` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cours`
--

LOCK TABLES `cours` WRITE;
/*!40000 ALTER TABLE `cours` DISABLE KEYS */;
INSERT INTO `cours` VALUES (7,'INF-L2-BD-001','Modelisation de bases de données',5,40.00,160,4,1,'LICENCE'),(8,'INF-L3-DAS-002','Développement Web',5,40.00,160,4,1,'LICENCE'),(9,'INF-M1-BDA-001','Introduction au Big Data',4,20.00,80,2,1,'LICENCE'),(10,'INF-M2-CIO-002','Sécurité des systèmes distribués',6,40.00,160,4,1,'LICENCE');
/*!40000 ALTER TABLE `cours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cours_filiere`
--

DROP TABLE IF EXISTS `cours_filiere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cours_filiere` (
  `id_cours` int(11) NOT NULL,
  `id_filiere` int(11) NOT NULL,
  `niveau` enum('L1','L2','L3','M1','M2') NOT NULL,
  `semestre` enum('S1','S2','S3','S4','S5','S6') NOT NULL,
  PRIMARY KEY (`id_cours`,`id_filiere`,`niveau`,`semestre`),
  KEY `idx_cours_filiere_filiere` (`id_filiere`),
  CONSTRAINT `fk_cours_filiere_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cours_filiere_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filiere` (`id_filiere`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cours_filiere`
--

LOCK TABLES `cours_filiere` WRITE;
/*!40000 ALTER TABLE `cours_filiere` DISABLE KEYS */;
INSERT INTO `cours_filiere` VALUES (7,1,'L2','S3'),(8,1,'L3','S3'),(9,1,'M1','S1'),(10,1,'M2','S2');
/*!40000 ALTER TABLE `cours_filiere` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departement`
--

DROP TABLE IF EXISTS `departement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departement` (
  `id_departement` int(11) NOT NULL AUTO_INCREMENT,
  `nom_departement` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_departement`),
  UNIQUE KEY `nom_departement` (`nom_departement`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departement`
--

LOCK TABLES `departement` WRITE;
/*!40000 ALTER TABLE `departement` DISABLE KEYS */;
INSERT INTO `departement` VALUES (1,'Sciences et Technologies','Département actuel de l’UVCI.',1);
/*!40000 ALTER TABLE `departement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enseignant`
--

DROP TABLE IF EXISTS `enseignant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enseignant` (
  `id_enseignant` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `statut` enum('PERMANENT','VACATAIRE') NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_departement` int(11) NOT NULL,
  `id_grade` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_enseignant`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `id_utilisateur` (`id_utilisateur`),
  KEY `idx_enseignant_departement` (`id_departement`),
  KEY `idx_enseignant_grade` (`id_grade`),
  KEY `idx_enseignant_statut` (`statut`),
  KEY `fk_enseignant_taux` (`id_taux`),
  CONSTRAINT `fk_enseignant_departement` FOREIGN KEY (`id_departement`) REFERENCES `departement` (`id_departement`) ON UPDATE CASCADE,
  CONSTRAINT `fk_enseignant_grade` FOREIGN KEY (`id_grade`) REFERENCES `grade` (`id_grade`) ON UPDATE CASCADE,
  CONSTRAINT `fk_enseignant_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE,
  CONSTRAINT `fk_enseignant_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enseignant`
--

LOCK TABLES `enseignant` WRITE;
/*!40000 ALTER TABLE `enseignant` DISABLE KEYS */;
INSERT INTO `enseignant` VALUES (3,'KOUASSI','Jean Michel','kouassi@uvci.ci','0708080808','PERMANENT',1,1,1,6,6),(4,'YAO','Stéphane','yao@uvci.ci','0708080807','PERMANENT',1,1,2,8,7),(5,'KOFFI','Armand','koffi@uvci.ci','0708080806','VACATAIRE',1,1,2,12,8),(6,'ATTA','Clarisse','atta@uvci.ci','0708080805','VACATAIRE',1,1,2,13,9);
/*!40000 ALTER TABLE `enseignant` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_enseignant_bi
BEFORE INSERT ON enseignant
FOR EACH ROW
BEGIN
    IF NEW.statut = 'PERMANENT' AND NEW.id_grade IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un enseignant permanent doit avoir un grade.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_enseignant_bu
BEFORE UPDATE ON enseignant
FOR EACH ROW
BEGIN
    IF NEW.statut = 'PERMANENT' AND NEW.id_grade IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un enseignant permanent doit avoir un grade.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `enseignant_taux_horaire`
--

DROP TABLE IF EXISTS `enseignant_taux_horaire`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enseignant_taux_horaire` (
  `id_enseignant_taux` int(11) NOT NULL AUTO_INCREMENT,
  `id_enseignant` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `date_affectation` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_enseignant_taux`),
  UNIQUE KEY `uk_enseignant_taux_unique` (`id_enseignant`,`id_taux`),
  KEY `fk_enseignant_taux_taux` (`id_taux`),
  CONSTRAINT `fk_enseignant_taux_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enseignant_taux_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enseignant_taux_horaire`
--

LOCK TABLES `enseignant_taux_horaire` WRITE;
/*!40000 ALTER TABLE `enseignant_taux_horaire` DISABLE KEYS */;
INSERT INTO `enseignant_taux_horaire` VALUES (1,3,6,1,'2026-05-16 22:46:15'),(2,3,7,1,'2026-05-16 22:46:15'),(3,4,8,1,'2026-05-16 22:49:35'),(4,4,9,1,'2026-05-16 22:49:35'),(5,5,12,1,'2026-05-16 22:51:48'),(6,6,13,1,'2026-05-16 22:53:28');
/*!40000 ALTER TABLE `enseignant_taux_horaire` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etat_paiement`
--

DROP TABLE IF EXISTS `etat_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etat_paiement` (
  `id_etat` int(11) NOT NULL AUTO_INCREMENT,
  `id_annee` int(11) NOT NULL,
  `date_debut_periode` date NOT NULL,
  `date_fin_periode` date NOT NULL,
  `total_enseignants` int(11) NOT NULL DEFAULT 0,
  `total_volume_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_heures_payables` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_global` decimal(12,2) NOT NULL DEFAULT 0.00,
  `statut_paiement` enum('PREPARE','VALIDE','PAYE') NOT NULL DEFAULT 'PREPARE',
  `date_generation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_etat`),
  UNIQUE KEY `uk_etat_paiement_periode` (`id_annee`,`date_debut_periode`,`date_fin_periode`),
  CONSTRAINT `fk_etat_paiement_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  CONSTRAINT `chk_etat_paiement_periode` CHECK (`date_fin_periode` >= `date_debut_periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etat_paiement`
--

LOCK TABLES `etat_paiement` WRITE;
/*!40000 ALTER TABLE `etat_paiement` DISABLE KEYS */;
/*!40000 ALTER TABLE `etat_paiement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etat_paiement_detail`
--

DROP TABLE IF EXISTS `etat_paiement_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etat_paiement_detail` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `id_etat` int(11) NOT NULL,
  `id_enseignant` int(11) NOT NULL,
  `statut_enseignant` enum('PERMANENT','VACATAIRE') NOT NULL,
  `total_volume_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `charge_statutaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_complementaires` decimal(10,2) NOT NULL DEFAULT 0.00,
  `heures_payables` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taux_horaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_individuel` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detail`),
  UNIQUE KEY `uk_detail_etat_enseignant` (`id_etat`,`id_enseignant`),
  KEY `fk_detail_enseignant` (`id_enseignant`),
  CONSTRAINT `fk_detail_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_etat` FOREIGN KEY (`id_etat`) REFERENCES `etat_paiement` (`id_etat`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etat_paiement_detail`
--

LOCK TABLES `etat_paiement_detail` WRITE;
/*!40000 ALTER TABLE `etat_paiement_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `etat_paiement_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `filiere`
--

DROP TABLE IF EXISTS `filiere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `filiere` (
  `id_filiere` int(11) NOT NULL AUTO_INCREMENT,
  `nom_filiere` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_departement` int(11) NOT NULL,
  PRIMARY KEY (`id_filiere`),
  UNIQUE KEY `uk_filiere_departement` (`nom_filiere`,`id_departement`),
  KEY `idx_filiere_departement` (`id_departement`),
  CONSTRAINT `fk_filiere_departement` FOREIGN KEY (`id_departement`) REFERENCES `departement` (`id_departement`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `filiere`
--

LOCK TABLES `filiere` WRITE;
/*!40000 ALTER TABLE `filiere` DISABLE KEYS */;
INSERT INTO `filiere` VALUES (1,'Informatique et Sciences du Numérique','Filière actuelle de l’UVCI.',1,1);
/*!40000 ALTER TABLE `filiere` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade`
--

DROP TABLE IF EXISTS `grade`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade` (
  `id_grade` int(11) NOT NULL AUTO_INCREMENT,
  `libelle_grade` varchar(100) NOT NULL,
  `charge_statutaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_grade`),
  UNIQUE KEY `libelle_grade` (`libelle_grade`),
  CONSTRAINT `chk_grade_charge` CHECK (`charge_statutaire` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade`
--

LOCK TABLES `grade` WRITE;
/*!40000 ALTER TABLE `grade` DISABLE KEYS */;
INSERT INTO `grade` VALUES (1,'Assistant',240.00),(2,'Maître-Assistant',180.00),(4,'Professeur',90.00);
/*!40000 ALTER TABLE `grade` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paiement`
--

DROP TABLE IF EXISTS `paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paiement` (
  `id_paiement` int(11) NOT NULL AUTO_INCREMENT,
  `volume_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `volume_complementaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `volume_a_payer` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_generation` datetime NOT NULL DEFAULT current_timestamp(),
  `statut_paiement` enum('GENERE','PAYE','ANNULE') NOT NULL DEFAULT 'GENERE',
  `id_enseignant` int(11) NOT NULL,
  `id_annee` int(11) NOT NULL,
  `id_taux` int(11) NOT NULL,
  `id_genere_par` int(11) NOT NULL,
  PRIMARY KEY (`id_paiement`),
  UNIQUE KEY `uk_paiement_enseignant_annee` (`id_enseignant`,`id_annee`),
  KEY `fk_paiement_taux` (`id_taux`),
  KEY `fk_paiement_genere_par` (`id_genere_par`),
  KEY `idx_paiement_enseignant` (`id_enseignant`),
  KEY `idx_paiement_annee` (`id_annee`),
  CONSTRAINT `fk_paiement_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  CONSTRAINT `fk_paiement_enseignant` FOREIGN KEY (`id_enseignant`) REFERENCES `enseignant` (`id_enseignant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_paiement_genere_par` FOREIGN KEY (`id_genere_par`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE,
  CONSTRAINT `fk_paiement_taux` FOREIGN KEY (`id_taux`) REFERENCES `taux_horaire` (`id_taux`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paiement`
--

LOCK TABLES `paiement` WRITE;
/*!40000 ALTER TABLE `paiement` DISABLE KEYS */;
/*!40000 ALTER TABLE `paiement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parametre_calcul`
--

DROP TABLE IF EXISTS `parametre_calcul`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parametre_calcul` (
  `id_parametre` int(11) NOT NULL AUTO_INCREMENT,
  `type_activite` enum('CREATION_RESSOURCE','MISE_A_JOUR_RESSOURCE') NOT NULL,
  `niveau_complexite` enum('NIVEAU_1','NIVEAU_2','NIVEAU_3') NOT NULL,
  `coefficient` decimal(10,3) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_parametre`),
  UNIQUE KEY `uk_parametre_calcul` (`type_activite`,`niveau_complexite`),
  CONSTRAINT `chk_parametre_coefficient` CHECK (`coefficient` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametre_calcul`
--

LOCK TABLES `parametre_calcul` WRITE;
/*!40000 ALTER TABLE `parametre_calcul` DISABLE KEYS */;
INSERT INTO `parametre_calcul` VALUES (1,'CREATION_RESSOURCE','NIVEAU_1',0.400,1),(2,'CREATION_RESSOURCE','NIVEAU_2',0.750,1),(3,'CREATION_RESSOURCE','NIVEAU_3',1.500,1),(4,'MISE_A_JOUR_RESSOURCE','NIVEAU_1',0.200,1),(5,'MISE_A_JOUR_RESSOURCE','NIVEAU_2',0.375,1),(6,'MISE_A_JOUR_RESSOURCE','NIVEAU_3',0.750,1);
/*!40000 ALTER TABLE `parametre_calcul` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ressource_pedagogique`
--

DROP TABLE IF EXISTS `ressource_pedagogique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ressource_pedagogique` (
  `id_ressource` int(11) NOT NULL AUTO_INCREMENT,
  `titre_ressource` varchar(200) NOT NULL,
  `type_ressource` enum('CONTENU TEXTUEL','VIDEO PEDAGOGIQUE','DOCUMENT PEDAGOGIQUE','QUIZ','ACTIVITE INTERACTIVE','EVALUATION') NOT NULL,
  `description` text DEFAULT NULL,
  `chemin_fichier` varchar(255) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `id_cours` int(11) NOT NULL,
  PRIMARY KEY (`id_ressource`),
  KEY `idx_ressource_cours` (`id_cours`),
  CONSTRAINT `fk_ressource_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ressource_pedagogique`
--

LOCK TABLES `ressource_pedagogique` WRITE;
/*!40000 ALTER TABLE `ressource_pedagogique` DISABLE KEYS */;
INSERT INTO `ressource_pedagogique` VALUES (4,'documents pédagogiques','','',NULL,'2026-05-16 23:13:57',1,7),(5,'documents pédagogiques','','',NULL,'2026-05-16 23:15:19',1,7),(6,'Vidéo pédagogique','','',NULL,'2026-05-16 23:17:44',1,9),(7,'quiz','','',NULL,'2026-05-16 23:20:49',1,8),(8,'Contenus textuels','','',NULL,'2026-05-16 23:22:49',1,10);
/*!40000 ALTER TABLE `ressource_pedagogique` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `libelle_role` varchar(50) NOT NULL,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `libelle_role` (`libelle_role`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role`
--

LOCK TABLES `role` WRITE;
/*!40000 ALTER TABLE `role` DISABLE KEYS */;
INSERT INTO `role` VALUES (1,'ADMINISTRATEUR'),(3,'ENSEIGNANT'),(2,'SECRETAIRE_PRINCIPAL');
/*!40000 ALTER TABLE `role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taux_horaire`
--

DROP TABLE IF EXISTS `taux_horaire`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taux_horaire` (
  `id_taux` int(11) NOT NULL AUTO_INCREMENT,
  `statut` enum('PERMANENT','VACATAIRE') NOT NULL,
  `id_grade` int(11) DEFAULT NULL,
  `niveau` enum('LICENCE','MASTER') NOT NULL,
  `id_annee` int(11) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date_effet` date NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_taux`),
  UNIQUE KEY `uk_taux_horaire` (`categorie`,`date_effet`),
  UNIQUE KEY `uk_taux_unique` (`statut`,`id_grade`,`niveau`,`id_annee`),
  KEY `fk_taux_grade` (`id_grade`),
  KEY `fk_taux_annee` (`id_annee`),
  CONSTRAINT `fk_taux_annee` FOREIGN KEY (`id_annee`) REFERENCES `annee_academique` (`id_annee`) ON UPDATE CASCADE,
  CONSTRAINT `fk_taux_grade` FOREIGN KEY (`id_grade`) REFERENCES `grade` (`id_grade`) ON UPDATE CASCADE,
  CONSTRAINT `chk_taux_montant` CHECK (`montant` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taux_horaire`
--

LOCK TABLES `taux_horaire` WRITE;
/*!40000 ALTER TABLE `taux_horaire` DISABLE KEYS */;
INSERT INTO `taux_horaire` VALUES (6,'PERMANENT',1,'LICENCE',1,'PERMANENT_ASSISTANT_LICENCE',5000.00,'2026-05-16',1),(7,'PERMANENT',1,'MASTER',1,'PERMANENT_ASSISTANT_MASTER',7000.00,'2026-05-16',1),(8,'PERMANENT',2,'LICENCE',1,'PERMANENT_MAîTRE_ASSISTANT_LICENCE',6000.00,'2026-05-16',1),(9,'PERMANENT',2,'MASTER',1,'PERMANENT_MAîTRE_ASSISTANT_MASTER',8000.00,'2026-05-16',1),(10,'PERMANENT',4,'LICENCE',1,'PERMANENT_PROFESSEUR_LICENCE',9000.00,'2026-05-16',1),(11,'PERMANENT',4,'MASTER',1,'PERMANENT_PROFESSEUR_MASTER',10000.00,'2026-05-16',1),(12,'VACATAIRE',2,'LICENCE',1,'VACATAIRE_MAîTRE_ASSISTANT_LICENCE',6000.00,'2026-05-16',1),(13,'VACATAIRE',2,'MASTER',1,'VACATAIRE_MAîTRE_ASSISTANT_MASTER',8000.00,'2026-05-16',1);
/*!40000 ALTER TABLE `taux_horaire` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `mot_de_passe_hash` varchar(255) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `id_role` int(11) NOT NULL,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `login` (`login`),
  KEY `idx_utilisateur_role` (`id_role`),
  CONSTRAINT `fk_utilisateur_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateur`
--

LOCK TABLES `utilisateur` WRITE;
/*!40000 ALTER TABLE `utilisateur` DISABLE KEYS */;
INSERT INTO `utilisateur` VALUES (1,'admin','$2y$10$DmQYfg2zXx0mXKijM6P0guE2gNZSTyvfEvwBQseJljJcN5k2xcW7i',1,'2026-05-07 19:19:41',1),(2,'sec_principal','$2y$10$DmQYfg2zXx0mXKijM6P0guE2gNZSTyvfEvwBQseJljJcN5k2xcW7i',1,'2026-05-07 19:19:41',2),(6,'kouassi_bd','$2y$10$yzfbMfvJfZUWICOV0rP8z.fQvLIGxpTU9JkdM69oAKz2vS0QU/wY2',1,'2026-05-16 22:38:37',3),(7,'yao_bda','$2y$10$PdM7F8/pRg1e9OpL3y6r3eFtKjfqELD4IvP7WZJRvgqaXQDLZoqfO',1,'2026-05-16 22:39:17',3),(8,'koffi_das','$2y$10$mm9/.2V/vDbF/2IdICeQEeVGwOOKNo33fYvRReln9Qv9w1f6TrgHq',1,'2026-05-16 22:39:54',3),(9,'atta_cio','$2y$10$1oFqZKMN0LeZaTwxKX4axOBamR6VI0SGyA3A/.3uim2ood8ReoplO',1,'2026-05-16 22:40:28',3);
/*!40000 ALTER TABLE `utilisateur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `validation_activite`
--

DROP TABLE IF EXISTS `validation_activite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `validation_activite` (
  `id_validation` int(11) NOT NULL AUTO_INCREMENT,
  `date_validation` datetime NOT NULL DEFAULT current_timestamp(),
  `decision` enum('VALIDEE','REJETEE') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `id_activite` int(11) NOT NULL,
  `id_validateur` int(11) NOT NULL,
  PRIMARY KEY (`id_validation`),
  UNIQUE KEY `uk_validation_activite` (`id_activite`),
  KEY `idx_validation_validateur` (`id_validateur`),
  CONSTRAINT `fk_validation_activite` FOREIGN KEY (`id_activite`) REFERENCES `activite_pedagogique` (`id_activite`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_validation_validateur` FOREIGN KEY (`id_validateur`) REFERENCES `utilisateur` (`id_utilisateur`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `validation_activite`
--

LOCK TABLES `validation_activite` WRITE;
/*!40000 ALTER TABLE `validation_activite` DISABLE KEYS */;
INSERT INTO `validation_activite` VALUES (2,'2026-05-16 23:23:52','REJETEE','Activité rejetée.',5,2),(3,'2026-05-16 23:24:03','REJETEE','Activité rejetée.',6,2),(4,'2026-05-16 23:24:07','VALIDEE','Activité validée.',4,2),(5,'2026-05-16 23:24:11','VALIDEE','Activité validée.',2,2),(6,'2026-05-16 23:24:14','VALIDEE','Activité validée.',3,2);
/*!40000 ALTER TABLE `validation_activite` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_validation_ai
AFTER INSERT ON validation_activite
FOR EACH ROW
BEGIN
    UPDATE activite_pedagogique
    SET statut_validation = NEW.decision
    WHERE id_activite = NEW.id_activite;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Temporary table structure for view `vue_cours_filieres`
--

DROP TABLE IF EXISTS `vue_cours_filieres`;
/*!50001 DROP VIEW IF EXISTS `vue_cours_filieres`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vue_cours_filieres` AS SELECT
 1 AS `id_cours`,
  1 AS `code_cours`,
  1 AS `intitule_cours`,
  1 AS `nombre_heures`,
  1 AS `nombre_credits`,
  1 AS `niveau`,
  1 AS `semestre`,
  1 AS `nom_filiere`,
  1 AS `nom_departement` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vue_enseignants_depasse_charge`
--

DROP TABLE IF EXISTS `vue_enseignants_depasse_charge`;
/*!50001 DROP VIEW IF EXISTS `vue_enseignants_depasse_charge`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vue_enseignants_depasse_charge` AS SELECT
 1 AS `id_enseignant`,
  1 AS `enseignant`,
  1 AS `nom_departement`,
  1 AS `libelle_grade`,
  1 AS `charge_statutaire`,
  1 AS `libelle_annee`,
  1 AS `volume_total`,
  1 AS `heures_complementaires` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vue_statistiques_globales`
--

DROP TABLE IF EXISTS `vue_statistiques_globales`;
/*!50001 DROP VIEW IF EXISTS `vue_statistiques_globales`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vue_statistiques_globales` AS SELECT
 1 AS `total_enseignants`,
  1 AS `total_cours`,
  1 AS `total_ressources`,
  1 AS `total_activites`,
  1 AS `volume_horaire_valide` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vue_volume_par_enseignant`
--

DROP TABLE IF EXISTS `vue_volume_par_enseignant`;
