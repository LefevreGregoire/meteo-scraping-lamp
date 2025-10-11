# Documentation - Récupération et affichage de données météo


## Table des matières

1. [Introduction](#1-introduction)  
2. [Configuration de l’infrastructure](#2-configuration-de-linfrastructure)  
   - 2.1 [Création des machines virtuelles](#21-création-des-machines-virtuelles)  
   - 2.2 [Configuration des adresses IP statiques](#22-configuration-des-adresses-ip-statiques)  
   - 2.3 [Test des connexions réseau](#23-test-des-connexions-réseau)  
3. [Installation des services](#3-installation-des-services)  
   - 3.1 [Installation d’Apache, PHP et MariaDB (LAMP)](#31-installation-dapache-php-et-mariadb-lamp)  
   - 3.2 [Configuration de MariaDB](#32-configuration-de-mariadb)  
4. [Développement du script météo](#4-développement-du-script-météo)  
   - 4.1 [Script PHP pour affichage](#41-script-php-pour-affichage)  
   - 4.2 [Automatisation avec cron](#42-automatisation-avec-cron)  
5. [Création de l’interface web](#5-création-de-linterface-web)  
6. [Accès distant au serveur LAMP (SSH)](#6-accès-distant-au-serveur-lamp-ssh)  
7. [Tests et validation](#7-tests-et-validation)

---
### Introduction

Ce projet consiste en la mise en place d'un système permettant de récupérer des données météorologiques en temps réel via l'API OpenWeatherMap, de les stocker dans une base de données MariaDB, puis d'afficher ces données sur une page web dynamique. L'infrastructure se compose de plusieurs machines virtuelles (VMs) configurées sous Debian et d'un serveur LAMP (Linux, Apache, MySQL, PHP).

**Technologies utilisées**:
- Debian pour les VMs
- Apache, MySQL, PHP (LAMP stack)
- MariaDB pour la gestion des données
- OpenWeatherMap API pour récupérer les données météo avec un script en php
- HTML, CSS pour l'affichage des résultats
- Cron pour automatiser la récupération des données
- SSH pour contrôler à distance le serveur LAMP



## 1. Configuration des VMs

### 1.1 Installation de VMware et création des VMs
Le projet repose sur une infrastructure de 4 VMs créées avec **VMware Workstation**. Voici les étapes pour configurer et installer chaque VM :

1. **VM1 - Serveur Routeur** :
   - **Rôle** : Ce serveur agira comme routeur pour connecter les autres VMs et fournir un accès à Internet.
   - **Paramètres réseau** : 
     - **Type de réseau** : **NAT** 
     - **Adresse IP statique** : 192.168.10.1.
   - **Installation de Debian** sur cette VM.

2. **VM2 - Serveur LAMP (DMZ)** :
   - **Rôle** : Serveur web Apache avec PHP et MariaDB.
   - **Paramètres réseau** :
     - **Type de réseau** : **LAN** (DMZ) avec une adresse IP statique, ici : 192.168.10.250.
     - **Connexion** : La VM LAMP se connecte au serveur routeur pour l'accès à Internet et à la base de données.
   - **Installation de Debian** et **LAMP**.

3. **VM3 - Serveur DHCP/DNS** :
   - **Rôle** : Attribution dynamique d'adresses IP (via DHCP) et gestion des résolutions DNS pour le réseau interne.
   - **Paramètres réseau** :
     - **Type de réseau** : **LAN** avec une adresse IP statique, ici : 192.168.10.251.
     - **Réseau DHCP** : Attribuer les IPs aux autres VMs (client, LAMP).
   - **Installation de Debian**, configuration de **ISC DHCP** et **BIND9** pour DNS.
   
4. **VM4 - Client** :
   - **Rôle** : Test de l'accès au serveur web (LAMP) et à la base de données.
   - **Paramètres réseau** : 
     - **Type de réseau** : **LAN** avec IP dynamique via DHCP.
   - **Installation de Debian** pour tester l'accès aux services.

Exemple de configuration d'une VM, ici le routeur : 

![Capture de la VM Routeur](https://www.dropbox.com/scl/fi/nchezkwua7ac4wdu9k0ev/VM-Routeur.PNG?rlkey=fkjcgbzlr77cztqyd40kl24fq&st=vbqpsf5a&raw=1)



### 1.2 Configuration des adresses IP statiques pour chaque VM


Pour chaque VM, il faut configurer une adresse IP statique dans le fichier `/etc/network/interfaces` via la commande :

```bash
sudo nano /etc/network/interfaces
```
Pour le routeur (VM1) :
```bash
# Interface reliant au client en DHCP
auto ens33
iface ens33 inet dhcp

# Interface reliant au DHCP/DNS (passerelle vers le client)
auto ens36
iface ens36 inet static
address 192.168.32.254/24
netmask 255.255.255.0

# Interface reliant au LAMP (avec un sous-réseau /29)
auto ens37
iface ens37 inet static
address 192.168.10.254/29
netmask 255.255.255.248

```
Pour le serveur LAMP (VM2) : 

```bash
auto ens33
iface ens33 inet static
    address 192.168.10.250
    gateway 192.168.10.254
    dns-nameservers 8.8.8.8
```

Pour le serveur DHCP/DNS (VM3) :

```bash
auto ens33
iface ens33 inet static
    address 192.168.32.250
    gateway 192.168.32.254
    dns-nameservers 8.8.8.8
```

Pour le Client (VM4) : 

```bash
auto ens33
iface ens33 inet dhcp
```

Redémarrer les réseaux de chaque VM avec : 

```bash
sudo systemctl restart networking
```

### 1.3 Tester les connexions réseau

````bash
ping 192.168.10.1  # Tester la connectivité entre la VM LAMP, le client et le routeur
ping google.com    # tester l'accès à Internet depuis la VM LAMP et le client
````

## 2. Installation et configuration des services

### 2.1 Installer Apache, MySQL (MariaDB), PHP (LAMP)

Sur la VM LAMP (VM2), exécuter les commandes suivantes :

````bash
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 mariadb-server php php-mysql libapache2-mod-php
````

Vérifier qu'Apache fonctionne en entrant l'adresse IP de la VM LAMP dans un navigateur, vous devriez avoir ceci : 

![Capture de la page par défaut Apache](https://www.dropbox.com/scl/fi/zlelco9vs29w0yr908q8w/APACHE-default-page.webp?rlkey=msa7jaxlc3vzho4gd4k5wvkzo&st=6vsxnsqu&raw=1)


### 2.3 Démarrage Apache et MariaDB

````bash
sudo systemctl start apache2
sudo systemctl start mariadb
````

### 2.2 Configuration de MariaDB

Se connecter à MariaDB pour créer la base de données : 

````bash
sudo mysql -u root -p
````

Dans l'interface MariaDB, créer la base de données et la table :

````sql
CREATE DATABASE meteo;
USE meteo;

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
````

## 3. Création du script PHP pour récupérer et stocker les données

### 3.1 Script PHP pour récupérer les données depuis OpenWeatherMap

Avant de pouvoir récupérer les données météo, il est nécessaire d’obtenir une **clé API** auprès du service OpenWeatherMap. Cette clé est utilisée pour authentifier les requêtes que ton script PHP enverra au serveur de l’API.

Aller sur le site https://openweathermap.org/api et se créer un compte afin de récupérer la clé.

![Capture de la clé API](https://www.dropbox.com/scl/fi/r47dmnt6wlk9jghhgc43e/APIKEY.PNG?rlkey=86sy1olleyof9f5efvg4cej1y&st=lzmncvd7&raw=1)


**Créer le fichier PHP** afin de récupérer les données météo :
````bash
sudo nano /var/www/html/meteo.php
````

Voici le script à insérer dans `meteo.php` :

````php
<?php
// Informations de connexion à la base de données
$host = 'localhost';
$db = 'meteo';
$user = 'root';
$pass = 'root';

// Variables par défaut pour le debug
$temp = 'N/A';
$feels_like = 'N/A';
$humidity = 'N/A';
$windSpeed = 'N/A';
$weatherDescription = 'Données non disponibles';
$icon = '01d';
$iconUrl = "http://openweathermap.org/img/wn/01d.png";
$error_message = '';

// Récupération des données météo depuis OpenWeatherMap
$apiKey = '1e17cd5924e989abea8e0eb9b8cb00d8';  // Ta clé API
$city = 'Andernos-les-Bains';
$url = "https://api.openweathermap.org/data/2.5/weather?q=$city&appid=$apiKey&units=metric&lang=fr";

// Appel à l’API
$weatherJson = file_get_contents($url);
$weather = json_decode($weatherJson, true);

// Connexion et insertion en base si données valides
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($weather && isset($weather['main'])) {
        $temperature = $weather['main']['temp'];
        $feels_like = $weather['main']['feels_like'];
        $humidity = $weather['main']['humidity'];
        $windSpeed = $weather['wind']['speed'];
        $description = $weather['weather'][0]['description'];
        $icon = $weather['weather'][0]['icon'];

        $stmt = $pdo->prepare("INSERT INTO weather_data (temperature, feels_like, humidity, wind_speed, description, icon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$temperature, $feels_like, $humidity, $windSpeed, $description, $icon]);
    }

    // Récupération de la dernière donnée météo
    $stmt = $pdo->prepare("SELECT * FROM weather_data ORDER BY date_recorded DESC LIMIT 1");
    $stmt->execute();
    $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($weatherData) {
        $temp = $weatherData['temperature'];
        $feels_like = $weatherData['feels_like'];
        $humidity = $weatherData['humidity'];
        $windSpeed = $weatherData['wind_speed'];
        $weatherDescription = $weatherData['description'];
        $icon = $weatherData['icon'];
        $iconUrl = "http://openweathermap.org/img/wn/$icon.png";
    } else {
        $error_message = "Aucune donnée météo trouvée dans la base de données.";
    }

} catch (PDOException $e) {
    $error_message = "Erreur de connexion à la base de données : " . $e->getMessage();
}

// Définir l'apparence selon les conditions météo
if (strpos($weatherDescription, 'clear') !== false) {
    $conditionIcon = 'fas fa-sun';
    $bgClass = 'sunny';
} elseif (strpos($weatherDescription, 'cloud') !== false) {
    $conditionIcon = 'fas fa-cloud';
    $bgClass = 'cloudy';
} elseif (strpos($weatherDescription, 'rain') !== false) {
    $conditionIcon = 'fas fa-cloud-rain';
    $bgClass = 'rainy';
} else {
    $conditionIcon = 'fas fa-cloud';
    $bgClass = 'default';
}

// Affichage des erreurs (si présentes)
if (!empty($error_message)) {
    echo "<div style='background: red; color: white; padding: 20px; margin: 20px; border-radius: 10px;'>";
    echo "<h2>Erreur :</h2>";
    echo "<p>$error_message</p>";
    echo "</div>";
}
?>

// Affichage des résultats dans le HTML

<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Météo à Andernos-les-Bains</title>
    <link rel='stylesheet' href='style.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
</head>
<body class='<?php echo $bgClass; ?>'>
    <div class='background-shapes'>
        <div class='shape shape-1'></div>
        <div class='shape shape-2'></div>
        <div class='shape shape-3'></div>
    </div>
    
    <div class='container'>
        <header class='header'>
            <div class='location'>
                <i class='fas fa-map-marker-alt'></i>
                <h1>Andernos-les-Bains</h1>
            </div>
            <div class='last-update'>
                <i class='fas fa-clock'></i>
                <span>Dernière mise à jour: <?php echo date('H:i'); ?></span>
            </div>
        </header>

        <main class='weather-main'>
            <div class='current-weather-card'>
                <div class='weather-header'>
                    <div class='main-temp'>
                        <span class='temperature'><?php echo $temp; ?></span>
                        <span class='unit'>°C</span>
                    </div>
                    <div class='weather-icon-container'>
                        <img src='<?php echo $iconUrl; ?>' alt='<?php echo $weatherDescription; ?>' class='weather-icon'>
                        <div class='icon-bg'></div>
                    </div>
                </div>

                <div class='weather-description'>
                    <span><?php echo ucfirst($weatherDescription); ?></span>
                </div>

                <div class='feels-like'>
                    Ressenti <span><?php echo $feels_like; ?>°</span>
                </div>
            </div>

            <div class='weather-details'>
                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='fas fa-tint'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Humidité</span>
                        <span class='detail-value'><?php echo $humidity; ?>%</span>
                    </div>
                    <div class='detail-progress'>
                        <div class='progress-bar' style='width: <?php echo is_numeric($humidity) ? $humidity : 0; ?>%'></div>
                    </div>
                </div>

                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='fas fa-wind'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Vent</span>
                        <span class='detail-value'><?php echo $windSpeed; ?> m/s</span>
                    </div>
                    <div class='wind-indicator'>
                        <div class='wind-arrow' style='transform: rotate(45deg)'></div>
                    </div>
                </div>

                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='fas fa-thermometer-half'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Température</span>
                        <span class='detail-value'><?php echo $temp; ?>°C</span>
                    </div>
                    <div class='temp-gauge'>
                        <div class='gauge-fill' style='height: <?php echo is_numeric($temp) ? (($temp + 20) * 2) : 50; ?>%'></div>
                    </div>
                </div>

                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='<?php echo $conditionIcon; ?>'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Conditions</span>
                        <span class='detail-value'><?php echo ucfirst($weatherDescription); ?></span>
                    </div>
                </div>
            </div>
        </main>

        <footer class='footer'>
            <div class='city-showcase'>
                <img src='/home/greg/Downloads/jetee-et-centre-andernos-2.jpg' alt='Andernos-les-Bains' class='city-image'>
                <div class='city-info'>
                    <h3>Andernos-les-Bains</h3>
                    <p>Perle du Bassin d'Arcachon</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.current-weather-card, .detail-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Effet de parallaxe sur les formes d'arrière-plan
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                shape.style.transform = `translate(${x * speed * 20}px, ${y * speed * 20}px)`;
            });
        });
    </script>
</body>
</html>
````

### 3.2 Automatiser le script avec Cron

**Cron** est un service qui permet d'automatiser l'exécution de commandes à des intervalles réguliers sur des systèmes Unix-like. Voici comment automatiser l'exécution de notre script PHP à l'aide de **cron**.

Ouvrir le crontab avec la commande suivante :
 
````bash
crontab -e
````

Ajouter cette ligne à la fin du fichier pour exécuter le script chaque minute :
 
````bash
* * * * /usr/bin/php /var/www/html/meteo.php > /var/www/html/meteo_output.txt 2>&1
````

Cette ligne permet d'exécuter le script à 00 seconde de chaque minute (`* * * * *`) et de rediriger les sorties et erreurs dans le fichier `meteo_output.txt`.

Vérifier si le cron fonctionne correctement :

````bash
cat /var/www/html/meteo_output.txt
````

## 4. Mise en forme de la page web

### 4.1 Créer un fichier `style.css` pour la page

Crée le fichier `style.css` dans `/var/www/html/` :

````bash
sudo nano /var/www/html/style.css
````

Ajouter le code CSS que vous souhaitez pour styliser la page web.

## 5. Test et validation du projet

### 5.1 Vérifier l'affichage de la page et de la base de donnée

Accéder à `http://192.168.10.250/meteo.php` dans un navigateur pour voir les données météo.

![Capture du site météo](https://www.dropbox.com/scl/fi/lzotwx9ged7w2pdl9l73k/SITE-METEO-2.0.PNG?rlkey=09y3dkofdiemiqo90wrtclryd&st=p26lenho&raw=1)

Vérifier que les données sont bien enregistrées dans la base.

![Affichage de la table weather_data](https://www.dropbox.com/scl/fi/ttcmxitne3x15v8v2p26r/SELECT-ALL-weather_data-db.PNG?rlkey=14i79lft47hh4whnn4l3habhk&st=bsdjqe8d&raw=1)


## 6. Accés distant au serveur LAMP (SSH)

### 6.1 Activer SSH sur le serveur

Pour permettre un accès distant sécurisé à la VM LAMP depuis une autre machine (comme la VM client), il est nécessaire d’installer et d’activer le service SSH sur le serveur.

````bash
sudo apt install openssh-server -y
sudo systemctl enable ssh
sudo systemctl start ssh
````

### 6.2 Connexion depuis la VM client

Une fois le service SSH actif sur la VM LAMP, on peut s’y connecter à distance depuis la VM client en utilisant la commande `ssh`. Cela permet d’administrer le serveur sans devoir y accéder physiquement.

````bash
ssh meteo_user@192.168.10.250
````

## Conclusion

Ce projet m’a permis de mettre en œuvre une infrastructure réseau complète basée sur une architecture à plusieurs machines virtuelles, tout en assurant la collecte, le stockage et l’affichage dynamique de données météorologiques en temps réel.

À travers cette réalisation, j’ai consolidé mes compétences en :
- Administration de systèmes sous Linux (Debian)
- Configuration réseau (DHCP, DNS, routage, LAMP)
- Développement web en PHP et SQL
- Utilisation d’une API externe (OpenWeatherMap)
- Automatisation de tâches via cron

J’ai également rencontré et résolu plusieurs difficultés techniques, comme la communication inter-VM, la connexion à la base de données MariaDB, ou l’intégration des données API dans une interface claire et responsive.

