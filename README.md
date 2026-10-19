<div align="center">

# Khidma-DZ 🇩🇿

**Plateforme qui met en relation des clients avec des techniciens locaux de confiance (services à domicile en Algérie).**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![PWA](https://img.shields.io/badge/PWA-ready-5A0FC8)

[Présentation](#-présentation) · [Fonctionnalités](#-fonctionnalités) · [Démo](#-démo-vidéo) · [Installation](#-installation) · [Équipe](#-équipe)

</div>

---

## 📌 Présentation

**Khidma DZ** (« خدمة » = service) permet à un client de trouver rapidement un technicien qualifié près de chez lui (plomberie, électricité, climatisation, etc.), de réserver, de communiquer et de payer en ligne, avec un suivi complet par un administrateur.

Projet d'équipe réalisé en **3ᵉ année de Licence Informatique** (Faculté de Mathématiques, Informatique et Télécommunications, **Université Dr Moulay Tahar – Saïda**), au sein de l'**Incubateur d'affaires de l'Université de Saïda**, année universitaire 2025/2026.

## ✨ Fonctionnalités

| Rôle | Ce qu'il peut faire |
|------|---------------------|
| **Client** | Rechercher un technicien, réserver un service, discuter par messagerie, payer, évaluer, signaler un litige, consulter ses factures |
| **Technicien** | Créer son profil (CV / diplômes), gérer ses demandes, partager sa position, suivre ses gains et commissions |
| **Administrateur** | Valider les comptes et les justificatifs, gérer litiges et signalements, suivre commissions et TVA, tableau de bord statistique |

Autres points : interface **français / arabe** (RTL), géolocalisation (Leaflet), graphiques (Chart.js), messagerie en quasi temps réel, génération de factures, **PWA** installable avec mode hors-ligne.

## 🎬 Démo vidéo

- [▶️ Démo vidéo (Google Drive)](https://drive.google.com/file/d/1PsnRJvA4b1oaM5UtxTjeNpvX-L0FJST1/view?usp=drivesdk)

## 🛠️ Technologies

- **Frontend** : HTML5, CSS3, JavaScript (vanilla), Leaflet.js, Chart.js, PWA (service worker, manifest)
- **Backend** : PHP 8.2 (PDO), PHPMailer
- **Base de données** : MySQL / MariaDB

## 🚀 Installation

**Prérequis** : XAMPP ou WAMP (PHP ≥ 8.0, MySQL/MariaDB).

1. Copiez le projet dans `htdocs/khidma-dz`.
2. Créez les dossiers `uploads/cv/` et `factures/` s'ils n'existent pas.
3. Créez une base `khidmadz1` et importez `database/khidmadz.sql` (les scripts de `database/migrations/` sont optionnels).
4. Copiez `includes/config.example.php` vers `includes/config.local.php` et renseignez vos valeurs (base de données, SMTP Gmail avec un *mot de passe d'application*).
5. Ouvrez `http://localhost/khidma-dz`.

### Comptes de démonstration
Toutes les données sont fictives. Les comptes de la base d'exemple ont le mot de passe `Demo@12345` (ex. `admin@khidma.dz`).
**À modifier avant tout déploiement réel.**

## 📁 Structure

```
actions/    traitements des formulaires (login, paiement, litiges…)
api/        endpoints JSON (messagerie, polling)
assets/     CSS, JavaScript, images
database/   schéma SQL et migrations
includes/   connexion BD, configuration, mailer, langues
```

## 🔒 Sécurité

Requêtes préparées (PDO), mots de passe hachés (bcrypt), sessions, exécution de scripts interdite dans `uploads/`. Les identifiants ne sont jamais versionnés : ils se trouvent dans `includes/config.local.php` (ignoré par git).

## 👥 Équipe

Projet réalisé en équipe de 3 étudiants. **Les trois membres ont travaillé ensemble sur l'ensemble du projet** : conception, développement backend et développement frontend.

| Membre | Contribution |
|--------|--------------|
| **KHELIFA Saliha Marwa** ([@marwakhelifa](https://github.com/marwakhelifa)) | Conception · Backend · Frontend |
| RACHEDI Karima Douaa | Conception · Backend · Frontend |
| KHERSSI Mehdi Mohamed Djeloul | Conception · Backend · Frontend |

**Encadrant** : MEKRI Miloud Aboubekr Seddik — Faculté de Mathématiques, Informatique et Télécommunications.

### Contribution de Marwa
- Participation à la conception de la plateforme et au développement backend, en collaboration avec l'équipe
- Développement des interfaces utilisateur (pages client, technicien et administrateur)
- Mise en page responsive et feuille de style (`assets/css/style.css`)
- Interactions JavaScript (`assets/js/`) : navigation, messagerie, suivi en temps réel
- Support bilingue français / arabe et mode PWA

## 📄 Licence

Projet académique réalisé à l'Université de Saïda (2025/2026).
