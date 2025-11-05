-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS abarrotes_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abarrotes_bd;

-- Tabla de usuarios (con rol)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  contrasena_hash VARCHAR(255) NOT NULL,
  rol ENUM('tiendero','comprador') NOT NULL DEFAULT 'comprador',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tokens de autenticacion
CREATE TABLE IF NOT EXISTS tokens_autenticacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expira_en DATETIME NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Productos
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL,
  existencias INT NOT NULL DEFAULT 0,
  categoria VARCHAR(100) DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos de ejemplo
INSERT INTO productos (nombre, descripcion, precio, existencias, categoria) VALUES
('Arroz 1kg', 'Arroz blanco super extra', 28.50, 120, 'Granos'),
('Frijol 1kg', 'Negro, limpio', 36.00, 80, 'Granos'),
('Aceite 900ml', 'Aceite vegetal', 42.90, 60, 'Despensa');
