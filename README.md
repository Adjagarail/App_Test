# Application First - Stack Docker Symfony

Application Symfony 7.2 avec PostgreSQL, Nginx, Jenkins et SonarQube.

## Architecture

- **app** : PHP 8.4-FPM avec Symfony
- **nginx** : Serveur web (port 8080)
- **db** : PostgreSQL 13 (port 5433)
- **postgres-sonar** : PostgreSQL 13 pour SonarQube
- **sonarqube** : Analyse de code (port 9000)
- **jenkins** : CI/CD (port 8081)

## Prérequis

- Docker
- Docker Compose

## Installation

1. Cloner le projet

2. Construire et démarrer les conteneurs :
```bash
docker-compose up -d --build
```

3. Installer les dépendances Symfony :
```bash
docker-compose exec app composer install
```

4. Créer la base de données :
```bash
docker-compose exec app php bin/console doctrine:database:create
docker-compose exec app php bin/console doctrine:migrations:migrate
```

## Accès aux services

- **Application Symfony** : http://localhost:8080
- **Jenkins** : http://localhost:8081
- **SonarQube** : http://localhost:9000
- **PostgreSQL** : localhost:5433

## Commandes utiles

### Symfony
```bash
# Console Symfony
docker-compose exec app php bin/console

# Cache clear
docker-compose exec app php bin/console cache:clear

# Créer une migration
docker-compose exec app php bin/console make:migration

# Exécuter les migrations
docker-compose exec app php bin/console doctrine:migrations:migrate
```

### Docker
```bash
# Démarrer les conteneurs
docker-compose up -d

# Arrêter les conteneurs
docker-compose down

# Voir les logs
docker-compose logs -f

# Reconstruire les images
docker-compose up -d --build
```

## Structure du projet

```
.
├── back/                  # Code source Symfony
├── docker/
│   ├── nginx/            # Configuration Nginx
│   ├── db_data/          # Données PostgreSQL (app)
│   ├── postgres_sonar_data/  # Données PostgreSQL (SonarQube)
│   ├── jenkins_data/     # Données Jenkins
│   ├── sonarqube_data/   # Données SonarQube
│   └── sonarqube_extensions/ # Extensions SonarQube
├── Dockerfile            # Image PHP-FPM personnalisée
└── docker-compose.yaml   # Orchestration des services
```

## Configuration

### Base de données
- User: `alpha_user`
- Password: `root`
- Database: `symfony`
- Port: `5433` (depuis l'hôte)

### SonarQube
- User par défaut: `admin`
- Password par défaut: `admin`

### Jenkins
Le mot de passe initial se trouve dans :
```bash
docker-compose exec jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```
# App_Test
