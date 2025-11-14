<?php
require_once __DIR__ . '/../configuracion/conexionBd.php';

class Usuario {

  // Crear usuario con rol (por defecto 'comprador')
  public static function crear(string $nombre, string $correo, string $contrasena, string $rol = 'comprador'): int {
    $rol = in_array($rol, ['tiendero', 'comprador'], true) ? $rol : 'comprador';
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, correo, contrasena_hash, rol) VALUES (?,?,?,?)');
    $stmt->execute([$nombre, $correo, password_hash($contrasena, PASSWORD_DEFAULT), $rol]);
    return (int)$pdo->lastInsertId();
  }

  // Buscar usuario por correo (para inicio de sesion)
  public static function buscarPorCorreo(string $correo): ?array {
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ? LIMIT 1');
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();
    return $usuario ?: null;
  }

  // Buscar usuario por ID (para /usuarios/perfil)
  public static function buscarPorId(int $id): ?array {
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('SELECT id, nombre, correo, rol, creado_en FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    return $usuario ?: null;
  }
}
