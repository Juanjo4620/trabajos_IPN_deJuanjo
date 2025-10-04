<?php
require_once __DIR__ . '/../configuracion/conexion_bd.php';

class Producto {
    // Listar productos con filtros opcionales
    public static function listar(array $filtros = []): array {
        $pdo = BaseDatos::obtenerConexion();
        $sql = 'SELECT * FROM productos WHERE 1=1';
        $parametros = [];

        // Búsqueda por nombre o categoría
        if (!empty($filtros['q'])) {
            $sql .= ' AND (nombre LIKE ? OR categoria LIKE ?)';
            $parametros[] = '%' . $filtros['q'] . '%';
            $parametros[] = '%' . $filtros['q'] . '%';
        }

        // Filtros por precio
        if (!empty($filtros['precio_minimo'])) { 
            $sql .= ' AND precio >= ?'; 
            $parametros[] = $filtros['precio_minimo']; 
        }
        if (!empty($filtros['precio_maximo'])) { 
            $sql .= ' AND precio <= ?'; 
            $parametros[] = $filtros['precio_maximo']; 
        }

        $sql .= ' ORDER BY creado_en DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    // Crear un nuevo producto
    public static function crear(array $datos): int {
        $pdo = BaseDatos::obtenerConexion();
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, existencias, categoria) VALUES (?,?,?,?,?)');
        $stmt->execute([
            $datos['nombre'], 
            $datos['descripcion'] ?? null, 
            $datos['precio'], 
            $datos['existencias'] ?? 0, 
            $datos['categoria'] ?? null
        ]);
        return (int)$pdo->lastInsertId();
    }

    // Buscar producto por ID
    public static function buscar(int $id): ?array {
        $pdo = BaseDatos::obtenerConexion();
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    // Actualizar un producto
    public static function actualizar(int $id, array $datos): bool {
        $pdo = BaseDatos::obtenerConexion();
        $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, existencias=?, categoria=? WHERE id=?');
        return $stmt->execute([
            $datos['nombre'], 
            $datos['descripcion'] ?? null, 
            $datos['precio'], 
            $datos['existencias'] ?? 0, 
            $datos['categoria'] ?? null, 
            $id
        ]);
    }

    // Eliminar un producto
    public static function eliminar(int $id): bool {
        $pdo = BaseDatos::obtenerConexion();
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id=?');
        return $stmt->execute([$id]);
    }
}
