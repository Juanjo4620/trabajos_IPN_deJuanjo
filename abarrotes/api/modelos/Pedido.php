<?php 
require_once __DIR__ . '/../configuracion/conexion_bd.php';

class Pedido {

  /**
   * Crea un pedido para un usuario con items: [{producto_id, cantidad}]
   * - Agrupa items por producto
   * - Verifica existencias (con FOR UPDATE)
   * - Calcula totales y descuenta existencias
   * - Todo en transacción
   * @return int ID del pedido creado
   */
  public static function crear(int $usuarioId, array $items, ?string $direccionEnvio = null): int {
    // Normalizar + agrupar por producto_id
    $agrupados = [];
    foreach ($items as $it) {
      $pid = (int)($it['producto_id'] ?? 0);
      $cant = (int)($it['cantidad'] ?? 0);
      if ($pid > 0 && $cant > 0) {
        $agrupados[$pid] = ($agrupados[$pid] ?? 0) + $cant;
      }
    }
    if (!$agrupados) {
      throw new InvalidArgumentException('El pedido no tiene artículos válidos');
    }
    if ($direccionEnvio !== null && strlen($direccionEnvio) > 255) {
      throw new InvalidArgumentException('La dirección de envío supera 255 caracteres');
    }

    $pdo = BaseDatos::obtenerConexion();
    $pdo->beginTransaction();

    try {
      // Crear encabezado del pedido (total=0 por ahora)
      $stmt = $pdo->prepare('INSERT INTO pedidos (usuario_id, total, estado, direccion_envio) VALUES (?,?,?,?)');
      $stmt->execute([$usuarioId, 0, 'pendiente', $direccionEnvio]);
      $pedidoId = (int)$pdo->lastInsertId();

      $total = 0;

      // Preparados reusables
      $sel = $pdo->prepare('SELECT id, nombre, precio, existencias FROM productos WHERE id = ? FOR UPDATE');
      $upd = $pdo->prepare('UPDATE productos SET existencias = existencias - ? WHERE id = ?');
      $insDet = $pdo->prepare('INSERT INTO pedidos_detalle (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)');

      foreach ($agrupados as $productoId => $cantidad) {
        $sel->execute([$productoId]);
        $p = $sel->fetch();
        if (!$p) {
          throw new RuntimeException('Producto no encontrado: ' . $productoId);
        }
        if ((int)$p['existencias'] < $cantidad) {
          throw new RuntimeException('Existencias insuficientes para: ' . $p['nombre']);
        }

        $precio = (float)$p['precio'];
        $subtotal = $precio * $cantidad;
        $total += $subtotal;

        // Insertar detalle
        $insDet->execute([$pedidoId, $productoId, $cantidad, $precio, $subtotal]);

        // Descontar existencias
        $upd->execute([$cantidad, $productoId]);
      }

      // Actualizar total del pedido
      $pdo->prepare('UPDATE pedidos SET total = ? WHERE id = ?')->execute([$total, $pedidoId]);

      $pdo->commit();
      return $pedidoId;

    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  /** Listar pedidos del usuario autenticado */
  public static function listarPorUsuario(int $usuarioId): array {
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY creado_en DESC');
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
  }

  /** Listar todos los pedidos (solo tiendero) */
  public static function listarTodos(): array {
    $pdo = BaseDatos::obtenerConexion();
    $sql = 'SELECT p.*, u.nombre AS usuario_nombre, u.correo AS usuario_correo
              FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id
             ORDER BY p.creado_en DESC';
    return $pdo->query($sql)->fetchAll();
  }

  /**
   * Obtener un pedido con sus items; restringe acceso si no es dueño (a menos que sea tiendero)
   */
  public static function obtener(int $pedidoId, int $usuarioId, bool $esTiendero): ?array {
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    if (!$pedido) return null;
    if (!$esTiendero && (int)$pedido['usuario_id'] !== $usuarioId) return null;

    $det = $pdo->prepare('
      SELECT d.*, p.nombre AS producto_nombre
        FROM pedidos_detalle d
        JOIN productos p ON p.id = d.producto_id
       WHERE d.pedido_id = ?
    ');
    $det->execute([$pedidoId]);
    $pedido['items'] = $det->fetchAll();

    return $pedido;
  }
}
