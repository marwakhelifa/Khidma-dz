-- Create TVA table used by application (matches runtime CREATE in handle_paiment.php)
CREATE TABLE IF NOT EXISTS `tva` (
  `id_tva` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_service` BIGINT UNSIGNED DEFAULT NULL,
  `id_paiment` BIGINT UNSIGNED DEFAULT NULL,
  `id_technicien` BIGINT UNSIGNED DEFAULT NULL,
  `montant_service` DOUBLE DEFAULT 0,
  `taux_tva` DOUBLE DEFAULT 0,
  `montant_tva` DOUBLE DEFAULT 0,
  `statut` VARCHAR(32) DEFAULT 'due',
  `date_tva` DATETIME DEFAULT NULL,
  `date_paiement` DATETIME DEFAULT NULL,
  UNIQUE KEY `unique_service_tva` (`id_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
