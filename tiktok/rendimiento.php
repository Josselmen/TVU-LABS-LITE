<?php

/**
 * Rendimiento de las publicaciones: récords, mejores y peores, franjas del día
 * y detección de publicaciones fuera de lo habitual.
 */
class Rendimiento
{
    /** Un video con menos horas que esto todavía está sumando vistas: no se compara. */
    public const HORAS_MADURACION = 48;
    /** Para los rankings de peores, el video debe tener al menos estos días. */
    public const DIAS_RANKING = 7;
    public const VISTAS_MINIMAS_INTERACCION = 500;

    public const FRANJAS = [
        ['clave' => 'madrugada', 'etiqueta' => 'Madrugada', 'rango' => '00:00 a 05:59', 'desde' => 0, 'hasta' => 5],
        ['clave' => 'manana', 'etiqueta' => 'Mañana', 'rango' => '06:00 a 11:59', 'desde' => 6, 'hasta' => 11],
        ['clave' => 'tarde', 'etiqueta' => 'Tarde', 'rango' => '12:00 a 17:59', 'desde' => 12, 'hasta' => 17],
        ['clave' => 'noche', 'etiqueta' => 'Noche', 'rango' => '18:00 a 23:59', 'desde' => 18, 'hasta' => 23],
    ];

    public static function analizar(array $videos, float $medianaGlobal): array
    {
        $conVistas = array_values(array_filter($videos, fn($v) => $v['vistas'] !== null));
        $n = count($conVistas);
        $referencia = $videos ? max(array_column($videos, 'ts')) : 0;
        $suma = fn(string $campo) => array_sum(array_map(fn($v) => (int)$v[$campo], $conVistas));

        $promedios = [
            'vistas' => $n > 0 ? $suma('vistas') / $n : null,
            'interacciones' => $n > 0 ? $suma('interacciones') / $n : null,
            'likes' => $n > 0 ? $suma('likes') / $n : null,
            'comentarios' => $n > 0 ? $suma('comentarios') / $n : null,
            'compartidos' => $n > 0 ? $suma('compartidos') / $n : null,
            'guardados' => $n > 0 ? $suma('guardados') / $n : null,
        ];

        $maduros = array_values(array_filter($conVistas, fn($v) => $referencia - $v['ts'] >= self::DIAS_RANKING * 86400));
        $peoresVistas = $maduros;
        usort($peoresVistas, fn($a, $b) => $a['vistas'] <=> $b['vistas']);

        $peoresInteraccion = array_values(array_filter($maduros, fn($v) => $v['engagement'] !== null && $v['vistas'] >= self::VISTAS_MINIMAS_INTERACCION));
        usort($peoresInteraccion, fn($a, $b) => $a['engagement'] <=> $b['engagement']);

        return [
            'promedios' => $promedios,
            'records' => self::records($conVistas, $medianaGlobal),
            'peores_vistas' => self::enriquecer(array_slice($peoresVistas, 0, 10), $medianaGlobal),
            'peores_interaccion' => self::enriquecer(array_slice($peoresInteraccion, 0, 10), $medianaGlobal),
            'dias_ranking' => self::DIAS_RANKING,
        ];
    }

    private static function enriquecer(array $lista, float $mediana): array
    {
        return array_map(function ($v) use ($mediana) {
            $v['veces_mediana'] = ($v['vistas'] !== null && $mediana > 0) ? $v['vistas'] / $mediana : null;
            return $v;
        }, $lista);
    }

    /** El video que lidera cada métrica. «Interacción» exige una base mínima de vistas. */
    private static function records(array $conVistas, float $mediana): array
    {
        $defs = [
            ['clave' => 'vistas', 'titulo' => 'Más vistas', 'campo' => 'vistas', 'formato' => 'entero'],
            ['clave' => 'engagement', 'titulo' => 'Mayor interacción', 'campo' => 'engagement', 'formato' => 'porcentaje', 'min_vistas' => self::VISTAS_MINIMAS_INTERACCION],
            ['clave' => 'compartidos', 'titulo' => 'Más compartidos', 'campo' => 'compartidos', 'formato' => 'entero'],
            ['clave' => 'comentarios', 'titulo' => 'Más comentarios', 'campo' => 'comentarios', 'formato' => 'entero'],
            ['clave' => 'guardados', 'titulo' => 'Más guardados', 'campo' => 'guardados', 'formato' => 'entero'],
            ['clave' => 'likes', 'titulo' => 'Más me gusta', 'campo' => 'likes', 'formato' => 'entero'],
        ];

        $salida = [];
        foreach ($defs as $d) {
            $campo = $d['campo'];
            $minimo = $d['min_vistas'] ?? 0;
            $validos = array_values(array_filter($conVistas, fn($v) => $v[$campo] !== null && $v['vistas'] >= $minimo));
            if (!$validos) {
                continue;
            }
            usort($validos, fn($a, $b) => $b[$campo] <=> $a[$campo]);
            $video = self::enriquecer([$validos[0]], $mediana)[0];
            $salida[] = $d + ['video' => $video, 'valor' => $video[$campo]];
        }
        return $salida;
    }

    // ------------------------------------------------------------------ Franjas del día

    public static function franjasDia(array $videos): array
    {
        $franjas = [];
        foreach (self::FRANJAS as $f) {
            $grupo = array_values(array_filter($videos, fn($v) => $v['hora'] !== null && $v['hora'] >= $f['desde'] && $v['hora'] <= $f['hasta']));
            $franjas[] = $f + Analisis::conPromedios(Analisis::estadisticas($grupo)) + ['confiable' => count($grupo) >= Analisis::MINIMO_MUESTRA];
        }

        $confiables = array_filter($franjas, fn($f) => $f['confiable'] && $f['mediana'] !== null);
        $mejorVistas = null;
        $mejorInteraccion = null;
        if ($confiables) {
            $porVistas = $confiables;
            usort($porVistas, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
            $mejorVistas = $porVistas[0];
            $porInteraccion = array_filter($confiables, fn($f) => $f['engagement'] !== null);
            usort($porInteraccion, fn($a, $b) => $b['engagement'] <=> $a['engagement']);
            $mejorInteraccion = $porInteraccion[0] ?? null;
        }

        return [
            'franjas' => $franjas,
            'mejor_vistas' => $mejorVistas,
            'mejor_interaccion' => $mejorInteraccion,
        ];
    }

    // ------------------------------------------------------------------ Publicaciones extraordinarias

    public static function percentil(array $ordenados, float $p): float
    {
        $n = count($ordenados);
        $pos = ($n - 1) * $p;
        $bajo = (int)floor($pos);
        $alto = (int)ceil($pos);
        return $ordenados[$bajo] + ($ordenados[$alto] - $ordenados[$bajo]) * ($pos - $bajo);
    }

    /** Límite superior del método IQR: Q3 + k·(Q3 − Q1). Devuelve null si hay pocos datos. */
    public static function limiteIqr(array $valores, float $k = 1.5): ?array
    {
        $valores = array_values(array_filter($valores, fn($v) => $v !== null));
        if (count($valores) < Analisis::MINIMO_MUESTRA * 2) {
            return null;
        }
        sort($valores);
        $q1 = self::percentil($valores, 0.25);
        $q3 = self::percentil($valores, 0.75);
        return ['q1' => $q1, 'q3' => $q3, 'limite' => $q3 + $k * ($q3 - $q1)];
    }

    /**
     * Publicaciones que se alejan del comportamiento habitual de la cuenta (método IQR).
     * Solo se comparan los videos con más de HORAS_MADURACION horas: los más nuevos aún están sumando vistas.
     */
    public static function extraordinarias(array $videos): array
    {
        $vacio = ['hay_datos' => false, 'maduros' => 0, 'en_maduracion' => 0, 'grupos' => []];
        if (!$videos) {
            return $vacio;
        }

        $referencia = max(array_column($videos, 'ts'));
        $conVistas = array_filter($videos, fn($v) => $v['vistas'] !== null);
        $maduros = array_values(array_filter($conVistas, fn($v) => $referencia - $v['ts'] >= self::HORAS_MADURACION * 3600));
        $enMaduracion = count($conVistas) - count($maduros);

        $conBase = array_values(array_filter($maduros, fn($v) => $v['vistas'] >= self::VISTAS_MINIMAS_INTERACCION && $v['engagement'] !== null));
        $limVistas = self::limiteIqr(array_column($maduros, 'vistas'));
        $limInteraccion = self::limiteIqr(array_column($conBase, 'engagement'));
        $tasaCompartidos = fn($v) => $v['compartidos'] / $v['vistas'];
        $limCompartidos = self::limiteIqr(array_map($tasaCompartidos, $conBase));
        $medianaVistas = Analisis::mediana(array_column($maduros, 'vistas')) ?? 0.0;

        $grupos = [];

        if ($limVistas) {
            $grupos['vistas'] = [
                'titulo' => 'Vistas muy por encima de lo habitual',
                'detalle' => 'Superan el límite estadístico de la cuenta (Q3 + 1,5 × IQR).',
                'formato' => 'entero', 'limite' => $limVistas['limite'], 'columna' => 'Vistas',
                'resultado' => self::superan($maduros, fn($v) => $v['vistas'], $limVistas['limite']),
            ];
        }
        if ($limInteraccion) {
            $grupos['interaccion'] = [
                'titulo' => 'Interacción inusualmente alta',
                'detalle' => 'Videos con al menos ' . self::VISTAS_MINIMAS_INTERACCION . ' vistas cuya interacción supera el límite habitual.',
                'formato' => 'porcentaje', 'limite' => $limInteraccion['limite'], 'columna' => 'Interacción',
                'resultado' => self::superan($conBase, fn($v) => $v['engagement'], $limInteraccion['limite']),
            ];
        }
        if ($limCompartidos) {
            $grupos['compartidos'] = [
                'titulo' => 'Se comparten más de lo normal',
                'detalle' => 'Compartidos por cada vista por encima del límite habitual.',
                'formato' => 'porcentaje', 'limite' => $limCompartidos['limite'], 'columna' => 'Compartidos / vistas',
                'resultado' => self::superan(array_filter($conBase, fn($v) => $v['compartidos'] >= 3), $tasaCompartidos, $limCompartidos['limite']),
            ];
        }
        if ($limInteraccion) {
            $pocas = array_filter($conBase, fn($v) => $v['vistas'] <= $medianaVistas);
            $grupos['pocas_vistas'] = [
                'titulo' => 'Mucha interacción con pocas vistas',
                'detalle' => 'Interacción por encima del límite habitual con vistas iguales o menores a la mediana (' . number_format($medianaVistas, 0, ',', '.') . '). Buen contenido que no llegó a más gente.',
                'formato' => 'porcentaje', 'limite' => $limInteraccion['limite'], 'columna' => 'Interacción',
                'resultado' => self::superan($pocas, fn($v) => $v['engagement'], $limInteraccion['limite']),
            ];
        }

        return [
            'hay_datos' => $grupos !== [],
            'maduros' => count($maduros),
            'en_maduracion' => $enMaduracion,
            'horas_maduracion' => self::HORAS_MADURACION,
            'grupos' => $grupos,
        ];
    }

    private static function superan(array $videos, callable $valor, float $limite): array
    {
        $items = [];
        foreach ($videos as $v) {
            $x = $valor($v);
            if ($x > $limite) {
                $items[] = ['video' => $v, 'valor' => $x, 'veces_limite' => $limite > 0 ? $x / $limite : null];
            }
        }
        usort($items, fn($a, $b) => $b['valor'] <=> $a['valor']);
        return ['total' => count($items), 'lista' => array_slice($items, 0, 5)];
    }
}
