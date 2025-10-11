# Météo (OpenWeatherMap) sur LAMP

Application PHP qui récupère la météo via l’API OpenWeatherMap, stocke les données dans MariaDB et les affiche sur une page web responsive.

- Documentation détaillée: [MeteoScrapingLamp.md](./MeteoScrapingLamp.md)
- Dépôt: meteo-scraping-lamp (public)

## Aperçu

- Page météo (exemple d’affichage):
  - ![Capture du site météo](https://www.dropbox.com/scl/fi/lzotwx9ged7w2pdl9l73k/SITE-METEO-2.0.PNG?rlkey=09y3dkofdiemiqo90wrtclryd&st=p26lenho&raw=1)
- Base de données (exemple de table):
  - ![Table weather_data](https://www.dropbox.com/scl/fi/ttcmxitne3x15v8v2p26r/SELECT-ALL-weather_data-db.PNG?rlkey=14i79lft47hh4whnn4l3habhk&st=bsdjqe8d&raw=1)
- Page par défaut Apache (sanity check):
  - ![Apache default page](https://www.dropbox.com/scl/fi/zlelco9vs29w0yr908q8w/APACHE-default-page.webp?rlkey=msa7jaxlc3vzho4gd4k5wvkzo&st=6vsxnsqu&raw=1)

Astuce: si une image ne s’affiche pas, ouvre le lien brut. Tu peux aussi copier ces images dans un dossier docs/screenshots du dépôt.

## Fonctionnalités

- Récupération météo en temps réel via OpenWeatherMap
- Stockage dans MariaDB avec historique
- Affichage web dynamique (PHP + CSS)
- Automatisation via cron
- Architecture multi-VM possible (routeur, LAMP, DHCP/DNS, client)

## Prérequis

- Debian/Ubuntu avec apt
- Apache2, PHP, MariaDB
- Clé API OpenWeatherMap (gratuite): [OpenWeatherMap API](https://openweathermap.org/api)

Installation des paquets:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mariadb-server php php-mysql libapache2-mod-php
```

## Base de données

Créer la base et la table:
```sql
CREATE DATABASE IF NOT EXISTS meteo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE meteo;

CREATE TABLE IF NOT EXISTS weather_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  temperature DECIMAL(5,2),
  feels_like DECIMAL(5,2),
  humidity INT,
  wind_speed DECIMAL(5,2),
  description VARCHAR(255),
  icon VARCHAR(50),
  date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Ou via fichier:
```bash
sudo mysql -u root -p < meteo.sql
```

## Configuration de l’application

- Ne jamais committer ta clé API ni tes mots de passe.
- Copie config.php.sample en config.php (config.php est ignoré par git):
```bash
cp config.php.sample config.php
nano config.php  # renseigne db_user, db_pass, owm_api_key, city, ...
```

## Déploiement (Apache)

1) Copier les fichiers web sur le serveur Apache:
```bash
sudo cp meteo.php style.css config.php /var/www/html/
```

2) Tester dans le navigateur:
- http://IP_DU_SERVEUR/meteo.php

3) Vérifier Apache/MariaDB:
```bash
sudo systemctl status apache2
sudo systemctl status mariadb
```

## Automatisation (cron)

Exécuter le script chaque minute:
```bash
crontab -e
* * * * * /usr/bin/php /var/www/html/meteo.php > /var/www/html/meteo_output.txt 2>&1
```

Vérifier la sortie:
```bash
tail -n 50 /var/www/html/meteo_output.txt
```

## Développement local et Git

Initialiser et pousser vers GitHub:
```bash
git init
git add .
git commit -m "Initial commit: meteo-scraping-lamp"
git branch -M main
git remote add origin git@github.com:LefevreGregoire/meteo-scraping-lamp.git
git push -u origin main
```

## Dépannage rapide

- Clé API invalide:
  - Vérifie config.php (owm_api_key), les unités (`metric`) et la ville.
  - Teste l’URL brute: `curl "https://api.openweathermap.org/data/2.5/weather?q=Andernos-les-Bains&appid=TA_CLE&units=metric&lang=fr"`
- Erreur DB (PDO): vérifier user/pass et que la DB `meteo` et la table existent.
- Page blanche PHP: vérifier les logs Apache.
  - Debian/Ubuntu: `/var/log/apache2/error.log`
- Pas d’images: utilise un fichier présent dans ton dépôt (ex.: `images/city.jpg`).

## Liens utiles

- [OpenWeatherMap API](https://openweathermap.org/api)
- [Apache HTTP Server](https://httpd.apache.org/)
- [MariaDB](https://mariadb.org/)
- [PHP PDO](https://www.php.net/manual/fr/book.pdo.php)

## Licence

MIT (optionnel)