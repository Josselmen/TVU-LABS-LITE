<?php

define('RAIZ', __DIR__);

require RAIZ . '/nucleo/ayudas.php';
require RAIZ . '/nucleo/conexion.php';
require RAIZ . '/acceso/controlador.php';
require RAIZ . '/inicio/controlador.php';
require RAIZ . '/tiktok/controlador.php';

configuracion();
iniciar_sesion();

$rutas = [
    'GET' => [
        '' => [Inicio::class, 'presentacion'],
        'inicio' => [Inicio::class, 'presentacion'],
        'menu' => [Inicio::class, 'menu'],
        'ingresar' => [Acceso::class, 'formulario'],
        'salir' => [Acceso::class, 'salir'],
        'youtube' => fn() => Inicio::plataforma('youtube'),
        'facebook' => fn() => Inicio::plataforma('facebook'),
        'tiktok' => [Tiktok::class, 'panel'],
        'tiktok/rendimiento' => [Tiktok::class, 'rendimiento'],
        'tiktok/horarios' => [Tiktok::class, 'horarios'],
        'tiktok/contenido' => [Tiktok::class, 'contenido'],
        'tiktok/estrategia' => [Tiktok::class, 'estrategia'],
        'tiktok/evolucion' => [Tiktok::class, 'evolucion'],
        'tiktok/videos' => [Tiktok::class, 'videos'],
        'tiktok/estado' => [Tiktok::class, 'estado'],
    ],
    'POST' => [
        'ingresar' => [Acceso::class, 'entrar'],
        'tiktok/actualizar' => [Tiktok::class, 'actualizar'],
    ],
];

$publicas = ['', 'inicio', 'ingresar'];
$ruta = trim((string)($_GET['ruta'] ?? ''), '/');
$metodo = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET';
$accion = $rutas[$metodo][$ruta] ?? null;

if ($accion === null) {
    error_pagina(404, 'La página que buscas no existe.');
}

try {
    if (!in_array($ruta, $publicas, true)) {
        exigir_sesion();
    }

    if ($metodo === 'POST' && !csrf_valido()) {
        avisar('error', 'La sesión expiró. Intenta de nuevo.');
        redirigir($ruta === 'ingresar' ? 'ingresar' : 'menu');
    }

    call_user_func($accion);
} catch (PDOException $e) {
    error_log('Base de datos: ' . $e->getMessage());
    $bd = configuracion('base_datos');
    $detalle = configuracion('depuracion') ? $e->getMessage() : '';
    error_pagina(500, sprintf(
        'No se pudo usar la base de datos «%s» en el puerto %d. Revisa que MySQL esté encendido en XAMPP y que ya ejecutaste base_datos/crear_base_datos.sql. %s',
        $bd['nombre'],
        $bd['puerto'],
        $detalle
    ));
} catch (Throwable $e) {
    error_log('Error: ' . $e->getMessage());
    error_pagina(500, configuracion('depuracion') ? $e->getMessage() : 'Ocurrió un error inesperado.');
}
