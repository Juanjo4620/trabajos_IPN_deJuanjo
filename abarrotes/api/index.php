<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Respuesta rápida a preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . '/rutas/rutas.php';

// Soporte para override de método vía _method / _metodo en formularios POST
$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo === 'POST') {
  if (isset($_POST['_method']))  { $metodo = strtoupper($_POST['_method']); }
  if (isset($_POST['_metodo']))  { $metodo = strtoupper($_POST['_metodo']); } // compat español
}

// === Normalización del path para que no incluya la carpeta del proyecto ===
// Ej: si la URL es /abarrotes/api/productos, recortamos "/abarrotes" para que el ruteo vea "/api/productos".
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Directorio del script que está ejecutándose (normalizado con /)
$dirScript = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // p.ej. "/abarrotes/api"

// Quitamos el sufijo "/api" para obtener la base del proyecto (carpeta raíz)
$baseProyecto = rtrim(preg_replace('#/api/?$#', '', $dirScript), '/'); // p.ej. "/abarrotes" o "" si está en la raíz

// Si la URL empieza con la base del proyecto, la recortamos
if ($baseProyecto !== '' && strpos($uri, $baseProyecto) === 0) {
  $uri = substr($uri, strlen($baseProyecto)); // "/abarrotes/api/productos" -> "/api/productos"
  if ($uri === '' || $uri === false) { $uri = '/'; }
}

// Ruteo
$ruta = coincidirRuta($metodo, $uri);

if (!$ruta) {
  http_response_code(404);
  echo json_encode(['error' => true, 'mensaje' => 'Ruta no encontrada', 'metodo' => $metodo, 'ruta' => $uri]);
  exit;
}

[$controlador, $accion, $parametros] = $ruta;

if (!class_exists($controlador) || !method_exists($controlador, $accion)) {
  http_response_code(500);
  echo json_encode(['error' => true, 'mensaje' => 'Controlador o accion invalida']);
  exit;
}

call_user_func_array([$controlador, $accion], $parametros);
