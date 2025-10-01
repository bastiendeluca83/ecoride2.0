# EcoRide – Plateforme de covoiturage écologique

## 1 Présentation
EcoRide est une application web de covoiturage (architecture MVC en **PHP 8.2**) avec **MySQL** (relationnel) et **MongoDB** (NoSQL).  
Le front est réalisé avec **Bootstrap (CDN)** et un léger **CSS** pour la navbar.  
Le projet est entièrement conteneurisé avec **Docker Compose**.

## 2 Fonctionnalités (extrait)
- Inscription/connexion (20 crédits offerts) + rôles **USER / EMPLOYEE / ADMIN**
- Recherche de covoiturages par ville/date + filtres (électrique, prix, durée, note)
- Détail d’un trajet (avis, véhicule, préférences), participation avec crédits (double confirmation)
- Espaces : **Utilisateur** (trajets, véhicules, préférences), **Employé** (validation avis / litiges), **Admin** (employés, suspensions, graphiques J/J)

## 3 Prérequis
- Git
- Docker + Docker Compose
- Navigateur web

## 4 Installation & lancement
```bash
git clone https://github.com/bastiendeluca83/ecoride2.0.git
cd ecoride

# Variables minimales attendues par l'app :
# DB_HOST=db
# DB_NAME=ecoride
# DB_USER=ecoride_user
# DB_PASS=ecoride_password
# MONGO_URI=mongodb://root:secret@mongo:27017/?authSource=admin
# MONGO_DB=ecoride
# MAIL_HOST=mailhog ; MAIL_PORT=1025 ; MAIL_FROM_ADDRESS=no-reply@ecoride.fr ; MAIL_FROM_NAME=EcoRide

# Lancer les services (Git Bash sous Windows : préfixer par MSYS_NO_PATHCONV=1)
MSYS_NO_PATHCONV=1 docker compose up -d --build
```

## 5 Services & accès (depuis `docker-compose.yml`)
- **Application (PHP/Apache)** : http://localhost:8080  
  Variables d'env injectées :
  - `DB_HOST=db`, `DB_NAME=ecoride`, `DB_USER=ecoride_user`, `DB_PASS=ecoride_password`
  - `MONGO_URI=mongodb://root:secret@mongo:27017/?authSource=admin`, `MONGO_DB=ecoride`

- **MySQL (db)** : port hôte **3306**  
  - Image : `mysql:8.0`  
  - Utilisateur : `ecoride_user` / `ecoride_password`  
  - Base : `ecoride`  
  - Root : `root` / `root`  
  - Initialisation : volume `./docker/mysql-init` → scripts exécutés au 1er démarrage (`docker/mysql-init/init.sql`).

- **phpMyAdmin** : http://localhost:8081  
  - Hôte : `db` (service MySQL) – Port : `3306`  
  - User/Pass : `ecoride_user` / `ecoride_password` (ou `root` / `root`)

- **MongoDB (mongo)** : port interne 27017 (non exposé)  
  - Image : `mongo:7` – Auth : `root` / `secret`  
  - Données : volume `mongo_data`

- **Mongo Express (UI)** : http://localhost:8082 (bindé sur 127.0.0.1)  
  - Image : `mongo-express` (lecture seule)  
  - `ME_CONFIG_MONGODB_SERVER=mongo`

- **Mailhog** (SMTP de dev + Web UI) :  
  - SMTP : `localhost:1025`  
  - UI : http://localhost:8025  

## 6 Commandes utiles
```bash
# Logs temps réel
docker compose logs -f

# Redémarrer les services
docker compose restart

# Ouvrir un shell dans le conteneur PHP
docker compose exec php bash

# Import SQL manuel (depuis l’hôte)
docker compose exec db sh -lc "mysql -uroot -proot ecoride < /docker-entrypoint-initdb.d/init.sql"
```

## 7 Jeu de données / SQL
- Script d’initialisation : `docker/mysql-init/init.sql` (création des tables + INSERT de test si présents).  
- Ajouter d’autres `.sql` dans `docker/mysql-init/` si nécessaire (exécutés au 1er démarrage).

  

## 9 Sécurité & notes
- Mots de passe hachés (PHP `password_hash()` / `password_verify()`)
- Sessions sécurisées + **token CSRF** sur formulaires sensibles
- Requêtes **PDO préparées** (anti-injection)
- En prod : ne pas exposer MongoDB ; ports stricts ; SMTP réel (remplacer Mailhog) ; secrets dans `.env` (ne pas commiter)

