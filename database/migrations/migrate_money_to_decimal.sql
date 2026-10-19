-- Migration: convert monetary FLOAT columns to DECIMAL(10,2)
-- BACKUP your database before running this script.
-- Run on a downtime window.

START TRANSACTION;

-- paiment.montant
ALTER TABLE paiment MODIFY montant DECIMAL(10,2) DEFAULT 0;

-- facture.montant_total
ALTER TABLE facture MODIFY montant_total DECIMAL(10,2) DEFAULT 0;

-- service prix fields
ALTER TABLE service MODIFY prix_estime DECIMAL(10,2) DEFAULT 0;
ALTER TABLE service MODIFY prix_final DECIMAL(10,2) DEFAULT 0;

-- prestation.prix_unitaire
ALTER TABLE prestation MODIFY prix_unitaire DECIMAL(10,2) DEFAULT 0;

-- technicien.tarif
ALTER TABLE technicien MODIFY tarif DECIMAL(10,2) DEFAULT 0;

-- commission monetary fields
ALTER TABLE commission MODIFY montant_service DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE commission MODIFY montant_commission DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE commission MODIFY taux_commission DECIMAL(5,2) NOT NULL DEFAULT 10.00;

COMMIT;

-- Note: review other FLOAT columns you may want to convert. Test on a copy first.
