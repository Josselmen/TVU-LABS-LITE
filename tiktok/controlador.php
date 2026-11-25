<?php

require_once __DIR__ . '/analisis.php';
require_once __DIR__ . '/modelo.php';
require_once __DIR__ . '/historial.php';
require_once __DIR__ . '/actualizador.php';

class Tiktok
{
    /** El filtro de excepciones (one-hit) está activado salvo que se pida lo contrario. */
    private static function opciones(): array
    {
        return [
            'ignorar_excepciones' => ($_GET['excepciones'] ?? 'ignorar') !== 'todos',
            'umbral' => Analisis::umbralValido($_GET['umbral'] ?? null),
        ];
    }

    /** Datos que necesitan todas las pantallas de análisis (cabecera, botón de recolección y filtro). */
    private static function datosComunes(string $seccion, array $videos, array $opciones): array
    {
        $excepciones = Analisis::detectarExcepciones($videos, $opciones['umbral']);

        return [
            'seccion' => $seccion,
            'opciones' => $opciones,
            'umbrales' => Analisis::UMBRALES_EXCEPCION,
            'hay_videos' => count($videos) > 0,
            'excepciones_info' => ['cantidad' => count($excepciones['lista']), 'limite' => $excepciones['limite']],
            'actualizacion' => Actualizador::estado(),
            'recoleccion' => Actualizador::estadoRecoleccion(),
            'ultima_actualizacion' => Actualizador::ultimaCompletada(),
        ];
    }

    private static function pantalla(string $vista, string $seccion, string $titulo, array $extra = []): void
    {
        $opciones = self::opciones();
        $todos = VideosModelo::todos();
        $resumen = Analisis::resumen($todos, $opciones);

        pagina(__DIR__ . '/vistas/' . $vista . '.php', array_merge(
            self::datosComunes($seccion, $todos, $opciones),
            ['resumen' => $resumen, 'todos' => $todos, 'dias' => Analisis::DIAS],
            $extra
        ), $titulo);
    }

    public static function panel(): void
    {
        self::pantalla('panel', 'panel', 'TikTok');
    }

    public static function rendimiento(): void
    {
        self::pantalla('rendimiento', 'rendimiento', 'Rendimiento · TikTok');
    }

    public static function horarios(): void
    {
        self::pantalla('horarios', 'horarios', 'Horarios · TikTok');
    }

    public static function contenido(): void
    {
        self::pantalla('contenido', 'contenido', 'Contenido · TikTok');
    }

    public static function estrategia(): void
    {
        $texto = mb_substr(trim((string)($_GET['texto'] ?? '')), 0, 600);

        $opciones = self::opciones();
        $todos = VideosModelo::todos();
        $resumen = Analisis::resumen($todos, $opciones);

        $recomendacion = null;
        if ($texto !== '' && !empty($resumen['hay_datos'])) {
            $recomendacion = Estrategia::recomendarHashtags($texto, $resumen['videos_analizados'], (float)$resumen['totales']['vistas_mediana']);
        }

        pagina(__DIR__ . '/vistas/estrategia.php', array_merge(
            self::datosComunes('estrategia', $todos, $opciones),
            ['resumen' => $resumen, 'texto' => $texto, 'recomendacion' => $recomendacion]
        ), 'Estrategia · TikTok');
    }

    public static function evolucion(): void
    {
        Historial::asegurarTabla();

        $todos = VideosModelo::todos();
        $porId = [];
        foreach ($todos as $v) {
            $porId[$v['id']] = $v;
        }

        $crecimiento = array_map(function ($f) use ($porId) {
            $f['video'] = $porId[$f['video_id']] ?? null;
            return $f;
        }, Historial::crecimiento(10));

        $elegido = preg_match('/^\d{10,25}$/', (string)($_GET['video'] ?? '')) ? (string)$_GET['video'] : ($crecimiento[0]['video_id'] ?? '');
        $opciones = self::opciones();

        pagina(__DIR__ . '/vistas/evolucion.php', array_merge(
            self::datosComunes('evolucion', $todos, $opciones),
            [
                'historial' => Historial::resumen(),
                'crecimiento' => array_filter($crecimiento, fn($f) => $f['video'] !== null),
                'video_elegido' => $porId[$elegido] ?? null,
                'serie' => $elegido !== '' ? Historial::serie($elegido) : [],
            ]
        ), 'Evolución · TikTok');
    }

    public static function videos(): void
    {
        $filtros = [
            'q' => (string)($_GET['q'] ?? ''),
            'anio' => (string)($_GET['anio'] ?? ''),
            'categoria' => (string)($_GET['categoria'] ?? ''),
            'orden' => (string)($_GET['orden'] ?? 'fecha'),
            'dir' => (string)($_GET['dir'] ?? 'desc'),
            'pagina' => (int)($_GET['pagina'] ?? 1),
            'por_pagina' => (int)($_GET['por_pagina'] ?? 25),
            'excepciones' => (string)($_GET['excepciones'] ?? 'todos'),
            'umbral' => Analisis::umbralValido($_GET['umbral'] ?? null),
        ];

        $todos = VideosModelo::todos();
        $opciones = ['ignorar_excepciones' => true, 'umbral' => $filtros['umbral']];

        pagina(__DIR__ . '/vistas/videos.php', array_merge(
            self::datosComunes('videos', $todos, $opciones),
            ['filtros' => $filtros, 'datos' => Analisis::explorar($todos, $filtros), 'hay_datos' => count($todos) > 0]
        ), 'Videos de TikTok');
    }

    public static function actualizar(): void
    {
        $resultado = Actualizador::iniciar(usuario_actual()['id'] ?? null);

        if (!$resultado['exito']) {
            avisar('error', $resultado['error']);
        }

        redirigir('tiktok');
    }

    public static function estado(): void
    {
        $estado = Actualizador::estado();
        $recoleccion = Actualizador::estadoRecoleccion();

        responder_json([
            'estado' => $estado['estado'],
            'mensaje' => $estado['mensaje'],
            'recuperados' => $recoleccion['recuperados'],
            'faltan' => $recoleccion['faltan'],
        ]);
    }
}
