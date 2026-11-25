CREATE DATABASE IF NOT EXISTS tvumsa_tiktok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tvumsa_tiktok;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
    video_id VARCHAR(25) NOT NULL PRIMARY KEY,
    url VARCHAR(255) NULL,
    publicado_en DATETIME NULL,
    descripcion TEXT NULL,
    hashtags TEXT NULL,
    duracion INT UNSIGNED NULL,
    vistas INT UNSIGNED NULL,
    likes INT UNSIGNED NULL,
    comentarios INT UNSIGNED NULL,
    compartidos INT UNSIGNED NULL,
    guardados INT UNSIGNED NULL,
    portada VARCHAR(1000) NULL,
    fijado TINYINT(1) NOT NULL DEFAULT 0,
    sonido VARCHAR(255) NULL,
    sonido_original TINYINT(1) NOT NULL DEFAULT 0,
    cuenta VARCHAR(60) NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_videos_publicado (publicado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actualizaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inicio DATETIME NOT NULL,
    fin DATETIME NULL,
    estado VARCHAR(20) NOT NULL,
    etapa VARCHAR(30) NOT NULL DEFAULT '',
    mensaje VARCHAR(500) NOT NULL DEFAULT '',
    latido DATETIME NOT NULL,
    videos_total INT UNSIGNED NULL,
    videos_nuevos INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metricas_historial (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(25) NOT NULL,
    capturado_en DATETIME NOT NULL,
    vistas INT UNSIGNED NULL,
    likes INT UNSIGNED NULL,
    comentarios INT UNSIGNED NULL,
    compartidos INT UNSIGNED NULL,
    guardados INT UNSIGNED NULL,
    INDEX idx_historial_video (video_id, capturado_en),
    INDEX idx_historial_fecha (capturado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
