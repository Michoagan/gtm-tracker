# GTM Tracker 🎯

Application web interne moderne, légère et complète permettant à une équipe de **5 personnes** de piloter et tracer toutes les actions de leur stratégie **Go-To-Market (GTM)**.

---

## 🗄️ Base de Données

- **Primaire (En ligne)** : **PostgreSQL (Aiven Cloud)**
  - Hôte : `pg-1e5f9d3c-onspecialtech-a4ba.f.aivencloud.com:20822`
  - Base : `defaultdb` (schéma isolé `gtm`)
  - SSL : Mode `require`
- **Secours (Local)** : SQLite (`database/gtm.sqlite`) avec bascule automatique si la connexion réseau est indisponible.

---

## 🚀 Démarrage Rapide

### 1. Prérequis
- **PHP 8.0+** avec extensions `pdo_pgsql` et `pdo_sqlite` activées.

### 2. Lancement du serveur local
Double-cliquez sur **`start.bat`** ou exécutez la commande suivante dans ce dossier :
```bash
php -S localhost:8000
```
Puis ouvrez votre navigateur sur : **[http://localhost:8000](http://localhost:8000)**

---

## 🔑 Identifiants de Connexion

### Compte Administrateur :
- **Email** : `admin@gtmtracker.com`
- **Mot de passe** : `Admin@2024!`
- *(Accès complet : gestion des utilisateurs, canaux, stratégies, bilans & exports)*

### Comptes Membres de l'Équipe :
| Membre | Email | Mot de passe | Rôle |
|---|---|---|---|
| **Sarah Conner** | `sarah@gtmtracker.com` | `Password@123` | Collaborateur |
| **Thomas Dubois** | `thomas@gtmtracker.com` | `Password@123` | Collaborateur |
| **Leila Benali** | `leila@gtmtracker.com` | `Password@123` | Collaborateur |
| **Alexandre Mercier** | `alexandre@gtmtracker.com` | `Password@123` | Collaborateur |

---

## 📌 Réponse aux Besoins Stratégiques GTM

| Question Clé | Fonctionnalité dans GTM Tracker |
|---|---|
| **Qui a fait quoi ?** | Historique complet des actions & conversations par membre |
| **Quand ?** | Horodatage précis, filtres par période (7j, 30j, trimestre, sur-mesure) |
| **Quelle stratégie ?** | Référentiel des stratégies GTM (Outbound, Inbound, Partenariats, etc.) |
| **Quel canal ?** | Référentiel des canaux (LinkedIn, Cold Email, WhatsApp, Événements...) |
| **Quelle action ?** | Fiche action détaillée (titre, description, priorité, statut, pièces jointes) |
| **Quel résultat ?** | Indicateur de résultat (Succès, Partiel, Échec, En attente) + commentaires |
| **Avec qui ?** | Fiches Prospects dédiées avec entreprise, coordonnées et statut |
| **Quelle conversation ?** | Log des échanges : messages envoyés, réponses reçues, captures/justificatifs |
| **Prochaine action ?** | Suivi du "Next step" avec alertes et rappels de relance |
| **Quels canaux/stratégies performent ?** | Graphiques et métriques de conversion et de volume par canal / stratégie |
| **Bilan global ?** | Module de reporting analytique complet avec export PDF imprimable |

---

## 🛠️ Re-migration ou Réinitialisation

Pour re-synchroniser les données locales vers la base PostgreSQL Aiven :
```bash
php migrate.php
```
