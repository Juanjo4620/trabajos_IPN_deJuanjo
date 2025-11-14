<?php 
require_once __DIR__ . '/../configuracion/conexionBd.php';

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

    /**
   * Verifica que el usuario pueda operar sobre un pedido (dueño o tiendero).
   * Retorna ['es_tiendero'=>bool, 'pedido'=>array] o lanza.
   */
  private static function assertAccesoPedido(int $pedidoId, int $usuarioId): array {
    $pdo = BaseDatos::obtenerConexion();
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    if (!$pedido) {
      throw new RuntimeException('Pedido no encontrado');
    }
    // El controlador ya sabe si es tiendero; aquí solo devolvemos el pedido
    return ['pedido' => $pedido];
  }

  /**
   * Marca un ITEM del pedido como recibido.
   */
  public static function marcarItemRecibido(int $pedidoId, int $detalleId, int $usuarioId, bool $esTiendero): bool {
    $pdo = BaseDatos::obtenerConexion();
    $pdo->beginTransaction();
    try {
      $info = self::assertAccesoPedido($pedidoId, $usuarioId);
      $pedido = $info['pedido'];
      if (!$esTiendero && (int)$pedido['usuario_id'] !== $usuarioId) {
        throw new RuntimeException('No autorizado');
      }

      // Asegura que el detalle pertenece al pedido y está en estado válido
      $sel = $pdo->prepare('SELECT * FROM pedidos_detalle WHERE id = ? AND pedido_id = ? FOR UPDATE');
      $sel->execute([$detalleId, $pedidoId]);
      $det = $sel->fetch();
      if (!$det) throw new RuntimeException('Detalle no encontrado');
      if ($det['estado_item'] !== 'pendiente' && $det['estado_item'] !== 'enviado') {
        throw new RuntimeException('El item no puede marcarse como recibido en su estado actual');
      }

      $upd = $pdo->prepare('UPDATE pedidos_detalle
                               SET estado_item = "recibido", recibido_en = NOW()
                             WHERE id = ?');
      $upd->execute([$detalleId]);

      $pdo->commit();
      return true;
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Solicita devolución de un ITEM del pedido (con motivo opcional).
   */
  public static function solicitarDevolucionItem(int $pedidoId, int $detalleId, int $usuarioId, bool $esTiendero, ?string $motivo = null): bool {
    if ($motivo !== null && mb_strlen($motivo) > 255) {
      throw new InvalidArgumentException('El motivo de devolución supera 255 caracteres');
    }

    $pdo = BaseDatos::obtenerConexion();
    $pdo->beginTransaction();
    try {
      $info = self::assertAccesoPedido($pedidoId, $usuarioId);
      $pedido = $info['pedido'];
      if (!$esTiendero && (int)$pedido['usuario_id'] !== $usuarioId) {
        throw new RuntimeException('No autorizado');
      }

      $sel = $pdo->prepare('SELECT * FROM pedidos_detalle WHERE id = ? AND pedido_id = ? FOR UPDATE');
      $sel->execute([$detalleId, $pedidoId]);
      $det = $sel->fetch();
      if (!$det) throw new RuntimeException('Detalle no encontrado');

      // Permitimos pedir devolución si está "recibido" o "pendiente/enviado" según política
      if (!in_array($det['estado_item'], ['pendiente','enviado','recibido'], true)) {
        throw new RuntimeException('El item no puede solicitar devolución en su estado actual');
      }

      $upd = $pdo->prepare('UPDATE pedidos_detalle
                               SET estado_item = "devolucion_solicitada",
                                   devolucion_motivo = ?,
                                   devuelto_en = NULL
                             WHERE id = ?');
      $upd->execute([$motivo, $detalleId]);

      $pdo->commit();
      return true;
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }
}
