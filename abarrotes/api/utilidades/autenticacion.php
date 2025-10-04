<?php
require_once __DIR__ . '/../configuracion/conexion_bd.php';

class Autenticacion {

  /**
   * Emitir un token con TTL (24h por defecto)
   * @return string Token de 64 caracteres (hex)
   */
  public static function emitirToken(int $usuarioId, int $ttlHoras = 24): string {
    $pdo = BaseDatos::obtenerConexion();
    $token = bin2hex(random_bytes(32)); // 64 chars
    $expira = (new DateTime("+{$ttlHoras} hours"))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('
      INSERT INTO tokens_autenticacion (usuario_id, token, expira_en)
      VALUES (?,?,?)
    ');
    $stmt->execute([$usuarioId, $token, $expira]);

    return $token;
  }

  /**
   * Obtiene el usuario autenticado a partir del header:
   * Authorization: Bearer <token>
   * @return ?array { id, nombre, correo, rol } o null si no válido
   */
  public static function usuarioDesdeSolicitud(): ?array {
    // Algunos servidores normalizan a HTTP_AUTHORIZATION
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (preg_match('/Bearer\s+([A-Fa-f0-9]{64})/', $authHeader, $m)) {
      $token = $m[1];
      $pdo = BaseDatos::obtenerConexion();

      $stmt = $pdo->prepare('
        SELECT u.id, u.nombre, u.correo, u.rol
          FROM tokens_autenticacion t
          JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token = ?
           AND t.expira_en > NOW()
         LIMIT 1
      ');
      $stmt->execute([$token]);
      $usuario = $stmt->fetch();

      return $usuario ?: null;
    }

    return null;
  }
}
