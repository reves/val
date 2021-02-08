/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;

CREATE TABLE IF NOT EXISTS `val_auth_sessions` (
  `Id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `AccountId` bigint(20) unsigned NOT NULL,
  `DeviceType` varchar(63) DEFAULT NULL,
  `DevicePlatform` varchar(63) DEFAULT NULL,
  `DeviceBrowser` varchar(63) DEFAULT NULL,
  `SignedInAt` varchar(63) DEFAULT NULL,
  `LastSeenAt` varchar(63) DEFAULT NULL,
  `SignedInIPAddress` varbinary(16) DEFAULT NULL,
  `LastSeenIPAddress` varbinary(16) DEFAULT NULL,
  PRIMARY KEY (`Id`),
  KEY `AccountId` (`AccountId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;