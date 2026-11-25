<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('RAIZ', dirname(__DIR__));

require RAIZ . '/nucleo/ayudas.php';
require RAIZ . '/nucleo/conexion.php';
require RAIZ . '/tiktok/modelo.php';

configuracion();

try {
    $pdo = bd();
} catch (PDOException $e) {
    $c = configuracion('base_datos');
    fwrite(STDERR, sprintf("No se pudo conectar a la base «%s» (puerto %d). Enciende MySQL en XAMPP y ejecuta antes base_datos/crear_base_datos.sql\n", $c['nombre'], $c['puerto']));
    exit(1);
}

$existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = :correo');
$existe->execute(['correo' => 'admin@tvumsa.bo']);

if ((int)$existe->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO usuarios (nombre, correo, clave) VALUES (:nombre, :correo, :clave)')->execute([
        'nombre' => 'Administrador',
        'correo' => 'admin@tvumsa.bo',
        'clave' => password_hash('admin123', PASSWORD_DEFAULT),
    ]);
    echo "Usuario creado: admin@tvumsa.bo / admin123 (cámbiala después de ingresar)\n";
} else {
    echo "El usuario admin@tvumsa.bo ya existía.\n";
}

$resultado = VideosModelo::importar();

if (!$resultado['exito']) {
    fwrite(STDERR, $resultado['error'] . "\n");
    exit(1);
}

printf("Videos guardados: %d (nuevos: %d)\n", $resultado['total'], $resultado['nuevos']);
