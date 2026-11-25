<?php

require_once __DIR__ . '/clasificador.php';
require_once __DIR__ . '/rendimiento.php';
require_once __DIR__ . '/estrategia.php';

class Analisis
{
    public const MINIMO_MUESTRA = 5;
    public const UMBRALES_EXCEPCION = [5, 10, 20, 50];
    public const UMBRAL_EXCEPCION_DEFECTO = 20;

    public const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    private const RANGOS_DURACION = [
        ['clave' => '0-15', 'etiqueta' => 'Hasta 15 s', 'min' => 0, 'max' => 15],
        ['clave' => '16-30', 'etiqueta' => '16 a 30 s', 'min' => 16, 'max' => 30],
        ['clave' => '31-60', 'etiqueta' => '31 a 60 s', 'min' => 31, 'max' => 60],
        ['clave' => '61-180', 'etiqueta' => '1 a 3 min', 'min' => 61, 'max' => 180],
        ['clave' => '181+', 'etiqueta' => 'Más de 3 min', 'min' => 181, 'max' => PHP_INT_MAX],
    ];

    private const RANGOS_VISTAS = [
        ['etiqueta' => 'Menos de 1 mil', 'min' => 0, 'max' => 999],
        ['etiqueta' => '1 mil a 5 mil', 'min' => 1000, 'max' => 4999],
        ['etiqueta' => '5 mil a 10 mil', 'min' => 5000, 'max' => 9999],
        ['etiqueta' => '10 mil a 50 mil', 'min' => 10000, 'max' => 49999],
        ['etiqueta' => '50 mil a 100 mil', 'min' => 50000, 'max' => 99999],
        ['etiqueta' => 'Más de 100 mil', 'min' => 100000, 'max' => PHP_INT_MAX],
    ];

    public static function mediana(array $valores): ?float
    {
        $valores = array_values(array_filter($valores, fn($v) => $v !== null));
        $n = count($valores);
        if ($n === 0) {
            return null;
        }
        sort($valores);
        $medio = intdiv($n, 2);
        return $n % 2 === 1 ? (float)$valores[$medio] : ($valores[$medio - 1] + $valores[$medio]) / 2;
    }

    public static function umbralValido($umbral): int
    {
        $umbral = (int)$umbral;
        return in_array($umbral, self::UMBRALES_EXCEPCION, true) ? $umbral : self::UMBRAL_EXCEPCION_DEFECTO;
    }

    public static function detectarExcepciones(array $videos, int $umbral): array
    {
        $mediana = self::mediana(array_column(array_filter($videos, fn($v) => $v['vistas'] !== null), 'vistas')) ?? 0.0;
        $limite = $mediana * $umbral;

        $ids = [];
        $lista = [];
        if ($limite > 0) {
            foreach ($videos as $v) {
                if ($v['vistas'] !== null && $v['vistas'] >= $limite) {
                    $ids[$v['id']] = true;
                    $lista[] = $v + ['veces_mediana' => $v['vistas'] / $mediana];
                }
            }
            usort($lista, fn($a, $b) => $b['vistas'] <=> $a['vistas']);
        }

        return ['umbral' => $umbral, 'mediana' => $mediana, 'limite' => $limite, 'ids' => $ids, 'lista' => $lista];
    }

    public static function resumen(array $todos, array $opciones = []): array
    {
        if (count($todos) === 0) {
            return ['hay_datos' => false, 'total' => 0];
        }

        usort($todos, fn($a, $b) => $b['ts'] <=> $a['ts']);

        $umbral = self::umbralValido($opciones['umbral'] ?? self::UMBRAL_EXCEPCION_DEFECTO);
        $ignorar = !empty($opciones['ignorar_excepciones']);
        $excepciones = self::detectarExcepciones($todos, $umbral);
        $videos = $ignorar
            ? array_values(array_filter($todos, fn($v) => !isset($excepciones['ids'][$v['id']])))
            : $todos;
        $total = count($videos);

        if ($total === 0) {
            return ['hay_datos' => false, 'total' => 0];
        }

        $conVistas = array_values(array_filter($videos, fn($v) => $v['vistas'] !== null));
        $vistas = array_column($conVistas, 'vistas');
        $totalVistas = array_sum($vistas);
        $totalInteracciones = array_sum(array_column($conVistas, 'interacciones'));

        $totales = [
            'videos' => $total,
            'vistas' => $totalVistas,
            'likes' => array_sum(array_map(fn($v) => (int)$v['likes'], $videos)),
            'comentarios' => array_sum(array_map(fn($v) => (int)$v['comentarios'], $videos)),
            'compartidos' => array_sum(array_map(fn($v) => (int)$v['compartidos'], $videos)),
            'guardados' => array_sum(array_map(fn($v) => (int)$v['guardados'], $videos)),
            'interacciones' => $totalInteracciones,
            'engagement' => $totalVistas > 0 ? $totalInteracciones / $totalVistas : null,
            'vistas_promedio' => count($vistas) > 0 ? $totalVistas / count($vistas) : null,
            'vistas_mediana' => self::mediana($vistas),
            'duracion_mediana' => self::mediana(array_column($videos, 'duracion')),
            'primer_video' => self::primeraFecha($videos),
            'ultimo_video' => $videos[0]['fecha'],
            'fijados' => count(array_filter($videos, fn($v) => $v['fijado'])),
        ];

        $medianaGlobal = $totales['vistas_mediana'] ?? 0.0;
        $clasificacion = Clasificador::analizar($videos);

        $resumen = [
            'hay_datos' => true,
            'total' => $total,
            'ignorando_excepciones' => $ignorar,
            'excepciones' => self::describirExcepciones($todos, $excepciones),
            'total_canal' => count($todos),
            'cuenta' => $videos[0]['cuenta'] ?: 'tvu_umsa_lp',
            'nombre_cuenta' => 'TVU UMSA LP',
            'totales' => $totales,
            'comparativa' => self::comparativaReciente($videos),
            'mensual' => self::serieMensual($videos),
            'anual' => self::serieAnual($videos),
            'dias' => self::agruparDias($videos),
            'horas' => self::agruparHoras($videos),
            'mapa_calor' => self::mapaCalor($videos),
            'franjas' => self::mejoresFranjas($videos),
            'duraciones' => self::agruparDuracion($videos),
            'distribucion_vistas' => self::distribucionVistas($videos),
            'hashtags' => self::analizarHashtags($videos, $medianaGlobal),
            'sonidos' => self::analizarSonidos($videos),
            'top_vistas' => self::tomar($videos, 'vistas', 10, $medianaGlobal),
            'top_engagement' => self::topEngagement($videos, $medianaGlobal),
            'recientes' => self::recientes($videos, $medianaGlobal),
            'fijados' => array_values(array_filter($videos, fn($v) => $v['fijado'])),
            'rendimiento' => Rendimiento::analizar($videos, $medianaGlobal),
            'franjas_dia' => Rendimiento::franjasDia($videos),
            'extraordinarias' => Rendimiento::extraordinarias($todos),
            'clasificacion' => $clasificacion,
            'ideas' => Estrategia::ideas($videos, $clasificacion, $medianaGlobal),
            'videos_analizados' => $videos,
        ];

        $resumen['hallazgos'] = self::hallazgos($resumen);

        return $resumen;
    }

    /** Fecha del video más antiguo que sí tiene fecha (un video sin fecha no debe vaciar el rango). */
    private static function primeraFecha(array $videos): string
    {
        $conFecha = array_filter($videos, fn($v) => $v['ts'] > 0);
        return $conFecha ? end($conFecha)['fecha'] : '';
    }

    public static function hallazgos(array $resumen): array
    {
        if (empty($resumen['hay_datos'])) {
            return [];
        }

        $hallazgos = [];

        $dias = array_filter($resumen['dias'], fn($d) => $d['videos'] >= self::MINIMO_MUESTRA && $d['mediana'] !== null);
        if ($dias) {
            usort($dias, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
            $d = $dias[0];
            $hallazgos[] = [
                'titulo' => 'Mejor día para publicar',
                'valor' => $d['nombre'],
                'detalle' => 'Mediana de ' . number_format($d['mediana'], 0, ',', '.') . ' vistas en ' . $d['videos'] . ' videos.',
            ];
        }

        if (!empty($resumen['franjas'])) {
            $f = $resumen['franjas'][0];
            $hallazgos[] = [
                'titulo' => 'Mejor franja horaria',
                'valor' => $f['dia'] . ' ' . $f['franja'],
                'detalle' => 'Mediana de ' . number_format($f['mediana'], 0, ',', '.') . ' vistas en ' . $f['videos'] . ' videos.',
            ];
        }

        $duraciones = array_filter($resumen['duraciones'], fn($d) => $d['videos'] >= self::MINIMO_MUESTRA && $d['mediana'] !== null);
        if ($duraciones) {
            usort($duraciones, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
            $d = $duraciones[0];
            $hallazgos[] = [
                'titulo' => 'Duración que mejor rinde',
                'valor' => $d['etiqueta'],
                'detalle' => 'Mediana de ' . number_format($d['mediana'], 0, ',', '.') . ' vistas en ' . $d['videos'] . ' videos.',
            ];
        }

        if (!empty($resumen['hashtags']['por_rendimiento'])) {
            $h = $resumen['hashtags']['por_rendimiento'][0];
            $hallazgos[] = [
                'titulo' => 'Hashtag con mejor rendimiento',
                'valor' => '#' . $h['nombre'],
                'detalle' => 'Mediana de ' . number_format($h['mediana'], 0, ',', '.') . ' vistas en ' . $h['videos'] . ' videos.',
            ];
        }

        $categorias = array_filter(
            $resumen['clasificacion']['categorias'] ?? [],
            fn($c) => $c['confiable'] && $c['mediana'] !== null && $c['nombre'] !== Clasificador::SIN_CATEGORIA
        );
        if ($categorias) {
            usort($categorias, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
            $c = $categorias[0];
            $hallazgos[] = [
                'titulo' => 'Categoría con mejor rendimiento',
                'valor' => $c['nombre'],
                'detalle' => 'Mediana de ' . number_format($c['mediana'], 0, ',', '.') . ' vistas en ' . $c['videos'] . ' videos.',
            ];
        }

        $momento = $resumen['franjas_dia']['mejor_vistas'] ?? null;
        if ($momento) {
            $hallazgos[] = [
                'titulo' => 'Momento del día con más vistas',
                'valor' => $momento['etiqueta'],
                'detalle' => 'Mediana de ' . number_format($momento['mediana'], 0, ',', '.') . ' vistas en ' . $momento['videos'] . ' videos (' . $momento['rango'] . ').',
            ];
        }

        return $hallazgos;
    }

    private static function describirExcepciones(array $todos, array $excepciones): array
    {
        $conExcepciones = self::estadisticas($todos);
        $sinExcepciones = self::estadisticas(array_values(array_filter($todos, fn($v) => !isset($excepciones['ids'][$v['id']]))));
        $cantidad = count($excepciones['lista']);
        $vistasExcepciones = array_sum(array_column($excepciones['lista'], 'vistas'));

        return [
            'umbral' => $excepciones['umbral'],
            'limite' => $excepciones['limite'],
            'mediana_canal' => $excepciones['mediana'],
            'cantidad' => $cantidad,
            'porcentaje_videos' => count($todos) > 0 ? $cantidad / count($todos) : 0.0,
            'porcentaje_vistas' => $conExcepciones['vistas'] > 0 ? $vistasExcepciones / $conExcepciones['vistas'] : 0.0,
            'con' => $conExcepciones,
            'sin' => $sinExcepciones,
            'lista' => array_slice($excepciones['lista'], 0, 15),
        ];
    }

    public static function estadisticas(array $grupo): array
    {
        $vistas = array_values(array_filter(array_column($grupo, 'vistas'), fn($v) => $v !== null));
        $interacciones = array_sum(array_column($grupo, 'interacciones'));
        $totalVistas = array_sum($vistas);
        $suma = fn(string $campo) => array_sum(array_map(fn($v) => (int)$v[$campo], $grupo));

        return [
            'videos' => count($grupo),
            'vistas' => $totalVistas,
            'interacciones' => $interacciones,
            'promedio' => count($vistas) > 0 ? $totalVistas / count($vistas) : null,
            'mediana' => self::mediana($vistas),
            'engagement' => $totalVistas > 0 ? $interacciones / $totalVistas : null,
            'likes' => $suma('likes'),
            'comentarios' => $suma('comentarios'),
            'compartidos' => $suma('compartidos'),
            'guardados' => $suma('guardados'),
        ];
    }

    /** Agrega a unas estadísticas los promedios de comentarios, compartidos y guardados por video. */
    public static function conPromedios(array $e): array
    {
        $n = max(1, $e['videos']);
        return $e + [
            'comentarios_prom' => $e['comentarios'] / $n,
            'compartidos_prom' => $e['compartidos'] / $n,
            'guardados_prom' => $e['guardados'] / $n,
        ];
    }

    private static function comparativaReciente(array $videos): array
    {
        $referencia = max(array_column($videos, 'ts'));
        $treinta = 30 * 86400;

        $actual = array_filter($videos, fn($v) => $v['ts'] > $referencia - $treinta);
        $anterior = array_filter($videos, fn($v) => $v['ts'] <= $referencia - $treinta && $v['ts'] > $referencia - 2 * $treinta);

        $a = self::estadisticas($actual);
        $b = self::estadisticas($anterior);

        $variacion = ($a['mediana'] !== null && $b['mediana'] !== null && $b['mediana'] > 0)
            ? ($a['mediana'] - $b['mediana']) / $b['mediana']
            : null;

        return [
            'actual' => $a,
            'anterior' => $b,
            'variacion_vistas' => $variacion,
            'hasta' => date('Y-m-d', $referencia),
        ];
    }

    private static function serieMensual(array $videos): array
    {
        $grupos = [];
        foreach ($videos as $v) {
            if ($v['mes'] !== '') {
                $grupos[$v['mes']][] = $v;
            }
        }
        ksort($grupos);

        $serie = [];
        foreach ($grupos as $mes => $grupo) {
            $serie[] = ['mes' => $mes] + self::estadisticas($grupo);
        }
        return $serie;
    }

    private static function serieAnual(array $videos): array
    {
        $grupos = [];
        foreach ($videos as $v) {
            if ($v['anio'] !== '') {
                $grupos[$v['anio']][] = $v;
            }
        }
        ksort($grupos);

        $serie = [];
        foreach ($grupos as $anio => $grupo) {
            $serie[] = ['anio' => (string)$anio] + self::estadisticas($grupo);
        }
        return $serie;
    }

    private static function agruparDias(array $videos): array
    {
        $grupos = array_fill(0, 7, []);
        foreach ($videos as $v) {
            if ($v['dia'] !== null) {
                $grupos[$v['dia']][] = $v;
            }
        }

        $salida = [];
        foreach ($grupos as $indice => $grupo) {
            $salida[] = ['indice' => $indice, 'nombre' => self::DIAS[$indice]] + self::conPromedios(self::estadisticas($grupo));
        }
        return $salida;
    }

    private static function agruparHoras(array $videos): array
    {
        $grupos = array_fill(0, 24, []);
        foreach ($videos as $v) {
            if ($v['hora'] !== null && $v['hora'] >= 0 && $v['hora'] <= 23) {
                $grupos[$v['hora']][] = $v;
            }
        }

        $salida = [];
        foreach ($grupos as $hora => $grupo) {
            $salida[] = ['hora' => $hora] + self::estadisticas($grupo);
        }
        return $salida;
    }

    private static function mapaCalor(array $videos): array
    {
        $celdas = [];
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $celdas[$d][$h] = [];
            }
        }

        foreach ($videos as $v) {
            if ($v['dia'] !== null && $v['hora'] !== null && $v['hora'] >= 0 && $v['hora'] <= 23 && $v['vistas'] !== null) {
                $celdas[$v['dia']][$v['hora']][] = $v['vistas'];
            }
        }

        $medianas = [];
        foreach ($celdas as $d => $horas) {
            foreach ($horas as $h => $lista) {
                if (count($lista) >= 3) {
                    $medianas[] = self::mediana($lista);
                }
            }
        }
        sort($medianas);
        $cantidad = count($medianas);
        $corte = fn(float $p) => $cantidad > 0 ? $medianas[(int)min($cantidad - 1, floor($cantidad * $p))] : 0;
        $cortes = [$corte(0.2), $corte(0.4), $corte(0.6), $corte(0.8)];

        $salida = [];
        foreach ($celdas as $d => $horas) {
            foreach ($horas as $h => $lista) {
                $n = count($lista);
                $mediana = $n > 0 ? self::mediana($lista) : null;
                $nivel = 0;
                if ($n >= 3 && $mediana !== null) {
                    $nivel = 1;
                    foreach ($cortes as $c) {
                        if ($mediana > $c) {
                            $nivel++;
                        }
                    }
                }
                $salida[$d][$h] = ['videos' => $n, 'mediana' => $mediana, 'nivel' => $nivel];
            }
        }

        return $salida;
    }

    private static function mejoresFranjas(array $videos): array
    {
        $grupos = [];
        foreach ($videos as $v) {
            if ($v['dia'] === null || $v['hora'] === null || $v['vistas'] === null) {
                continue;
            }
            $franja = intdiv($v['hora'], 3);
            $grupos[$v['dia'] . '-' . $franja][] = $v['vistas'];
        }

        $franjas = [];
        foreach ($grupos as $clave => $lista) {
            if (count($lista) < self::MINIMO_MUESTRA * 2) {
                continue;
            }
            [$dia, $franja] = array_map('intval', explode('-', $clave));
            $franjas[] = [
                'dia' => self::DIAS[$dia],
                'franja' => sprintf('%02d:00 a %02d:00', $franja * 3, ($franja + 1) * 3 % 24),
                'videos' => count($lista),
                'mediana' => self::mediana($lista),
            ];
        }

        usort($franjas, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
        return array_slice($franjas, 0, 5);
    }

    private static function agruparDuracion(array $videos): array
    {
        $salida = [];
        foreach (self::RANGOS_DURACION as $rango) {
            $grupo = array_filter($videos, fn($v) => $v['duracion'] !== null && $v['duracion'] >= $rango['min'] && $v['duracion'] <= $rango['max']);
            $salida[] = ['clave' => $rango['clave'], 'etiqueta' => $rango['etiqueta']] + self::estadisticas($grupo);
        }
        return $salida;
    }

    private static function distribucionVistas(array $videos): array
    {
        $salida = [];
        foreach (self::RANGOS_VISTAS as $rango) {
            $cantidad = count(array_filter($videos, fn($v) => $v['vistas'] !== null && $v['vistas'] >= $rango['min'] && $v['vistas'] <= $rango['max']));
            $salida[] = ['etiqueta' => $rango['etiqueta'], 'videos' => $cantidad];
        }
        return $salida;
    }

    public const MINIMO_PAR_HASHTAGS = 3;

    private static function analizarHashtags(array $videos, float $medianaGlobal): array
    {
        $grupos = [];
        foreach ($videos as $v) {
            foreach (array_unique($v['hashtags']) as $tag) {
                $grupos[$tag][] = $v;
            }
        }

        $lista = [];
        $poco = [];
        foreach ($grupos as $nombre => $grupo) {
            $fila = ['nombre' => (string)$nombre] + self::conPromedios(self::estadisticas($grupo));
            if (count($grupo) >= self::MINIMO_MUESTRA) {
                $lista[] = $fila;
            } elseif (count($grupo) >= 2 && $fila['mediana'] !== null && $medianaGlobal > 0) {
                $fila['veces_mediana'] = $fila['mediana'] / $medianaGlobal;
                $poco[] = $fila;
            }
        }

        $masUsados = $lista;
        usort($masUsados, fn($a, $b) => $b['videos'] <=> $a['videos']);

        $porRendimiento = array_values(array_filter($lista, fn($h) => $h['mediana'] !== null));
        usort($porRendimiento, fn($a, $b) => $b['mediana'] <=> $a['mediana']);

        $peores = array_reverse($porRendimiento);

        $porInteraccion = array_values(array_filter($lista, fn($h) => $h['engagement'] !== null));
        usort($porInteraccion, fn($a, $b) => $b['engagement'] <=> $a['engagement']);

        $porCompartidos = $lista;
        usort($porCompartidos, fn($a, $b) => $b['compartidos_prom'] <=> $a['compartidos_prom']);

        // Poco usados que rinden más que la mediana del canal: oportunidades por explotar.
        $poco = array_values(array_filter($poco, fn($h) => $h['veces_mediana'] > 1));
        usort($poco, fn($a, $b) => $b['veces_mediana'] <=> $a['veces_mediana']);

        return [
            'distintos' => count($grupos),
            'analizados' => count($lista),
            'mas_usados' => array_slice($masUsados, 0, 15),
            'por_rendimiento' => array_slice($porRendimiento, 0, 10),
            'peores' => array_slice($peores, 0, 10),
            'por_interaccion' => array_slice($porInteraccion, 0, 10),
            'por_compartidos' => array_slice($porCompartidos, 0, 10),
            'poco_usados' => array_slice($poco, 0, 10),
            'coocurrencia' => self::coocurrenciaHashtags($videos),
        ];
    }

    /** Pares de hashtags que aparecen juntos en al menos MINIMO_PAR_HASHTAGS videos. */
    private static function coocurrenciaHashtags(array $videos, int $maximo = 12): array
    {
        $pares = [];
        foreach ($videos as $v) {
            $tags = array_values(array_unique($v['hashtags']));
            sort($tags);
            $n = count($tags);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pares[$tags[$i] . "\0" . $tags[$j]][] = $v;
                }
            }
        }

        $salida = [];
        foreach ($pares as $clave => $grupo) {
            if (count($grupo) < self::MINIMO_PAR_HASHTAGS) {
                continue;
            }
            [$a, $b] = explode("\0", $clave);
            $salida[] = ['a' => (string)$a, 'b' => (string)$b] + self::estadisticas($grupo);
        }
        usort($salida, fn($x, $y) => $y['videos'] <=> $x['videos'] ?: ($y['mediana'] <=> $x['mediana']));
        return array_slice($salida, 0, $maximo);
    }

    private static function analizarSonidos(array $videos): array
    {
        $originales = array_filter($videos, fn($v) => $v['sonido_original']);
        $otros = array_filter($videos, fn($v) => !$v['sonido_original'] && $v['sonido'] !== '');

        $grupos = [];
        foreach ($otros as $v) {
            $grupos[$v['sonido']][] = $v;
        }
        $canciones = [];
        foreach ($grupos as $clave => $grupo) {
            if (count($grupo) < 3) {
                continue;
            }
            $canciones[] = ['titulo' => (string)$clave, 'autor' => ''] + self::estadisticas($grupo);
        }
        usort($canciones, fn($a, $b) => $b['videos'] <=> $a['videos']);

        return [
            'originales' => self::estadisticas($originales),
            'con_musica' => self::estadisticas($otros),
            'canciones' => array_slice($canciones, 0, 8),
        ];
    }

    private static function enriquecer(array $video, float $medianaGlobal): array
    {
        $video['veces_mediana'] = ($video['vistas'] !== null && $medianaGlobal > 0) ? $video['vistas'] / $medianaGlobal : null;
        return $video;
    }

    private static function tomar(array $videos, string $campo, int $limite, float $medianaGlobal): array
    {
        $validos = array_filter($videos, fn($v) => $v[$campo] !== null);
        usort($validos, fn($a, $b) => $b[$campo] <=> $a[$campo]);
        return array_map(fn($v) => self::enriquecer($v, $medianaGlobal), array_slice(array_values($validos), 0, $limite));
    }

    private static function topEngagement(array $videos, float $medianaGlobal): array
    {
        $validos = array_filter($videos, fn($v) => $v['engagement'] !== null && $v['vistas'] >= 1000);
        usort($validos, fn($a, $b) => $b['engagement'] <=> $a['engagement']);
        return array_map(fn($v) => self::enriquecer($v, $medianaGlobal), array_slice(array_values($validos), 0, 10));
    }

    private static function recientes(array $videos, float $medianaGlobal): array
    {
        return array_map(fn($v) => self::enriquecer($v, $medianaGlobal), array_slice($videos, 0, 8));
    }

    public static function explorar(array $videos, array $filtros): array
    {
        $texto = mb_strtolower(trim((string)($filtros['q'] ?? '')));
        $anio = (string)($filtros['anio'] ?? '');
        $orden = (string)($filtros['orden'] ?? 'fecha');
        $direccion = ($filtros['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $porPagina = max(10, min(100, (int)($filtros['por_pagina'] ?? 25)));
        $pagina = max(1, (int)($filtros['pagina'] ?? 1));

        $campos = [
            'fecha' => 'ts', 'vistas' => 'vistas', 'likes' => 'likes', 'comentarios' => 'comentarios',
            'compartidos' => 'compartidos', 'guardados' => 'guardados', 'engagement' => 'engagement', 'duracion' => 'duracion',
        ];
        $campoOrden = $campos[$orden] ?? 'ts';

        $modoExcepciones = in_array($filtros['excepciones'] ?? '', ['solo', 'sin'], true) ? $filtros['excepciones'] : 'todos';
        $umbral = self::umbralValido($filtros['umbral'] ?? self::UMBRAL_EXCEPCION_DEFECTO);
        $excepciones = self::detectarExcepciones($videos, $umbral);
        foreach ($videos as $i => $v) {
            $videos[$i]['excepcion'] = isset($excepciones['ids'][$v['id']]);
        }

        $categoria = (string)($filtros['categoria'] ?? '');
        $categorias = Clasificador::categorias();
        $categoria = in_array($categoria, $categorias, true) ? $categoria : '';
        foreach ($videos as $i => $v) {
            $clasificado = Clasificador::clasificarTexto(Clasificador::textoVideo($v));
            $videos[$i]['categoria'] = $clasificado['categoria'];
            $videos[$i]['secundaria'] = $clasificado['secundaria'];
        }

        $filtrados = array_values(array_filter($videos, function ($v) use ($texto, $anio, $modoExcepciones, $categoria) {
            if ($anio !== '' && $v['anio'] !== $anio) {
                return false;
            }
            if ($categoria !== '' && $v['categoria'] !== $categoria) {
                return false;
            }
            if ($modoExcepciones === 'solo' && !$v['excepcion']) {
                return false;
            }
            if ($modoExcepciones === 'sin' && $v['excepcion']) {
                return false;
            }
            if ($texto === '') {
                return true;
            }
            if (str_contains(mb_strtolower($v['descripcion']), $texto)) {
                return true;
            }
            foreach ($v['hashtags'] as $tag) {
                if (str_contains(mb_strtolower($tag), ltrim($texto, '#'))) {
                    return true;
                }
            }
            return false;
        }));

        usort($filtrados, function ($a, $b) use ($campoOrden, $direccion) {
            $x = $a[$campoOrden] ?? -1;
            $y = $b[$campoOrden] ?? -1;
            return $direccion === 'asc' ? $x <=> $y : $y <=> $x;
        });

        $total = count($filtrados);
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min($pagina, $paginas);

        $anios = array_values(array_unique(array_filter(array_column($videos, 'anio'))));
        rsort($anios);

        return [
            'items' => array_slice($filtrados, ($pagina - 1) * $porPagina, $porPagina),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'por_pagina' => $porPagina,
            'anios' => $anios,
            'categorias' => $categorias,
            'resumen' => self::estadisticas($filtrados),
            'umbral' => $umbral,
            'limite_excepcion' => $excepciones['limite'],
            'cantidad_excepciones' => count($excepciones['lista']),
        ];
    }
}
