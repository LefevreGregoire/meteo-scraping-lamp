# Météo Scraping LAMP (OpenWeatherMap)

Application PHP qui récupère la météo via l’API OpenWeatherMap, stocke les données dans MariaDB et les affiche sur une page web responsive.

![PHP](https://img.shields.io/badge/PHP-8.2-blue?logo=php)
![MariaDB](https://img.shields.io/badge/MariaDB-LAMP-green?logo=mariadb)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)
![Status](https://img.shields.io/badge/status-stable-success)

---

## Aperçu du projet

- Page météo (exemple d’affichage) :
  
  - ![Capture du site météo](https://www.dropbox.com/scl/fi/lzotwx9ged7w2pdl9l73k/SITE-METEO-2.0.PNG?rlkey=09y3dkofdiemiqo90wrtclryd&st=p26lenho&raw=1)
    
- Base de données (exemple de table) :
  
  - ![Table weather_data](https://www.dropbox.com/scl/fi/ttcmxitne3x15v8v2p26r/SELECT-ALL-weather_data-db.PNG?rlkey=14i79lft47hh4whnn4l3habhk&st=bsdjqe8d&raw=1)

---

## Contenu du projet

| Fichier | Description |
|----------|--------------|
| `meteo.php` | Script principal : récupère les données météo via API et les insère/affiche depuis MariaDB |
| `style.css` | Styles et mise en page de la page météo |
| `meteo.sql` | Script SQL de création de la base et de la table |
| `config.php.sample` | Exemple de fichier de configuration (à copier en `config.php`) |
| `Projet_CCF.md` | Documentation technique complète du projet (déploiement, code, infrastructure) |

---

## Prérequis

- **Système :** Debian / Ubuntu  
- **Serveur web :** Apache2  
- **Langages :** PHP 8+  
- **Base de données :** MariaDB  
- **Clé API :** [OpenWeatherMap](https://openweathermap.org/api)

### Installation des paquets

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql libapache2-mod-php
``` 

## Installation

**Créer la base et la table**

```bash
sudo mysql -u root -p < meteo.sql
```
**Configurer l’accès et l’API**

```bash
cp config.php.sample config.php
nano config.php  # renseigner db_user, db_pass et owm_api_key
```

**Déployer dans Apache**

```bash
sudo cp meteo.php style.css /var/www/html/
sudo cp config.php /var/www/html/
```
**Tester localement**

Via : http://localhost/meteo.php

**Vérifier Apache/MariaDB**

```bash
sudo systemctl status apache2
sudo systemctl status mariadb
```

## Automatisation (cron)

Exécuter le script chaque minute :
```bash
crontab -e
* * * * * /usr/bin/php /var/www/html/meteo.php > /var/www/html/meteo_output.txt 2>&1
```

Vérifier la sortie :
```bash
tail -n 50 /var/www/html/meteo_output.txt
```

## Exemple de table SQL

```sql
CREATE TABLE weather_data (
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

## Dépannage rapide

- Clé API invalide :
  - Vérifie config.php (owm_api_key), les unités (`metric`) et la ville.
  - Teste l’URL brute : `curl "https://api.openweathermap.org/data/2.5/weather?q=Andernos-les-Bains&appid=TA_CLE&units=metric&lang=fr"`
- Erreur DB (PDO) : vérifier user/pass et que la DB `meteo` et la table existent.
- Page blanche PHP : vérifier les logs Apache.
  - Debian/Ubuntu: `/var/log/apache2/error.log`
- Pas d’images : utiliser un fichier présent dans un dépôt (ex.: `images/city.jpg`).

## Liens utiles

- [OpenWeatherMap API](https://openweathermap.org/api)
- [Apache HTTP Server](https://httpd.apache.org/)
- [MariaDB](https://mariadb.org/)
- [PHP PDO](https://www.php.net/manual/fr/book.pdo.php)
- Documentation détaillée : [MeteoScrapingLamp.md](./MeteoScrapingLamp.md)


## Licence

MIT
