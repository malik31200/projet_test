# Exposé : Plateforme Actual Sport
## Présentation du projet de réservation de sessions sportives

**Durée : 10 minutes**

---

## Introduction (1 minute)

Bonjour à tous,

Aujourd'hui, je vais vous présenter **Actual Sport**, une plateforme web moderne de gestion et de réservation de sessions sportives que j'ai développée. Ce projet répond à un besoin concret : permettre aux utilisateurs de réserver facilement des séances de sport tout en offrant une gestion simplifiée pour les administrateurs de la salle.

L'objectif principal était de créer une solution complète intégrant des paiements sécurisés en ligne, une interface utilisateur intuitive et responsive, et un système de remboursement automatique en cas d'annulation.

---

## Présentation du projet (2 minutes)

### Contexte et problématique

Dans le secteur du fitness et du sport, la gestion des réservations reste souvent manuelle ou utilise des outils peu adaptés. Les clients doivent parfois appeler pour réserver, et les annulations sont complexes à gérer. Avec Actual Sport, j'ai voulu digitaliser complètement ce processus.

### Solution proposée

Actual Sport est une application web qui permet :
- Aux **utilisateurs** de consulter les cours disponibles, réserver des sessions avec paiement en ligne, et gérer leurs réservations depuis un tableau de bord personnel
- Aux **administrateurs** de créer des cours, planifier des sessions, et suivre l'activité en temps réel

### Fonctionnalités clés

Du côté utilisateur, la plateforme offre :
- Une inscription et connexion sécurisées
- La consultation des cours avec leurs détails : prix, durée, description
- La réservation de sessions avec visualisation des places disponibles en temps réel
- Un paiement sécurisé via Stripe
- Un espace personnel pour suivre ses réservations
- La possibilité d'annuler jusqu'à 24h avant la session avec remboursement automatique

Du côté administrateur :
- Un dashboard avec statistiques
- La gestion complète des cours (création, modification, activation/désactivation)
- La planification des sessions avec définition des horaires et capacités
- Le suivi des réservations et des utilisateurs

---

## Architecture technique (2 minutes)

### Stack technologique

Pour développer cette plateforme, j'ai choisi une architecture moderne basée sur :

**Backend :**
- **Symfony 6.4** comme framework PHP principal - il offre une structure solide, une bonne documentation et des outils puissants pour le développement rapide
- **PHP 8.2** pour bénéficier des dernières fonctionnalités du langage
- **Doctrine ORM** pour la gestion de la base de données, ce qui permet de manipuler les données comme des objets PHP
- **MySQL 8.0** comme système de gestion de base de données

**Frontend :**
- **Twig** comme moteur de templates pour générer les pages HTML
- **CSS3** personnalisé avec un design moderne et responsive
- La police **Helvetica Neue** pour un rendu professionnel

**Infrastructure :**
- **Docker et Docker Compose** pour conteneuriser l'application - cela garantit que le projet fonctionne de manière identique sur tous les environnements
- **Nginx** comme serveur web
- **PHP-FPM** pour l'exécution de PHP

**Intégration de paiement :**
- **Stripe Checkout** pour les pages de paiement sécurisées
- **Stripe API** pour la gestion des remboursements automatiques

### Architecture de la base de données

Le modèle de données repose sur 5 entités principales :

1. **User** : gère les utilisateurs avec leurs rôles (utilisateur standard ou administrateur)
2. **Course** : représente un type de cours (Yoga, Musculation, etc.) avec son prix et sa durée
3. **Session** : une instance planifiée d'un cours avec date, horaire et places disponibles
4. **Registration** : lie un utilisateur à une session avec un statut (confirmé ou annulé)
5. **Payment** : enregistre les transactions Stripe avec le montant et l'ID de paiement

Ces entités sont reliées entre elles pour assurer l'intégrité des données. Par exemple, une suppression de session ne supprime pas l'historique des réservations.

---

## Flux utilisateur détaillé (2 minutes)

Laissez-moi vous présenter le parcours complet d'un utilisateur sur la plateforme.

### Phase 1 : Découverte et inscription

L'utilisateur arrive sur la page d'accueil qui présente les différents cours disponibles. Il peut parcourir la liste des sessions programmées, voir les horaires, les places disponibles affichées en temps réel avec un badge rouge, et les tarifs.

Pour réserver, il doit créer un compte. Le formulaire d'inscription demande simplement un nom, un email et un mot de passe. Le mot de passe est automatiquement hashé en base de données pour la sécurité.

### Phase 2 : Réservation et paiement

Une fois connecté, l'utilisateur clique sur "Réserver cette session". Le système vérifie d'abord qu'il n'est pas déjà inscrit à cette session et qu'il reste des places disponibles.

Il est ensuite redirigé vers une page de paiement Stripe Checkout. Cette page est générée dynamiquement avec les informations de la session : nom du cours, date, horaire et prix. 

L'utilisateur entre ses informations de carte bancaire directement sur l'interface Stripe - je ne gère jamais les données bancaires sur mon serveur, c'est Stripe qui s'en occupe pour respecter les normes de sécurité PCI-DSS.

Après le paiement, Stripe me renvoie un identifiant de transaction que je stocke en base de données. Cet identifiant, appelé PaymentIntent ID, est crucial pour pouvoir effectuer des remboursements plus tard.

La réservation est alors créée avec le statut "confirmé", le nombre de places disponibles est automatiquement décrémenté, et l'utilisateur reçoit une confirmation.

### Phase 3 : Gestion et annulation

L'utilisateur accède à son espace personnel où il voit toutes ses réservations avec leurs statuts. Les réservations confirmées ont un badge vert, les annulées un badge rouge.

S'il souhaite annuler, il doit le faire au moins 24 heures avant la session. Le système calcule automatiquement ce délai. Si c'est possible, il clique sur "Annuler la réservation".

À ce moment-là, plusieurs actions s'exécutent automatiquement :
1. Le statut de la réservation passe à "annulé"
2. La place est libérée et redevient disponible pour d'autres utilisateurs
3. Le système récupère l'ID de paiement Stripe stocké en base
4. Un appel API est fait à Stripe pour créer un remboursement complet
5. L'utilisateur reçoit un message confirmant le remboursement qui sera effectif sous 5 à 10 jours ouvrés

---

## Points techniques remarquables (2 minutes)

### Intégration Stripe avancée

L'intégration de Stripe a nécessité une attention particulière. J'ai créé un service dédié, `StripeService`, qui centralise toutes les interactions avec l'API Stripe.

Le point le plus technique a été la gestion correcte des identifiants. Stripe génère deux types d'ID : le Checkout Session ID pour la page de paiement, et le PaymentIntent ID qui correspond à la transaction réelle. Pour pouvoir rembourser, j'ai besoin du PaymentIntent ID.

J'ai donc mis en place un système où, après le paiement, je récupère le Checkout Session ID depuis Stripe, puis j'utilise l'API Stripe pour obtenir le PaymentIntent ID associé, que je stocke en base. C'est ce PaymentIntent ID qui est utilisé lors d'un remboursement.

### Containerisation Docker

Le projet est entièrement containerisé avec Docker Compose. Cela signifie que l'environnement de développement est reproductible à l'identique sur n'importe quelle machine.

J'ai trois conteneurs :
- **PHP-FPM** : contient le code Symfony et exécute PHP
- **MySQL** : héberge la base de données
- **Nginx** : sert de serveur web et reverse proxy

Cette approche facilite grandement le déploiement et évite les problèmes du type "ça marche sur ma machine".

Les ports sont configurés pour éviter les conflits : 8083 pour l'application web, 3309 pour MySQL.

### Sécurité

Plusieurs mesures de sécurité ont été implémentées :
- **Authentification JWT** pour l'API REST, permettant une authentification stateless
- **Hashage bcrypt** des mots de passe avec un coût élevé
- **Validation des données** à tous les niveaux : formulaires, contrôleurs, et base de données
- **Protection CSRF** sur tous les formulaires
- **Gestion des rôles** avec Symfony Security pour différencier utilisateurs et administrateurs
- **Aucune donnée bancaire** stockée - tout passe par Stripe

### Responsive Design

Le site est entièrement responsive avec des media queries CSS. J'ai défini deux breakpoints :
- 768px pour les tablettes : la navigation passe en vertical, les grilles s'adaptent
- 480px pour les smartphones : tout passe en colonne unique, les tailles de police sont réduites

Tous les éléments s'adaptent : les cartes, les tableaux (qui passent en scroll horizontal), les badges, et même la navigation.

---

## Conclusion et perspectives (1 minute)

Pour conclure, Actual Sport est une plateforme complète qui digitalise l'expérience de réservation sportive. Elle combine une interface utilisateur moderne et intuitive avec une architecture technique robuste.

Les points forts du projet sont :
- L'intégration complète des paiements et remboursements automatiques
- Une architecture propre et maintenable grâce à Symfony
- Un design moderne et entièrement responsive
- Une containerisation Docker facilitant le déploiement

### Améliorations futures possibles

Si je devais faire évoluer le projet, je pourrais ajouter :
- Un système de notifications email (confirmation de réservation, rappel 24h avant)
- Un calendrier interactif pour visualiser les sessions
- Un système d'abonnement mensuel avec carnets de séances
- Une application mobile native iOS/Android
- Un module de gestion des coachs avec leurs disponibilités
- Des statistiques avancées pour les administrateurs (taux de remplissage, revenus, etc.)
- Un système de fidélité avec points de récompense

### Démo rapide

Je vous propose maintenant une démonstration rapide du site si nous avons le temps, pour vous montrer concrètement l'interface et le parcours utilisateur.

Merci pour votre attention. Je suis maintenant disponible pour répondre à vos questions.

---

## Questions fréquentes (préparation)

**Q : Pourquoi avoir choisi Symfony plutôt que Laravel ou un autre framework ?**
R : Symfony offre une architecture très structurée et modulaire, une excellente documentation en français, et une communauté très active. De plus, sa gestion des bundles facilite l'ajout de fonctionnalités.

**Q : Comment gérez-vous la concurrence lors des réservations ?**
R : Doctrine gère automatiquement les transactions. De plus, je vérifie les places disponibles juste avant la création de réservation. Pour une version production, on pourrait ajouter un système de verrou optimiste.

**Q : Combien de temps a pris le développement ?**
R : Le développement initial avec les fonctionnalités de base a pris environ [X semaines/mois], avec l'intégration Stripe et les remboursements ajoutant environ [X] de temps supplémentaire.

**Q : Le site est-il en production ?**
R : Actuellement en mode test avec les clés sandbox de Stripe. Pour passer en production, il faudrait configurer les clés live de Stripe et déployer sur un serveur de production.

**Q : Combien coûte l'utilisation de Stripe ?**
R : Stripe prend 1,4% + 0,25€ par transaction réussie en Europe. Pour les remboursements, les frais Stripe ne sont pas remboursés mais il n'y a pas de frais supplémentaires.

---

**Temps total : ~10 minutes**
**Mots : ~1400**
