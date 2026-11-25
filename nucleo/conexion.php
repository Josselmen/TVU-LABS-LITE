<?php

function bd(): PDO
{
    static $conexion = null;

    if ($conexion === null) {
        $c = configuracion('base_datos');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['puerto'], $c['nombre']);

        $conexion = new PDO($dsn, $c['usuario'], $c['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $conexion;
}
