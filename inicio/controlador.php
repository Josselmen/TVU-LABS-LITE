<?php

class Inicio
{
    /** Redes que muestra el menú. Solo TikTok tiene análisis por ahora. */
    public const PLATAFORMAS = [
        'tiktok' => [
            'nombre' => 'TikTok',
            'activa' => true,
            'descripcion' => 'Rendimiento, mejores horarios, categorías de contenido, hashtags y un asistente de estrategia.',
        ],
        'youtube' => [
            'nombre' => 'YouTube',
            'activa' => false,
            'descripcion' => 'El análisis del canal de YouTube se agregará próximamente.',
        ],
        'facebook' => [
            'nombre' => 'Facebook',
            'activa' => false,
            'descripcion' => 'El análisis de la página de Facebook se agregará próximamente.',
        ],
    ];

    public static function presentacion(): void
    {
        pagina(RAIZ . '/inicio/vistas/presentacion.php', [
            'plataformas' => self::PLATAFORMAS,
            'con_sesion' => usuario_actual() !== null,
        ], 'Inicio');
    }

    public static function menu(): void
    {
        pagina(RAIZ . '/inicio/vistas/menu.php', ['plataformas' => self::PLATAFORMAS], 'Elige una red');
    }

    public static function plataforma(string $clave): void
    {
        $plataforma = self::PLATAFORMAS[$clave] ?? null;
        if ($plataforma === null) {
            error_pagina(404, 'La página que buscas no existe.');
        }

        pagina(RAIZ . '/inicio/vistas/proximamente.php', ['clave' => $clave, 'plataforma' => $plataforma], $plataforma['nombre']);
    }

    /** Dibujo de cada red (SVG en línea, sin imágenes externas). */
    public static function icono(string $clave): string
    {
        $iconos = [
            'tiktok' => '<svg viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="12" fill="#0a1628"/><path d="M26 11v18.2a5.2 5.2 0 1 1-4.4-5.15v-4.2A9.4 9.4 0 1 0 30.2 29V19.8c1.7 1.3 3.7 2 5.8 2v-4.2c-3.4 0-6-2.7-6-6.6z" fill="#fff"/><path d="M28 11v18.2a5.2 5.2 0 0 1-8.2 4.2 5.2 5.2 0 0 0 8.2-4.2V13.5c1.2 1.6 2.9 2.6 4.8 2.9v-1.8c-2.5-.4-4.5-2-4.8-3.6z" fill="#4a9ede" opacity=".9"/></svg>',
            'youtube' => '<svg viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="12" fill="#dc3545"/><rect x="9" y="14" width="30" height="20" rx="6" fill="#fff"/><path d="M21 19.5v9l8-4.5z" fill="#dc3545"/></svg>',
            'facebook' => '<svg viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="12" fill="#2558a6"/><path d="M26.5 38V25.5h4.2l.7-4.9h-4.9v-3.1c0-1.4.4-2.4 2.4-2.4h2.6V10.8c-.5-.1-2-.2-3.8-.2-3.7 0-6.2 2.2-6.2 6.4v3.6h-4.2v4.9h4.2V38z" fill="#fff"/></svg>',
        ];
        return $iconos[$clave] ?? '';
    }
}
