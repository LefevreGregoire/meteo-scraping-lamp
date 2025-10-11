<?php
// Chargement de la configuration (ne pas committer config.php)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Configuration manquante. Copiez config.php.sample en config.php et renseignez vos valeurs.";
    exit;
}
$config = require $configPath;

// Valeurs par défaut pour l'affichage
$temp = 'N/A';
$feels_like = 'N/A';
$humidity = 'N/A';
$windSpeed = 'N/A';
$weatherDescription = 'Données non disponibles';
$icon = '01d';
$iconUrl = "https://openweathermap.org/img/wn/01d.png";
$error_message = '';
$conditionIcon = 'fas fa-cloud';
$bgClass = 'default';

// Paramètres API
$apiKey = $config['owm_api_key'] ?? '';
$city   = $config['city'] ?? 'Andernos-les-Bains';
$units  = $config['units'] ?? 'metric';
$lang   = $config['lang'] ?? 'fr';

if (empty($apiKey)) {
    $error_message = "Clé API OpenWeatherMap manquante dans config.php.";
}

// Appel API (cURL avec timeouts)
$weather = null;
if (empty($error_message)) {
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) .
           "&appid=" . urlencode($apiKey) . "&units=" . urlencode($units) . "&lang=" . urlencode($lang);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $weatherJson = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($weatherJson === false || $httpCode >= 400) {
        $error_message = "Erreur API OpenWeatherMap: " . (!empty($curlErr) ? $curlErr : "HTTP $httpCode");
    } else {
        $weather = json_decode($weatherJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = "Erreur de décodage JSON: " . json_last_error_msg();
        }
    }
}

// Connexion DB et insertion si données valides
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (empty($error_message) && !empty($weather['main'])) {
        $temperature  = $weather['main']['temp'] ?? null;
        $feels_like_v = $weather['main']['feels_like'] ?? null;
        $humidity_v   = $weather['main']['humidity'] ?? null;
        $windSpeed_v  = $weather['wind']['speed'] ?? null;
        $description  = $weather['weather'][0]['description'] ?? 'N/A';
        $iconCode     = $weather['weather'][0]['icon'] ?? '01d';

        $stmt = $pdo->prepare("
            INSERT INTO weather_data (temperature, feels_like, humidity, wind_speed, description, icon)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$temperature, $feels_like_v, $humidity_v, $windSpeed_v, $description, $iconCode]);
    }

    // Récupération de la dernière donnée météo
    $stmt = $pdo->query("SELECT * FROM weather_data ORDER BY date_recorded DESC, id DESC LIMIT 1");
    $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($weatherData) {
        $temp               = $weatherData['temperature'];
        $feels_like         = $weatherData['feels_like'];
        $humidity           = $weatherData['humidity'];
        $windSpeed          = $weatherData['wind_speed'];
        $weatherDescription = $weatherData['description'];
        $icon               = $weatherData['icon'];
        $iconUrl            = "https://openweathermap.org/img/wn/" . htmlspecialchars($icon, ENT_QUOTES) . ".png";
    } else {
        if (empty($error_message)) {
            $error_message = "Aucune donnée météo trouvée dans la base de données.";
        }
    }
} catch (PDOException $e) {
    $error_message = "Erreur de connexion à la base de données : " . $e->getMessage();
}

// Définir l'apparence selon la description météo
$descLower = mb_strtolower((string)$weatherDescription, 'UTF-8');
if (strpos($descLower, 'clair') !== false || strpos($descLower, 'clear') !== false || strpos($descLower, 'soleil') !== false) {
    $conditionIcon = 'fas fa-sun';
    $bgClass = 'sunny';
} elseif (strpos($descLower, 'nuage') !== false || strpos($descLower, 'cloud') !== false) {
    $conditionIcon = 'fas fa-cloud';
    $bgClass = 'cloudy';
} elseif (strpos($descLower, 'pluie') !== false || strpos($descLower, 'rain') !== false) {
    $conditionIcon = 'fas fa-cloud-rain';
    $bgClass = 'rainy';
} else {
    $conditionIcon = 'fas fa-cloud';
    $bgClass = 'default';
}
?>
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Météo à <?php echo htmlspecialchars($city, ENT_QUOTES); ?></title>
    <link rel='stylesheet' href='style.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
</head>
<body class='<?php echo htmlspecialchars($bgClass, ENT_QUOTES); ?>'>
    <?php if (!empty($error_message)): ?>
        <div style='background: #c0392b; color: white; padding: 16px; margin: 12px; border-radius: 8px;'>
            <strong>Erreur :</strong>
            <div><?php echo htmlspecialchars($error_message, ENT_QUOTES); ?></div>
        </div>
    <?php endif; ?>

    <div class='background-shapes'>
        <div class='shape shape-1'></div>
        <div class='shape shape-2'></div>
        <div class='shape shape-3'></div>
    </div>

    <div class='container'>
        <header class='header'>
            <div class='location'>
                <i class='fas fa-map-marker-alt'></i>
                <h1><?php echo htmlspecialchars($city, ENT_QUOTES); ?></h1>
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
                        <span class='temperature'><?php echo htmlspecialchars($temp, ENT_QUOTES); ?></span>
                        <span class='unit'>°C</span>
                    </div>
                    <div class='weather-icon-container'>
                        <img src='<?php echo htmlspecialchars($iconUrl, ENT_QUOTES); ?>' alt='<?php echo htmlspecialchars($weatherDescription, ENT_QUOTES); ?>' class='weather-icon'>
                        <div class='icon-bg'></div>
                    </div>
                </div>

                <div class='weather-description'>
                    <span><?php echo htmlspecialchars(ucfirst((string)$weatherDescription), ENT_QUOTES); ?></span>
                </div>

                <div class='feels-like'>
                    Ressenti <span><?php echo htmlspecialchars($feels_like, ENT_QUOTES); ?>°</span>
                </div>
            </div>

            <div class='weather-details'>
                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='fas fa-tint'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Humidité</span>
                        <span class='detail-value'><?php echo htmlspecialchars($humidity, ENT_QUOTES); ?>%</span>
                    </div>
                    <div class='detail-progress'>
                        <div class='progress-bar' style='width: <?php echo is_numeric($humidity) ? (int)$humidity : 0; ?>%'></div>
                    </div>
                </div>

                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='fas fa-wind'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Vent</span>
                        <span class='detail-value'><?php echo htmlspecialchars($windSpeed, ENT_QUOTES); ?> m/s</span>
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
                        <span class='detail-value'><?php echo htmlspecialchars($temp, ENT_QUOTES); ?>°C</span>
                    </div>
                    <div class='temp-gauge'>
                        <div class='gauge-fill' style='height: <?php echo is_numeric($temp) ? max(0, min(100, ($temp + 20) * 2)) : 50; ?>%'></div>
                    </div>
                </div>

                <div class='detail-card'>
                    <div class='detail-icon'>
                        <i class='<?php echo htmlspecialchars($conditionIcon, ENT_QUOTES); ?>'></i>
                    </div>
                    <div class='detail-content'>
                        <span class='detail-label'>Conditions</span>
                        <span class='detail-value'><?php echo htmlspecialchars(ucfirst((string)$weatherDescription), ENT_QUOTES); ?></span>
                    </div>
                </div>
            </div>
        </main>

        <footer class='footer'>
            <div class='city-showcase'>
                <img src='images/city.jpg' alt='<?php echo htmlspecialchars($city, ENT_QUOTES); ?>' class='city-image'>
                <div class='city-info'>
                    <h3><?php echo htmlspecialchars($city, ENT_QUOTES); ?></h3>
                    <p>Perle du Bassin d'Arcachon</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.current-weather-card, .detail-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

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