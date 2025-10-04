<?php
require_once __DIR__ . '/../utilidades/autenticacion.php';
require_once __DIR__ . '/../modelos/Pedido.php';

class ControladorPedido {

  // POST /api/pedidos
  // body: { direccion_envio?: string, items: [{producto_id, cantidad}, ...] }
  public static function crear() {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
      return;
    }

    if (($usuario['rol'] ?? '') !== 'comprador') {
      http_response_code(403);
      echo json_encode(['error' => true, 'mensaje' => 'Solo el rol comprador puede realizar pedidos']);
      return;
    }

    $entrada = json_decode(file_get_contents('php://input'), true);
    if ($entrada === null && json_last_error() !== JSON_ERROR_NONE) {
      http_response_code(400);
      echo json_encode(['error' => true, 'mensaje' => 'JSON inválido']);
      return;
    }
    $entrada = $entrada ?? $_POST;

    $items = $entrada['items'] ?? null;
    if (!is_array($items)) {
      http_response_code(400);
      echo json_encode(['error' => true, 'mensaje' => 'El campo "items" debe ser un arreglo']);
      return;
    }

    $direccion = isset($entrada['direccion_envio']) ? trim((string)$entrada['direccion_envio']) : null;
    if ($direccion !== null && mb_strlen($direccion) > 255) {
      http_response_code(400);
      echo json_encode(['error' => true, 'mensaje' => 'La dirección de envío supera 255 caracteres']);
      return;
    }

    try {
      $pedidoId = Pedido::crear((int)$usuario['id'], $items, $direccion);
      http_response_code(201);
      echo json_encode(['mensaje' => 'Pedido creado', 'pedido_id' => $pedidoId]);
    } catch (InvalidArgumentException $e) {
      // errores de forma/validación
      http_response_code(400);
      echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
    } catch (RuntimeException $e) {
      // reglas de negocio (p.ej. existencias insuficientes)
      http_response_code(422);
      echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['error' => true, 'mensaje' => 'Error interno']);
    }
  }

  // GET /api/pedidos   (comprador: sus pedidos; tiendero: ?todos=1/true para ver todos)
  public static function index() {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error'=>true,'mensaje'=>'No autorizado']);
      return;
    }

    $esTiendero = (($usuario['rol'] ?? '') === 'tiendero');
    // Acepta ?todos=1, ?todos=true, ?todos=on
    $todos = $esTiendero && filter_var($_GET['todos'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $lista = $todos ? Pedido::listarTodos() : Pedido::listarPorUsuario((int)$usuario['id']);
    echo json_encode($lista);
  }

  // GET /api/pedidos/{id}
  public static function mostrar($id) {
    $usuario = Autenticacion::usuarioDesdeSolicitud();
    if (!$usuario) {
      http_response_code(401);
      echo json_encode(['error'=>true,'mensaje'=>'No autorizado']);
      return;
    }

    $pedidoId = (int)$id;
    if ($pedidoId <= 0) {
      http_response_code(400);
      echo json_encode(['error'=>true,'mensaje'=>'ID de pedido inválido']);
      return;
    }

    $esTiendero = (($usuario['rol'] ?? '') === 'tiendero');
    $pedido = Pedido::obtener($pedidoId, (int)$usuario['id'], $esTiendero);
    if (!$pedido) {
      http_response_code(404);
      echo json_encode(['error'=>true,'mensaje'=>'Pedido no encontrado']);
      return;
    }

    echo json_encode($pedido);
  }
}
