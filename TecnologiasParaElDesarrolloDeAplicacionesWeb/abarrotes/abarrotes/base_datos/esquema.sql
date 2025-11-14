-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS abarrotes_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abarrotes_bd;

-- =======================
-- Usuarios y autenticación
-- =======================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  contrasena_hash VARCHAR(255) NOT NULL,
  rol ENUM('tiendero','comprador') NOT NULL DEFAULT 'comprador',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tokens_autenticacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expira_en DATETIME NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tokens_usuario (usuario_id),
  CONSTRAINT fk_tokens_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========
-- Productos
-- =========
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

-- =======
-- Pedidos
-- =======
CREATE TABLE IF NOT EXISTS pedidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estado ENUM('pendiente','pagado','enviado','cancelado') NOT NULL DEFAULT 'pendiente',
  direccion_envio VARCHAR(255) DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pedidos_usuario (usuario_id, creado_en),
  CONSTRAINT fk_pedidos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Detalle de pedidos (items) + estados por ítem
CREATE TABLE IF NOT EXISTS pedidos_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,

  -- Estado del ítem para cumplir “confirmar recibido / solicitar devolución” por producto
  estado_item ENUM('pendiente','enviado','recibido','devolucion_solicitada','devuelto','cancelado')
    NOT NULL DEFAULT 'pendiente',
  recibido_en DATETIME NULL,
  devolucion_motivo VARCHAR(255) NULL,
  devuelto_en DATETIME NULL,

  INDEX idx_detalle_pedido (pedido_id),
  INDEX idx_detalle_producto (producto_id),
  CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =======================
-- Formulario de Contacto
-- =======================
CREATE TABLE IF NOT EXISTS contactos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  correo VARCHAR(160) NOT NULL,
  comentarios TEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===============
-- Datos de ejemplo
-- ===============
INSERT INTO productos (nombre, descripcion, precio, existencias, categoria) VALUES
('Arroz 1kg', 'Arroz blanco super extra', 28.50, 120, 'Granos'),
('Frijol 1kg', 'Negro, limpio', 36.00, 80, 'Granos'),
('Aceite 900ml', 'Aceite vegetal', 42.90, 60, 'Despensa');
