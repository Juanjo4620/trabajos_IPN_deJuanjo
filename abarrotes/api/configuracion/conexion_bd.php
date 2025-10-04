<?php
class BaseDatos {
  public static function obtenerConexion(): PDO {
    $host = '127.0.0.1';
    $bd   = 'abarrotes_bd'; // nombre en español como en esquema.sql
    $usuario = 'root';      // cámbialo si tu MySQL usa otro usuario
    $contrasena = '';

    $dsn = "mysql:host=$host;dbname=$bd;charset=utf8mb4";

    $opciones = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, $usuario, $contrasena, $opciones);
  }
}
