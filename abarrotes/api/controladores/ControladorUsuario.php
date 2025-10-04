<?php
require_once __DIR__ . '/../modelos/Usuario.php';
require_once __DIR__ . '/../utilidades/autenticacion.php';

class ControladorUsuario {

  // POST /api/usuarios/registro
  public static function registrar() {
    $entrada = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nombre     = trim($entrada['nombre']  ?? '');
    $correo     = trim($entrada['correo']  ?? '');
    $contrasena = (string)($entrada['contrasena'] ?? '');
    $rol        = $entrada['rol'] ?? 'comprador'; // opcional

    if (!$nombre || !$correo || !$contrasena) {
      http_response_code(422);
      echo json_encode(['error' => true, 'mensaje' => 'nombre, correo y contrasena son requeridos']);
      return;
    }

    if (Usuario::buscarPorCorreo($correo)) {
      http_response_code(409);
      echo json_encode(['error' => true, 'mensaje' => 'El correo ya está registrado']);
      return;
    }

    $id = Usuario::crear($nombre, $correo, $contrasena, $rol);
    $token = Autenticacion::emitirToken($id);

    echo json_encode([
      'mensaje' => 'Usuario creado',
      'token'   => $token,
      'rol'     => $rol
    ]);
  }

  // POST /api/usuarios/inicio-sesion
  public static function iniciarSesion() {
    $entrada = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $correo     = trim($entrada['correo'] ?? '');
    $contrasena = (string)($entrada['contrasena'] ?? '');

    $usuario = Usuario::buscarPorCorreo($correo);
    if (!$usuario || !password_verify($contrasena, $usuario['contrasena_hash'])) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'Credenciales inválidas']);
      return;
    }

    $token = Autenticacion::emitirToken((int)$usuario['id']);
    echo json_encode([
      'token' => $token,
      'rol'   => $usuario['rol'] ?? 'comprador'
    ]);
  }

  // GET /api/usuarios/perfil
  public static function perfil() {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
      return;
    }
    // $usuario ya debería incluir id, nombre, correo, rol, creado_en
    echo json_encode(['usuario' => $usuario]);
  }
}
