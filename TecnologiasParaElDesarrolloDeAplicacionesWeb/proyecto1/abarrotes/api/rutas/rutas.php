<?php
require_once __DIR__ . '/../controladores/ControladorUsuario.php';
require_once __DIR__ . '/../controladores/ControladorProducto.php';

/**
 * Retorna [controlador, metodo, params] si hay coincidencia; null si no.
 * OJO: si tu index.php llamaba a matchRoute(), cámbialo a coincidirRuta().
 */
function coincidirRuta(string $metodo, string $ruta): ?array {
  $rutas = [
    // ---- Usuarios ----
    ['POST',  '#^/api/usuarios/registro$#',       ['ControladorUsuario', 'registrar']],
    ['POST',  '#^/api/usuarios/inicio-sesion$#',  ['ControladorUsuario', 'iniciarSesion']],
    ['GET',   '#^/api/usuarios/perfil$#',         ['ControladorUsuario', 'perfil']],

    // ---- Productos ----
    ['GET',    '#^/api/productos$#',               ['ControladorProducto', 'index']],
    ['POST',   '#^/api/productos$#',               ['ControladorProducto', 'crear']],
    ['GET',    '#^/api/productos/(\d+)$#',         ['ControladorProducto', 'mostrar']],
    ['PUT',    '#^/api/productos/(\d+)$#',         ['ControladorProducto', 'actualizar']],
    ['DELETE', '#^/api/productos/(\d+)$#',         ['ControladorProducto', 'eliminar']],
  ];

  foreach ($rutas as [$m, $regex, $handler]) {
    if ($m === $metodo && preg_match($regex, $ruta, $mch)) {
      array_shift($mch); // quita el match completo
      return [$handler[0], $handler[1], $mch];
    }
  }
  return null;
}
