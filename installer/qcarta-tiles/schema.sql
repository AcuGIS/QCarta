-- Generic map registry for tile/WMS proxy admin UI (MySQL 5.7+ / MariaDB)
CREATE TABLE IF NOT EXISTS maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    qgis_map_path TEXT NOT NULL,
    layers TEXT,
    rendering_mode ENUM('tile','wms') DEFAULT 'tile',
    quality_preset ENUM('performance','balanced','quality') DEFAULT 'balanced',
    image_format ENUM('png','jpeg') DEFAULT 'png',
    transparent TINYINT(1) DEFAULT 1,
    cache_enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
