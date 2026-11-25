<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('RAIZ', dirname(__DIR__));

require RAIZ . '/nucleo/ayudas.php';
require RAIZ . '/nucleo/conexion.php';
require RAIZ . '/tiktok/modelo.php';
require RAIZ . '/tiktok/actualizador.php';

configuracion();

$id = isset($argv[1]) ? (int)$argv[1] : 0;
if ($id <= 0) {
    fwrite(STDERR, "Uso: php tiktok/ejecutor.php ID_ACTUALIZACION\n");
    exit(1);
}

$resultado = Actualizador::ejecutar($id);
echo $resultado['estado'] . ': ' . $resultado['mensaje'] . "\n";
exit($resultado['estado'] === 'completado' ? 0 : 1);
