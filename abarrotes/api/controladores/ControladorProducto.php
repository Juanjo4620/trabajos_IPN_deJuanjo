<?php
require_once __DIR__ . '/../modelos/Producto.php';
require_once __DIR__ . '/../utilidades/autenticacion.php';

class ControladorProducto {

  // GET /api/productos?q=&precio_minimo=&precio_maximo=
  public static function index() {
    $filtros = [
      'q' => $_GET['q'] ?? null,
      'precio_minimo' => $_GET['precio_minimo'] ?? null,
      'precio_maximo' => $_GET['precio_maximo'] ?? null,
    ];
    echo json_encode(Producto::listar($filtros));
  }

  // GET /api/productos/{id}
  public static function mostrar($id) {
    $producto = Producto::buscar((int)$id);
    if (!$producto) {
      http_response_code(404);
      echo json_encode(['error' => true, 'mensaje' => 'No encontrado']);
      return;
    }
    echo json_encode($producto);
  }

  // POST /api/productos  (solo tiendero)
  public static function crear() {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
      return;
    }
    if (($usuario['rol'] ?? 'comprador') !== 'tiendero') {
      http_response_code(403);
      echo json_encode(['error' => true, 'mensaje' => 'Solo tiendero puede crear']);
      return;
    }

    $entrada = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Validaciones básicas
    if (empty($entrada['nombre']) || !isset($entrada['precio'])) {
      http_response_code(422);
      echo json_encode(['error' => true, 'mensaje' => 'nombre y precio son requeridos']);
      return;
    }

    $id = Producto::crear($entrada);
    http_response_code(201);
    echo json_encode(['mensaje' => 'Creado', 'id' => $id]);
  }

  // PUT /api/productos/{id}  (solo tiendero)
  public static function actualizar($id) {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
      return;
    }
    if (($usuario['rol'] ?? 'comprador') !== 'tiendero') {
      http_response_code(403);
      echo json_encode(['error' => true, 'mensaje' => 'Solo tiendero puede actualizar']);
      return;
    }

    $entrada = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $existente = Producto::buscar((int)$id);
    if (!$existente) {
      http_response_code(404);
      echo json_encode(['error' => true, 'mensaje' => 'No encontrado']);
      return;
    }

    $datos = [
      'nombre'      => $entrada['nombre']      ?? $existente['nombre'],
      'descripcion' => $entrada['descripcion'] ?? $existente['descripcion'],
      'precio'      => array_key_exists('precio', $entrada) ? $entrada['precio'] : $existente['precio'],
      'existencias' => array_key_exists('existencias', $entrada) ? $entrada['existencias'] : $existente['existencias'],
      'categoria'   => $entrada['categoria']   ?? $existente['categoria'],
    ];

    Producto::actualizar((int)$id, $datos);
    echo json_encode(['mensaje' => 'Actualizado']);
  }

  // DELETE /api/productos/{id}  (solo tiendero)
  public static function eliminar($id) {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
      return;
    }
    if (($usuario['rol'] ?? 'comprador') !== 'tiendero') {
      http_response_code(403);
      echo json_encode(['error' => true, 'mensaje' => 'Solo tiendero puede eliminar']);
      return;
    }

    $existente = Producto::buscar((int)$id);
    if (!$existente) {
      http_response_code(404);
      echo json_encode(['error' => true, 'mensaje' => 'No encontrado']);
      return;
    }

    Producto::eliminar((int)$id);
    echo json_encode(['mensaje' => 'Eliminado']);
  }
}
