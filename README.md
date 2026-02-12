# 🏋️ Actual Sport - Plateforme de Réservation de Sessions Sportives

Plateforme web moderne de gestion et réservation de sessions sportives avec paiement en ligne sécurisé via Stripe.

## 📋 Table des matières

- [Description](#description)
- [Fonctionnalités](#fonctionnalités)
- [Technologies utilisées](#technologies-utilisées)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Structure du projet](#structure-du-projet)
- [API et Routes](#api-et-routes)
- [Paiements Stripe](#paiements-stripe)
- [Docker](#docker)
- [Commandes utiles](#commandes-utiles)

## 🎯 Description

Actual Sport est une application web complète permettant aux utilisateurs de :
- Consulter les cours et sessions sportives disponibles
- Réserver des sessions individuelles avec paiement en ligne
- Gérer leurs réservations (consultation, annulation avec remboursement)
- Bénéficier de remboursements automatiques en cas d'annulation (min. 24h avant)

L'application offre également une interface d'administration pour gérer les cours, sessions et utilisateurs.

## ✨ Fonctionnalités

### 👥 Pour les utilisateurs

- **Authentification sécurisée** (JWT)
  - Inscription avec email et mot de passe
  - Connexion avec session persistante
  - Gestion de profil

- **Consultation des cours**
  - Liste des cours actifs avec descriptions
  - Détails : prix, durée, disponibilité
  - Filtrage par type de cours

- **Réservation de sessions**
  - Affichage des sessions disponibles
  - Visualisation en temps réel des places restantes
  - Paiement sécurisé via Stripe Checkout
  - Confirmation instantanée

- **Gestion des réservations**
  - Dashboard personnel avec historique complet
  - Statut des réservations (Confirmé, Annulé)
  - Annulation possible jusqu'à 24h avant la session
  - Remboursement automatique via Stripe (5-10 jours ouvrés)
  - Réattribution automatique des places libérées

- **Historique des paiements**
  - Consultation des transactions effectuées
  - Détails des montants et dates
  - Lien avec les sessions réservées

### 🔐 Pour les administrateurs

- **Gestion des cours**
  - Création, modification, suppression de cours
  - Activation/désactivation
  - Définition des prix et durées

- **Gestion des sessions**
  - Création de sessions pour chaque cours
  - Définition des horaires et capacités
  - Suivi des places disponibles
  - Gestion des statuts

- **Statistiques**
  - Nombre total de cours
  - Nombre total de sessions
  - Nombre d'utilisateurs actifs

## 🛠️ Technologies utilisées

### Backend
- **Symfony 6.4** - Framework PHP
- **PHP 8.2** - Langage serveur
- **Doctrine ORM** - Gestion base de données
- **LexikJWTAuthenticationBundle** - Authentification JWT
- **Stripe PHP SDK** - Intégration paiements

### Frontend
- **Twig** - Moteur de templates
- **CSS3** - Styles responsive personnalisés
- **Helvetica Neue** - Police moderne

### Base de données
- **MySQL 8.0** - Base de données principale

### Infrastructure
- **Docker & Docker Compose** - Conteneurisation
- **Nginx Alpine** - Serveur web
- **PHP-FPM 8.2** - Processeur PHP

### Paiement
- **Stripe Checkout** - Pages de paiement sécurisées
- **Stripe API** - Gestion des remboursements

## 📦 Prérequis

- Docker Desktop (Windows/Mac) ou Docker + Docker Compose (Linux)
- Git
- Compte Stripe (clés de test pour développement)
- WSL2 (pour Windows)

## 🚀 Installation

### 1. Cloner le repository

```bash
git clone <url-du-repo>
cd projet_test
```

### 2. Configuration de l'environnement

Copier le fichier `.env` et configurer les variables :

```bash
cp .env .env.local
```

Variables importantes à configurer dans `.env` :

```env
# Base de données
DATABASE_URL="mysql://root:root@mysql:3306/actual_db?serverVersion=8.0"

# Stripe (remplacer par vos clés)
STRIPE_PUBLIC_KEY=pk_test_votre_cle_publique
STRIPE_SECRET_KEY=sk_test_votre_cle_secrete

# JWT (générer avec php bin/console lexik:jwt:generate-keypair)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase
```

### 3. Démarrer Docker

```bash
docker-compose up -d
```

### 4. Installer les dépendances

```bash
docker exec -it projet_actual_php composer install
```

### 5. Générer les clés JWT

```bash
docker exec -it projet_actual_php php bin/console lexik:jwt:generate-keypair
```

### 6. Créer la base de données et les tables

```bash
docker exec -it projet_actual_php php bin/console doctrine:database:create
docker exec -it projet_actual_php php bin/console doctrine:migrations:migrate
```

### 7. Créer un compte administrateur

```bash
docker exec -it projet_actual_php php bin/console app:create-admin
```

Suivre les instructions pour créer le compte admin.

### 8. (Optionnel) Charger des données de test

```bash
docker exec -it projet_actual_php php bin/console doctrine:fixtures:load
```

## ⚙️ Configuration

### Ports utilisés

- **8083** : Nginx (Application web)
- **3309** : MySQL (Base de données)
- **9000** : PHP-FPM (Interne)

### Configuration Stripe

1. Créer un compte sur [Stripe](https://stripe.com)
2. Récupérer les clés de test dans le Dashboard
3. Ajouter les clés dans le fichier `.env`
4. Configurer les URLs de redirection :
   - Success URL : `http://localhost:8083/payment/success`
   - Cancel URL : `http://localhost:8083/payment/cancel/{id}`

### Configuration Docker

Le fichier `docker-compose.yml` définit 3 services :

- **php** : PHP 8.2-FPM avec toutes les extensions nécessaires
- **mysql** : MySQL 8.0 avec la base `actual_db`
- **nginx** : Serveur web sur le port 8083

## 🎮 Utilisation

### Accéder à l'application

Ouvrir votre navigateur : `http://localhost:8083`

### Interface utilisateur

1. **Page d'accueil** : Présentation des cours disponibles
2. **Sessions** : Liste des sessions avec places disponibles
3. **Inscription/Connexion** : Créer un compte ou se connecter
4. **Réservation** : Cliquer sur "Réserver" → Paiement Stripe → Confirmation
5. **Mon espace** : Voir ses réservations et historique de paiements
6. **Annulation** : Annuler jusqu'à 24h avant → Remboursement automatique

### Interface admin

1. Se connecter avec le compte admin
2. Accéder à `/admin`
3. Gérer les cours, sessions, et consulter les statistiques

## 📁 Structure du projet

```
projet_test/
├── config/                  # Configuration Symfony
│   ├── packages/           # Configuration des bundles
│   ├── routes/             # Définition des routes
│   └── services.yaml       # Services (StripeService)
├── migrations/             # Migrations Doctrine
├── public/                 # Fichiers publics
│   ├── css/
│   │   └── style.css      # Styles responsive
│   └── images/
│       └── actual.png     # Logo
├── src/
│   ├── Command/           # Commandes CLI
│   │   └── CreateAdminCommand.php
│   ├── Controller/        # Contrôleurs
│   │   ├── WebController.php          # Routes web
│   │   ├── StripePaymentController.php # Paiements
│   │   ├── AuthController.php         # Authentification
│   │   └── ...
│   ├── Entity/           # Entités Doctrine
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Session.php
│   │   ├── Registration.php
│   │   └── Payment.php
│   ├── Repository/       # Repositories Doctrine
│   └── Services/         # Services métier
│       └── StripeService.php
├── templates/            # Templates Twig
│   ├── base.html.twig   # Template de base
│   ├── web/             # Templates pages web
│   │   ├── home.html.twig
│   │   ├── sessions.html.twig
│   │   └── dashboard.html.twig
│   ├── payment/         # Templates paiement
│   │   ├── session.html.twig
│   │   ├── success.html.twig
│   │   └── cancel.html.twig
│   └── security/        # Templates auth
├── docker/              # Configuration Docker
├── docker-compose.yml   # Orchestration Docker
├── composer.json        # Dépendances PHP
└── .env                 # Variables d'environnement
```

## 🌐 API et Routes

### Routes publiques

- `GET /` - Page d'accueil
- `GET /courses` - Liste des cours
- `GET /sessions` - Liste des sessions disponibles
- `GET /login` - Page de connexion
- `POST /login` - Authentification
- `GET /register` - Page d'inscription
- `POST /register` - Création de compte

### Routes utilisateur (authentification requise)

- `GET /dashboard` - Espace personnel
- `POST /session/{id}/book` - Réserver une session
- `POST /registration/{id}/cancel` - Annuler une réservation

### Routes paiement (authentification requise)

- `GET /payment/session/{id}` - Page de paiement
- `POST /payment/session/{id}/checkout` - Créer session Stripe
- `GET /payment/success` - Confirmation de paiement
- `GET /payment/cancel/{id}` - Annulation de paiement

### Routes admin (ROLE_ADMIN requis)

- `GET /admin` - Dashboard admin
- CRUD des cours et sessions

### API REST (JWT requis)

- `POST /api/login_check` - Obtenir token JWT
- `GET /api/courses` - Liste des cours (JSON)
- `GET /api/sessions` - Liste des sessions (JSON)
- `POST /api/sessions/{id}/register` - Réservation (JSON)

## 💳 Paiements Stripe

### Flux de paiement

1. **Sélection de session** : L'utilisateur choisit une session
2. **Redirection Stripe** : Création d'une Checkout Session
3. **Paiement** : L'utilisateur paie via Stripe Checkout
4. **Retour** : Redirection vers la page de succès
5. **Enregistrement** : Création de la réservation et du paiement
6. **Confirmation** : Affichage de la confirmation

### Remboursements

Les remboursements sont automatiques lors d'une annulation :

1. **Validation** : Vérification du délai de 24h
2. **Récupération** : Obtention du PaymentIntent ID
3. **Remboursement** : Création du refund via Stripe API
4. **Notification** : Message de confirmation à l'utilisateur
5. **Mise à jour** : Changement du statut de la réservation

Le remboursement est effectif sous 5-10 jours ouvrés.

### Configuration des clés de test Stripe

```env
# Format des clés
STRIPE_PUBLIC_KEY=pk_test_51...
STRIPE_SECRET_KEY=sk_test_51...
```

⚠️ **Important** : Ne jamais commiter les clés réelles dans Git !

## 🐳 Docker

### Commandes Docker utiles

```bash
# Démarrer les conteneurs
docker-compose up -d

# Arrêter les conteneurs
docker-compose down

# Voir les logs
docker-compose logs -f

# Accéder au conteneur PHP
docker exec -it projet_actual_php bash

# Accéder à MySQL
docker exec -it projet_actual_mysql mysql -uroot -proot actual_db

# Reconstruire les images
docker-compose build --no-cache

# Nettoyer Docker
docker system prune -a
```

### Volumes Docker

Les données persistantes sont stockées dans :
- `mysql_data` : Données MySQL
- `./` : Code source (bind mount)

## 🔧 Commandes utiles

### Symfony

```bash
# Créer une migration
docker exec -it projet_actual_php php bin/console make:migration

# Appliquer les migrations
docker exec -it projet_actual_php php bin/console doctrine:migrations:migrate

# Créer un admin
docker exec -it projet_actual_php php bin/console app:create-admin

# Vider le cache
docker exec -it projet_actual_php php bin/console cache:clear
```

### Composer

```bash
# Installer une dépendance
docker exec -it projet_actual_php composer require vendor/package

# Mettre à jour les dépendances
docker exec -it projet_actual_php composer update
```

### Base de données

```bash
# Créer la base
docker exec -it projet_actual_php php bin/console doctrine:database:create

# Supprimer la base
docker exec -it projet_actual_php php bin/console doctrine:database:drop --force

# Voir le schéma SQL
docker exec -it projet_actual_php php bin/console doctrine:schema:update --dump-sql
```

## 📱 Responsive Design

Le site est entièrement responsive avec 2 breakpoints :

- **Desktop** : > 768px
- **Tablette** : ≤ 768px
- **Mobile** : ≤ 480px

Adaptations mobiles :
- Navigation verticale
- Grilles en 1 colonne
- Tailles de police réduites
- Boutons et badges optimisés
- Tables avec scroll horizontal

## 🎨 Design

- **Police** : Helvetica Neue
- **Couleurs principales** :
  - Noir (#000000) : Header, footer, boutons
  - Rouge (#E63946) : Logo, badges de places
  - Vert (#10b981) : Badge confirmé
  - Gris clair : Fond (#ecf0f1)
- **Style** : Moderne, épuré, cards avec ombres
- **Animations** : Hover effects, transitions fluides

## 📝 Historique Git

```bash
# Sauvegarder l'état actuel
git add -A
git commit -m "Description des changements"

# Revenir à un commit précédent
git checkout <commit-id>

# Créer une branche
git branch nom-branche <commit-id>
```

## 🐛 Dépannage

### Problème de port déjà utilisé

Si les ports 8083 ou 3309 sont occupés :
```bash
# Modifier dans docker-compose.yml
ports:
  - "8084:80"    # Changer 8083 en 8084
  - "3310:3306"  # Changer 3309 en 3310
```

### Erreur "vendor/autoload_runtime.php not found"

```bash
docker exec -it projet_actual_php composer install
```

### Erreur JWT

```bash
docker exec -it projet_actual_php php bin/console lexik:jwt:generate-keypair
```

### Erreur de migration

```bash
docker exec -it projet_actual_php php bin/console doctrine:schema:update --force
```

### Stripe : Remboursement ne fonctionne pas

Vérifier que :
1. Les clés Stripe sont correctes dans `.env`
2. Le PaymentIntent ID est bien stocké (commence par `pi_`)
3. Le paiement existe dans le Dashboard Stripe

## 📄 Licence

Ce projet est développé pour Actual Sport.

## 👨‍💻 Auteur

Développé avec ❤️ pour Actual Sport

---

**Version** : 1.0.0  
**Dernière mise à jour** : Février 2026
