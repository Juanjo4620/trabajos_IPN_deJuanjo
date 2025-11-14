<?php
require_once __DIR__ . '/../modelos/contacto.php';

class ControladorContacto {
  // POST /api/contacto
  public static function crear() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
    $correo = (string)($data['correo'] ?? '');
    $comentarios = (string)($data['comentarios'] ?? '');
    try {
      $id = Contacto::guardar($correo, $comentarios);
      http_response_code(201);
      echo json_encode(['mensaje'=>'Contacto registrado','id'=>$id]);
    } catch (InvalidArgumentException $e) {
      http_response_code(400); echo json_encode(['error'=>true,'mensaje'=>$e->getMessage()]);
    } catch (Throwable $e) {
      http_response_code(500); echo json_encode(['error'=>true,'mensaje'=>'Error interno']);
    }
  }
}
