-- ============================================================
--  Commission system migration (v2 — tech owes platform)
--  Run this once against your khidmadz1 database
-- ============================================================

-- 1. Commission table
--    statut = 'due'    → generated, tech hasn't paid yet
--    statut = 'payée'  → tech paid Khidma DZ ✓
CREATE TABLE IF NOT EXISTS `commission` (
  `id_commission`      INT(20)       NOT NULL AUTO_INCREMENT,
  `id_service`         INT(11)       NOT NULL,
  `id_paiment`         INT(20)       NOT NULL,
  `id_technicien`      INT(11)       NOT NULL,
  `montant_service`    FLOAT         NOT NULL DEFAULT 0  COMMENT 'Montant HT encaissé par le tech',
  `taux_commission`    DECIMAL(5,2)  NOT NULL DEFAULT 8.00,
  `montant_commission` FLOAT         NOT NULL DEFAULT 0  COMMENT 'Ce que le tech doit à Khidma DZ',
  `statut`             ENUM('due','payée') NOT NULL DEFAULT 'due',
  `date_commission`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_paiement`      DATETIME      DEFAULT NULL COMMENT 'Quand le tech a payé la plateforme',
  PRIMARY KEY (`id_commission`),
  UNIQUE KEY `uniq_paiment` (`id_paiment`),
  KEY `idx_technicien` (`id_technicien`),
  KEY `idx_service`    (`id_service`),
  KEY `idx_statut`     (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. If upgrading from v1 (had collectée/versée), migrate old statuses
--    Safe to run even if column doesn't exist yet (ALTER IGNORE)
ALTER TABLE `commission`
  MODIFY COLUMN `statut` ENUM('due','payée','collectée','versée') NOT NULL DEFAULT 'due';

UPDATE `commission` SET statut='due'   WHERE statut='collectée';
UPDATE `commission` SET statut='payée' WHERE statut='versée';

ALTER TABLE `commission`
  MODIFY COLUMN `statut` ENUM('due','payée') NOT NULL DEFAULT 'due';

-- 3. Back-fill for existing paid payments
INSERT IGNORE INTO `commission`
  (`id_service`,`id_paiment`,`id_technicien`,`montant_service`,`taux_commission`,`montant_commission`,`statut`,`date_commission`)
SELECT
  p.id_service,
  p.id_paiment,
  s.id_technicien,
  p.montant,
  8.00,
  ROUND(p.montant * 0.08, 2),
  'due',
  COALESCE(p.date_paiment, NOW())
FROM paiment p
JOIN service s ON s.id_service = p.id_service
WHERE p.statut_paiment = 'payé'
  AND s.id_technicien IS NOT NULL;