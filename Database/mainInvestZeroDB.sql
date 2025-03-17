-- MySQL Dump for InvestZero Database (Updated)
-- Server version 8.0.41

-- ------------------------------------------------------
-- Create Database if Not Exists
-- ------------------------------------------------------
CREATE DATABASE IF NOT EXISTS investzero;
USE investzero;

-- Disable Foreign Key Checks to Avoid Constraint Errors
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------
-- Table structure for table `Users`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  `userID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(225) NOT NULL,
  `email` varchar(225) NOT NULL,
  `username` varchar(225) NOT NULL,
  `password` varchar(225) NOT NULL,
  `created_at` bigint NOT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



DROP TABLE IF EXISTS `Sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Sessions` (
  `session_id` varchar(225) NOT NULL,
  `user_id` int NOT NULL,
  `expires_at` bigint NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `Sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------
-- Table structure for table `Accounts`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `Accounts`;
CREATE TABLE `Accounts` (
  `account_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `buying_power` decimal(15,2) NOT NULL DEFAULT '5000.00',
  `total_balance` decimal(15,2) NOT NULL DEFAULT '5000.00',
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `unique_user_account` (`user_id`),
  CONSTRAINT `Accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------
-- Table structure for table `Stocks`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `Stocks`;
CREATE TABLE `Stocks` (
  `ticker` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `marketCap` decimal(20,2) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `exchange` varchar(50) DEFAULT NULL,
  'description' varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ticker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------
-- Table structure for table `Portfolios`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `Portfolios`;
CREATE TABLE `Portfolios` (
  `account_id` int NOT NULL,
  `ticker` varchar(10) NOT NULL,
  `quantity` int NOT NULL,
  `average_price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`account_id`, `ticker`),
  CONSTRAINT `Portfolios_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `Accounts` (`account_id`) ON DELETE CASCADE,
  CONSTRAINT `Portfolios_ibfk_2` FOREIGN KEY (`ticker`) REFERENCES `Stocks` (`ticker`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------
-- Table structure for table `PriceHistory`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `PriceHistory`;
CREATE TABLE `PriceHistory` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `ticker` varchar(10) NOT NULL,
  `timestamp` bigint NOT NULL,
  `open` decimal(15,2) NOT NULL,
  `high` decimal(15,2) NOT NULL,
  `low` decimal(15,2) NOT NULL,
  `close` decimal(15,2) NOT NULL,
  `volume` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticker_timestamp` (`ticker`,`timestamp`),
  KEY `idx_ticker_latest` (`ticker`,`timestamp` DESC),
  CONSTRAINT `PriceHistory_ibfk_1` FOREIGN KEY (`ticker`) REFERENCES `Stocks` (`ticker`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------
-- Table structure for table `Transactions`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `Transactions`;
CREATE TABLE `Transactions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `ticker` varchar(10) NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `transaction_type` enum('BUY','SELL') NOT NULL,
  `timestamp` bigint NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `Transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `Accounts` (`account_id`) ON DELETE CASCADE,
  CONSTRAINT `Transactions_ibfk_2` FOREIGN KEY (`ticker`) REFERENCES `Stocks` (`ticker`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Enable Foreign Key Checks After Table Creation
SET FOREIGN_KEY_CHECKS = 1;