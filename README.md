# Khidma-DZ 🇩🇿

Plateforme web qui met en relation des clients et des techniciens de services à domicile en Algérie.
Projet académique (PFE).

## Fonctionnalités
- Inscription / connexion (client, technicien, admin)
- Profils techniciens et catalogue de services
- Réservations, messagerie (polling), géolocalisation GPS
- Vérification CV / diplômes
- Paiement, commissions, TVA, génération de factures
- Tableau de bord administrateur
- Interface français / arabe, PWA

## Technologies
PHP 8.2 · MySQL/MariaDB · HTML/CSS/JS · Leaflet.js · Chart.js · PHPMailer

## Installation (XAMPP / WAMP)
1. Copiez le dossier dans `htdocs/khidma-dz`.
2. Créez une base `khidmadz1` et importez `database/khidmadz.sql` (les scripts de `database/migrations/` sont optionnels).
3. Copiez `includes/config.example.php` vers `includes/config.local.php` et renseignez vos valeurs (base de données, SMTP Gmail avec mot de passe d'application).
4. Ouvrez `http://localhost/khidma-dz`.

## Comptes de démonstration
Toutes les données sont fictives. Tous les comptes de la base d'exemple ont le mot de passe `Demo@12345`
(par ex. `admin@khidma.dz`). **Changez-le avant tout déploiement réel.**

## Sécurité
Requêtes préparées (PDO), mots de passe hachés (bcrypt), sessions.
Les identifiants ne sont jamais versionnés : ils se trouvent dans `includes/config.local.php` (ignoré par git).
