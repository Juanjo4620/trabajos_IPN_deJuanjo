<?php
require_once __DIR__ . '/../configuracion/conexionBd.php';

class Contacto {
  public static function guardar(string $correo, string $comentarios): int {
    $correo = trim($correo);
    $comentarios = trim($comentarios);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Correo inválido');
    }
    if ($comentarios === '') {
      throw new InvalidArgumentException('Comentarios requeridos');
    }
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('INSERT INTO contactos (correo, comentarios) VALUES (?,?)');
    $stmt->execute([$correo, $comentarios]);
    return (int)$pdo->lastInsertId();
  }
}
