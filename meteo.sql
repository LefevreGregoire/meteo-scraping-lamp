-- Créer la base et la table
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