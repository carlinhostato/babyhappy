-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: babyhappy
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `sitter_id` int NOT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `status_reserva` varchar(20) NOT NULL DEFAULT 'pendente',
  `montante_total` decimal(6,2) DEFAULT '0.00',
  PRIMARY KEY (`booking_id`),
  KEY `parent_id` (`parent_id`),
  KEY `sitter_id` (`sitter_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`sitter_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,1,9,'2025-10-22 17:48:00','2025-10-23 20:51:00','paga',324.60),(15,1,11,'2026-05-18 12:00:00','2026-05-20 12:12:00','pendente',2169.00),(16,1,9,'2026-05-20 12:00:00','2026-05-20 12:12:00','aprovada',2.40),(17,1,9,'2026-06-12 11:00:00','2026-06-12 12:00:00','paga',12.00),(18,16,9,'2026-05-12 11:05:00','2026-05-12 13:00:00','recusada',23.00),(19,15,9,'2026-05-11 12:12:00','2026-05-11 16:00:00','paga',45.60),(20,15,9,'2025-12-06 12:00:00','2025-12-06 19:00:00','paga',84.00),(21,15,9,'2025-12-24 00:41:00','2025-12-31 00:41:00','pendente',2016.00),(22,15,9,'2025-12-24 00:41:00','2025-12-31 00:41:00','pendente',2016.00),(23,15,9,'2025-12-24 00:41:00','2025-12-31 00:41:00','pendente',2016.00),(24,15,9,'2025-12-12 22:51:00','2025-12-19 23:53:00','pendente',2028.40),(25,15,9,'2025-12-19 23:00:00','2025-12-23 01:01:00','pendente',888.20);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `conteudo` text NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `booking_id` (`booking_id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (59,NULL,15,9,'Olá Dona Sara Veja se recebeu o valor que lhe mandei do serviço pf','2025-11-23 11:45:19',1),(60,NULL,9,15,'Olá Senhor Carlos recebi sim..Bom fim de semana!','2025-11-23 11:47:59',1),(61,NULL,9,15,'oioioioioioi','2025-11-28 17:44:59',1),(62,NULL,9,15,'oioioioioioio','2025-12-02 13:18:42',1),(64,NULL,9,15,'ola meu amigo tudo bem?','2025-12-12 16:01:11',1),(65,NULL,15,9,'oi','2025-12-12 21:09:02',1),(67,NULL,15,9,'ola tudo bem ganhamos <3','2025-12-12 21:13:40',1);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `status_pagamento` enum('Pendente','Sucesso','Falha','COMPLETED') NOT NULL DEFAULT 'Pendente',
  `montante` decimal(10,2) NOT NULL,
  `type` enum('LOAD_BALANCE','BOOKING_PAYMENT','SITTER_CREDIT','WITHDRAWAL') NOT NULL,
  `data_pagamento` datetime DEFAULT CURRENT_TIMESTAMP,
  `referencia_gateway` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `referencia_gateway` (`referencia_gateway`),
  KEY `user_id` (`user_id`),
  KEY `nome_da_fk` (`booking_id`),
  CONSTRAINT `nome_da_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (25,1,NULL,NULL,'Sucesso',400.00,'LOAD_BALANCE','2025-11-09 17:29:12','WALLET_2FF2FDCAC8'),(27,1,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-11-10 11:31:09','WALLET_EEF1CA62BD'),(29,1,NULL,1,'Sucesso',324.60,'BOOKING_PAYMENT','2025-11-10 11:53:51','BOOK_FINAL_F3E175C4'),(30,1,NULL,NULL,'Sucesso',100000.00,'LOAD_BALANCE','2025-11-11 18:49:13','WALLET_942785E66A'),(31,1,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-11-12 14:15:48','WALLET_3DC87EC907'),(32,1,NULL,NULL,'Sucesso',100000.00,'LOAD_BALANCE','2025-11-12 15:14:31','WALLET_A8A93C52B7'),(33,1,NULL,NULL,'Sucesso',1000.00,'LOAD_BALANCE','2025-11-20 16:59:11','WALLET_84E8EFD270'),(34,1,NULL,17,'Sucesso',12.00,'BOOKING_PAYMENT','2025-11-20 21:10:39','BOOK_FINAL_AA1DC1A9'),(35,16,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-11-22 10:18:18','WALLET_D157EA7CB8'),(36,20,NULL,NULL,'Sucesso',1000.00,'LOAD_BALANCE','2025-11-22 10:33:10','WALLET_606537BB8C'),(37,15,NULL,NULL,'Sucesso',10000.00,'LOAD_BALANCE','2025-11-23 11:36:29','WALLET_E2E1AC9D39'),(38,15,NULL,19,'Sucesso',45.60,'BOOKING_PAYMENT','2025-11-23 11:39:02','BOOK_FINAL_D7409872'),(39,15,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-12-04 14:51:15','WALLET_FC34D8CBE7'),(40,15,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-12-05 13:20:09','WALLET_3C1FAFAC2D'),(41,15,NULL,20,'Sucesso',84.00,'BOOKING_PAYMENT','2025-12-05 13:24:36','BOOK_FINAL_A686BD78'),(42,15,NULL,NULL,'Sucesso',100.00,'LOAD_BALANCE','2025-12-11 20:10:09','WALLET_3C19F50D2E');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qualifications`
--

DROP TABLE IF EXISTS `qualifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `qualifications` (
  `qual_id` int NOT NULL AUTO_INCREMENT,
  `sitter_id` int NOT NULL,
  `tipo_documento` varchar(100) NOT NULL,
  `url_documento` varchar(255) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `data_submissao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`qual_id`),
  KEY `sitter_id` (`sitter_id`),
  CONSTRAINT `qualifications_ibfk_1` FOREIGN KEY (`sitter_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qualifications`
--

LOCK TABLES `qualifications` WRITE;
/*!40000 ALTER TABLE `qualifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `qualifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `ratings` (
  `rating_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL,
  `avaliador_id` int NOT NULL,
  `recetor_id` int NOT NULL,
  `rating` int NOT NULL,
  `comentario` text,
  `data_avaliacao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rating_id`),
  UNIQUE KEY `unique_rating_per_party` (`booking_id`,`avaliador_id`),
  KEY `avaliador_id` (`avaliador_id`),
  KEY `recetor_id` (`recetor_id`),
  CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`),
  CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`avaliador_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `ratings_ibfk_3` FOREIGN KEY (`recetor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `ratings_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ratings`
--

LOCK TABLES `ratings` WRITE;
/*!40000 ALTER TABLE `ratings` DISABLE KEYS */;
INSERT INTO `ratings` VALUES (1,NULL,1,9,5,'ola','2025-11-11 12:02:50'),(2,NULL,1,9,5,'ola','2025-11-11 12:04:39'),(3,NULL,1,9,5,'ola','2025-11-11 12:06:18'),(4,NULL,1,9,5,'OLA','2025-11-11 12:06:51'),(5,NULL,1,9,1,'foda','2025-11-11 21:20:12'),(6,NULL,1,9,1,'foda se consegui','2025-11-11 21:20:38'),(7,NULL,1,9,1,'ola','2025-11-11 21:24:10'),(8,NULL,1,9,5,'oxi','2025-11-11 21:26:00'),(10,NULL,1,9,1,'carlos é o maior','2025-11-11 21:54:27'),(11,NULL,1,9,1,'carlos é o maior','2025-11-11 21:54:44'),(12,NULL,1,9,1,'carlos é o maior','2025-11-11 21:58:22'),(13,NULL,1,9,1,'carlos é o maior','2025-11-11 22:23:03'),(14,NULL,1,9,5,'ola','2025-11-11 22:23:09'),(15,NULL,1,9,4,'ola','2025-11-11 22:23:17'),(17,NULL,1,9,4,'ola','2025-11-11 22:51:48'),(18,NULL,1,11,3,'ola','2025-11-12 13:05:42'),(19,NULL,1,9,5,'5 estrelas','2025-11-13 11:26:59'),(20,NULL,1,9,5,'oi','2025-11-20 17:27:38'),(21,NULL,16,11,3,'oioi','2025-11-22 10:17:21'),(22,NULL,15,11,5,'oi','2025-11-28 18:00:56'),(23,NULL,15,9,5,NULL,'2025-12-02 13:23:44');
/*!40000 ALTER TABLE `ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sitter_profiles`
--

DROP TABLE IF EXISTS `sitter_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `sitter_profiles` (
  `sitter_id` int NOT NULL,
  `descricao` text,
  `preco_hora` decimal(5,2) DEFAULT '0.00',
  `experiencia_anos` int DEFAULT '0',
  `media_rating` decimal(3,2) DEFAULT '0.00',
  `esta_online` tinyint(1) DEFAULT '0',
  `status_perfil` enum('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (`sitter_id`),
  CONSTRAINT `sitter_profiles_ibfk_1` FOREIGN KEY (`sitter_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sitter_profiles`
--

LOCK TABLES `sitter_profiles` WRITE;
/*!40000 ALTER TABLE `sitter_profiles` DISABLE KEYS */;
INSERT INTO `sitter_profiles` VALUES (9,'sou muito',12.00,1,0.00,0,'aprovado'),(11,'muito',45.00,56,0.00,0,'aprovado'),(12,'muitoooo',34.00,3,0.00,0,'aprovado'),(13,'muigtooo',23.00,4,0.00,0,'aprovado'),(14,'123',12.00,34,0.00,0,'aprovado');
/*!40000 ALTER TABLE `sitter_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `data_registo` datetime DEFAULT CURRENT_TIMESTAMP,
  `photo_url` varchar(255) DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `bio` text,
  `phone` varchar(20) DEFAULT NULL,
  `balance_bhc` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo na moeda BabyHappyCoin (BHC)',
  `disponibilidade` varchar(255) DEFAULT NULL,
  `proximidade` varchar(255) DEFAULT NULL,
  `experiencia` varchar(255) DEFAULT NULL,
  `localizacao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'parent','carlinhosantoniotato@gmail.com','$2y$10$/sy7o4ZArRaTR7r5UGF9DO9swfSNL2cYmnNJGQTpcBWpM0kDJ.zF.','Carlinhos T','2025-10-16 11:04:46','/public/uploads/profile_photos/user_1_1763658502.jpg','2025-11-20 20:44:59','','',272153.20,'','','',''),(3,'parent','saraaragao@gmail.com','$2y$10$JUTzWTJHVxexnJO2drUPgemAAkDPj4ImNWiPseb27kJ2rHrsXRyxW','Sara Aragão','2025-10-16 11:28:43',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL),(4,'babysitter','saracorola@gmail.com','$2y$10$Vlx7AlxNEEqHnBeYuf6E4u3eNchjWRwJmQ1SLnnyvXaxxRt8SRd.u','Sara Corola','2025-10-16 15:36:30',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL),(5,'babysitter','ratocato@gmail.com','$2y$10$aX8nJRTi6EGnbc1xHoE4BeawQ4BLAZ.GpZS5nUGqb6tiNAnwcU4vS','Rato cato','2025-10-16 15:51:21',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL),(7,'parent','dragao@gmail.com','$2y$10$COCWAXIfExTWQ4umwWfeG.jAUFV86eOvcQuz7iURJnR0Iol0a/yPS','dragao','2025-10-16 16:57:10',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL),(9,'babysitter','carlos@babyhappy.pt','$2y$10$quywvAM0V4auHoW2HLIwl.9vz0FHsQvUL7fhWbFToBF0XBRfdTpVu','Carlos Tato','2025-10-19 17:19:05','/public/uploads/profile_photos/user_9_1765574108.jpg','2025-12-12 17:06:08','','',2596.80,'Disponível','','Avançada','Espinho'),(10,'parent','ca@gmail.com','$2y$10$PDD4E0CMvpCNSMRNyHEzKeW8hBrpo4eO0CYW1y7l5dhwxnbQBBxJy','ca','2025-10-19 19:16:52',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL),(11,'babysitter','carina@gmail.com','$2y$10$T7Bo58FZ7bQ/ApHURuNjru/4AkH9wU17lR.4M0CkWYpxTaH.5CC.6','Ana Carina','2025-10-20 11:47:21','/public/assets/images/profiles/sitter_11_1760957356.png',NULL,NULL,NULL,0.00,NULL,NULL,NULL,'Espinho'),(12,'babysitter','mul@gmail.com','$2y$10$TGj/AiqSsr5ujYauVgsSFeBvoB3wwYn6lieNpotFe0x4ruJPcdDhm','Mul','2025-10-20 11:51:20','/public/assets/images/profiles/sitter_12_1760957506.jpg',NULL,NULL,NULL,0.00,NULL,NULL,NULL,'Lisboa'),(13,'babysitter','jo@gmail.com','$2y$10$CR5txyLXiwh.NpYhlbHMyueSy9WTZtxQhNjflsZTx1VK9liNymF4K','jo','2025-10-20 11:52:13','/public/assets/images/profiles/sitter_13_1760957560.jpg',NULL,NULL,NULL,0.00,NULL,NULL,NULL,'Ovar'),(14,'babysitter','hu@gmail.com','$2y$10$HEFk7zhemECgUsz0fhB7HOGpzkfFm7zSLb8/mMjHmoBNGQIqH9fYO','hu','2025-10-20 11:53:11','/public/assets/images/profiles/sitter_14_1760957612.jpg',NULL,NULL,NULL,0.00,NULL,NULL,NULL,'Porto'),(15,'parent','carlos@gmail.com','$2y$10$mHobkWBGarYLB.TRfmNvs.t6iN2rUpyt7Ty30THM7ffAHs9OLapnu','Carlos Tato','2025-11-21 16:55:20','/public/uploads/profile_photos/user_15_1765537985.jpeg','2025-12-12 21:13:42','','932219503',0.00,'','','','Espinho'),(16,'parent','carlinhos@gmail.com','$2y$10$Md.lr5ABib4AYyOQ5r1atOyQBwTTfAE0OiSEWRIBXFmV5yGQ1ykbS','carlinhos','2025-11-22 10:13:38','/public/uploads/profile_photos/user_16_1763806562.png',NULL,'','',0.00,'','','',''),(17,'babysitter','porra@gmail.com','$2y$10$C.MTVPD1wlWMFSnfkEnceO2.FDmRcCiNrftrDaew9kRiqVP4ifIEq','porra','2025-11-22 10:20:25',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,''),(18,'babysitter','rio@gmail.com','$2y$10$0CQZaU/75stjsp/ahQKuseaOmVoRieLWLx4pIVS3Sz5Y9SEx41xJq','rio','2025-11-22 10:21:28','/public/uploads/profile_photos/user_18_1763807039.jpeg',NULL,'','',0.00,'Disponível','','Especialista','Aveiro'),(19,'babysitter','kuala@gmail.com','$2y$10$TZfAd8ZS3mL.TAYzKYEwgOqqxCCyAZUPHl4vU39teHDSViAQfDiBK','kuala','2025-11-22 10:28:34',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,''),(20,'parent','falcao@gmail.com','$2y$10$exeFbICMPAgGtR.6KDfWkOvCCA77Vod2.oePg4LZQ5WTIm.x/U48e','falcao','2025-11-22 10:30:35','/public/uploads/profile_photos/user_20_1763807575.jpg',NULL,'','',0.00,'','','',''),(21,'parent','lucia@gmail.com','$2y$10$ijHOgtH7NE4fVd5PcBxTW.w65Fyo.mK6Cdbo4wpQDVYb8CKO/MsK2','lucia','2025-12-11 13:55:58',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,''),(23,'parent','rockstroice@gmail.com','$2y$10$4EhzF/IpoPWa7Rxd0rsMNuuMjKsMUslx11t0HmzQ0tzJNz.dMTTEO','rockstroice','2025-12-11 14:00:02',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,''),(24,'babysitter','dulce@gmail.com','$2y$10$piGquIyX9iF5/xGK2RcOi.K00hfLJZK1/AkiBslNNXV08B.AFSs9O','dulce','2025-12-11 14:06:19',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,'');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `wallets` (
  `wallet_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wallet_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
INSERT INTO `wallets` VALUES (17,9,53.00,'2025-11-10 11:53:51','2025-12-12 16:47:17');
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawals`
--

DROP TABLE IF EXISTS `withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `withdrawals` (
  `withdrawal_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `montante` decimal(10,2) NOT NULL,
  `method` enum('DEBIT_CARD','MBWAY') NOT NULL,
  `details_value` varchar(255) DEFAULT NULL,
  `status` enum('PENDENTE','PROCESSADO','CANCELADO') DEFAULT 'PENDENTE',
  `data_solicitacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` datetime DEFAULT NULL,
  PRIMARY KEY (`withdrawal_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawals`
--

LOCK TABLES `withdrawals` WRITE;
/*!40000 ALTER TABLE `withdrawals` DISABLE KEYS */;
INSERT INTO `withdrawals` VALUES (1,9,100.00,'MBWAY',NULL,'PENDENTE','2025-11-10 12:00:06',NULL),(2,9,50.00,'MBWAY','919685519','PENDENTE','2025-11-10 12:05:32',NULL),(3,9,2.00,'MBWAY','919685519','PENDENTE','2025-11-11 10:51:05',NULL),(4,9,2.00,'MBWAY','919685519','PENDENTE','2025-11-11 10:51:29',NULL),(5,9,10.00,'MBWAY','919685519','PENDENTE','2025-11-13 11:24:43',NULL),(6,9,100.00,'MBWAY','919685519','PENDENTE','2025-11-20 21:09:10',NULL),(7,9,72.60,'MBWAY','919685519','PENDENTE','2025-11-22 10:19:12',NULL),(8,9,5.00,'MBWAY','932219503','PENDENTE','2025-11-28 17:44:48',NULL),(9,9,40.60,'MBWAY','932219503','PENDENTE','2025-12-02 13:19:19',NULL),(10,9,5.00,'MBWAY','932219504','PENDENTE','2025-12-05 13:26:06',NULL);
/*!40000 ALTER TABLE `withdrawals` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-14 14:46:16
