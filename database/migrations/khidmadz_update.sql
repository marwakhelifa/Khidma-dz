-- ============================================================
-- khidmadz_update.sql — Run ONCE after khidmadz1.sql
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE `disponibilite`
  MODIFY `id_disponibilite` int(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `message`
  MODIFY `id_message` int(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `utilisateur`
    ADD COLUMN IF NOT EXISTS `langue` varchar(5) NOT NULL DEFAULT 'fr' AFTER `statut_compte`;

CREATE TABLE IF NOT EXISTS `notification` (
  `id_notif` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(20) DEFAULT NULL,
  `titre` varchar(200) DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `lien` varchar(300) DEFAULT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_notif` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notif`),
  KEY `id_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `log_action` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(20) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prestation_catalogue` (
  `id_catalogue` int(11) NOT NULL AUTO_INCREMENT,
  `specialite` varchar(100) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `nom_ar` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_catalogue`),
  KEY `specialite` (`specialite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `prestation_catalogue`
    ADD COLUMN IF NOT EXISTS `nom_ar` varchar(200) NOT NULL DEFAULT '' AFTER `nom`;

CREATE TABLE IF NOT EXISTS `prestation` (
  `id_prestation` int(11) NOT NULL AUTO_INCREMENT,
  `id_technicien` int(20) DEFAULT NULL,
  `id_catalogue` int(11) DEFAULT NULL,
  `nom` varchar(200) NOT NULL,
  `prix_unitaire` float DEFAULT NULL,
  `unite` varchar(50) DEFAULT 'intervention',
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_prestation`),
  KEY `id_technicien` (`id_technicien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `service_prestation` (
  `id_sp` int(11) NOT NULL AUTO_INCREMENT,
  `id_service` int(20) DEFAULT NULL,
  `id_prestation` int(11) DEFAULT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sp`),
  KEY `id_service` (`id_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reset_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(20) DEFAULT NULL,
  `token` varchar(200) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `technicien`
    ADD COLUMN IF NOT EXISTS `latitude` decimal(10,7) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `longitude` decimal(10,7) DEFAULT NULL;

-- Catalogue data (FR + AR)
INSERT IGNORE INTO `prestation_catalogue` (`id_catalogue`,`specialite`,`nom`,`nom_ar`) VALUES
(1,'Plomberie','Réparation fuite d\'eau','إصلاح تسرب المياه'),
(2,'Plomberie','Installation robinetterie','تركيب الحنفيات'),
(3,'Plomberie','Débouchage canalisation','إزالة انسداد المجاري'),
(4,'Plomberie','Remplacement chauffe-eau','استبدال سخان المياه'),
(5,'Plomberie','Installation chauffe-eau','تركيب سخان المياه'),
(6,'Plomberie','Réparation WC / toilettes','إصلاح المرحاض'),
(10,'Électricité','Installation prise électrique','تركيب مقبس كهربائي'),
(11,'Électricité','Remplacement disjoncteur','استبدال قاطع التيار'),
(12,'Électricité','Installation lustre / plafonnier','تركيب ثريا أو نجفة'),
(13,'Électricité','Mise en conformité tableau','تحديث لوحة الكهرباء'),
(14,'Électricité','Dépannage panne électrique','إصلاح عطل كهربائي'),
(20,'Menuiserie','Réparation porte / fenêtre','إصلاح باب أو نافذة'),
(21,'Menuiserie','Installation placard','تركيب خزانة'),
(22,'Menuiserie','Fabrication meuble sur mesure','صنع أثاث مخصص'),
(23,'Menuiserie','Pose parquet','تركيب باركيه'),
(30,'Peinture','Peinture intérieure (m²)','دهان داخلي (م²)'),
(31,'Peinture','Peinture extérieure (m²)','دهان خارجي (م²)'),
(32,'Peinture','Enduit / Ragréage','تسوية وترميم'),
(40,'Climatisation','Installation climatiseur split','تركيب مكيف سبليت'),
(41,'Climatisation','Entretien / nettoyage clim','صيانة وتنظيف المكيف'),
(42,'Climatisation','Recharge gaz climatiseur','إعادة شحن غاز المكيف'),
(43,'Climatisation','Réparation panne climatiseur','إصلاح عطل المكيف'),
(50,'Électroménager','Réparation machine à laver','إصلاح الغسالة'),
(51,'Électroménager','Réparation réfrigérateur','إصلاح الثلاجة'),
(52,'Électroménager','Réparation four / cuisinière','إصلاح الفرن أو الطباخة'),
(53,'Électroménager','Réparation lave-vaisselle','إصلاح غسالة الأطباق'),
(60,'Carrelage','Pose carrelage sol (m²)','تركيب بلاط الأرضية (م²)'),
(61,'Carrelage','Pose carrelage mural (m²)','تركيب بلاط الجدار (م²)'),
(62,'Carrelage','Réparation carrelage','إصلاح وتعديل البلاط'),
(70,'Maçonnerie','Réparation fissure / lézarde','إصلاح شقوق الجدران'),
(71,'Maçonnerie','Construction muret','بناء جدار صغير'),
(72,'Maçonnerie','Démolition cloison','هدم حاجز'),
(80,'Serrurerie','Remplacement serrure','استبدال قفل'),
(81,'Serrurerie','Ouverture porte claquée','فتح باب مقفل'),
(82,'Serrurerie','Installation serrure 3 points','تركيب قفل ثلاثي'),
(90,'Vitrerie','Remplacement vitre cassée','استبدال زجاج مكسور'),
(91,'Vitrerie','Installation fenêtre aluminium','تركيب نافذة ألومنيوم'),
(100,'Toiture','Réparation toiture / terrasse','إصلاح السطح والتراس'),
(101,'Toiture','Étanchéité terrasse','عزل السطح'),
(110,'Jardinage','Taille arbres / haies','تقليم الأشجار والأسيجة'),
(111,'Jardinage','Entretien pelouse','صيانة العشب'),
(120,'Informatique','Réparation PC / Laptop','إصلاح كمبيوتر'),
(121,'Informatique','Installation logiciels / Windows','تثبيت البرامج وويندوز'),
(122,'Informatique','Récupération données','استرجاع البيانات'),
(130,'Télévision','Installation parabole','تركيب طبق ستالايت'),
(131,'Télévision','Réglage décodeur satellite','ضبط فضائي وريسيفر'),
(140,'Déménagement','Déménagement local (camion)','نقل محلي (شاحنة)'),
(141,'Déménagement','Transport de meubles','نقل أثاث'),
(150,'Nettoyage','Nettoyage appartement','تنظيف شقة'),
(151,'Nettoyage','Nettoyage post-travaux','تنظيف ما بعد البناء'),
(160,'Électronique','Réparation smartphone','إصلاح هاتف ذكي'),
(161,'Électronique','Réparation tablette','إصلاح تابلت'),
(170,'Marbre','Pose marbre / granit','تركيب رخام وغرانيت'),
(171,'Marbre','Polissage marbre','تلميع الرخام'),
(180,'Soudure','Soudure métallique','لحام معدني'),
(181,'Soudure','Fabrication grille / portail','صنع شبكية أو بوابة'),
(190,'Autre','Autre prestation','خدمة أخرى');
