<?php

/**
 * Asistente de estrategia: sugiere hashtags e ideas de publicación a partir del historial de la cuenta.
 *
 * Todo se calcula con los datos propios (categorías, hashtags, vistas, interacción). No usa IA externa ni
 * envía información a ningún servicio. Las sugerencias se basan en evidencia histórica: no predicen vistas.
 */
class Estrategia
{
    private const IDEAS = [
        'Académico' => 'Guía rápida del proceso académico: fechas, requisitos y consejos para el próximo periodo',
        'Educación' => 'Historias de estudiantes y docentes: cómo se forman en la UMSA',
        'Cultura' => 'Conoce los talleres artísticos que ofrece la UMSA a sus estudiantes',
        'Deportes' => 'Resumen de la jornada deportiva universitaria con los mejores momentos',
        'Noticias' => 'Lo más importante de la semana en la UMSA, explicado en menos de un minuto',
        'Institucional' => 'Qué deciden y qué hacen las autoridades universitarias: avances que impactan a los estudiantes',
        'Investigación' => 'Un proyecto de investigación de la UMSA explicado en un minuto',
        'Entretenimiento' => 'La UMSA opina: preguntas rápidas a estudiantes sobre un tema del momento',
        'Eventos universitarios' => 'Así se vivió el último evento universitario: resumen con los mejores momentos',
        'Información administrativa' => 'Paso a paso de un trámite universitario: requisitos, fechas y dónde hacerlo',
    ];

    public const MAXIMO_IDEAS = 5;

    // ------------------------------------------------------------------ Hashtags sugeridos

    /**
     * @param array $videos Videos del historial (ya normalizados).
     */
    public static function recomendarHashtags(string $texto, array $videos, float $medianaGlobal, int $maximo = 10): array
    {
        $texto = trim($texto);
        $normalizado = Clasificador::normalizar($texto);
        if ($normalizado === '' || !$videos) {
            return ['hay_texto' => false];
        }

        $clasificacion = Clasificador::clasificarTexto($texto);
        $categoria = $clasificacion['categoria'];
        $palabras = Clasificador::palabras($normalizado);

        preg_match_all('/#([\p{L}\p{N}_]+)/u', $texto, $encontrados);
        $escritos = array_values(array_unique(array_map('mb_strtolower', $encontrados[1] ?? [])));
        $escritosSet = array_flip($escritos);

        $relevancia = [];
        $peso = [];
        $enCategoria = [];
        $total = count($videos);
        $usos = [];

        foreach ($videos as $v) {
            foreach (array_unique($v['hashtags']) as $tag) {
                $usos[$tag] = ($usos[$tag] ?? 0) + 1;
            }
        }

        foreach ($videos as $v) {
            $vNorm = ' ' . Clasificador::normalizar(Clasificador::textoVideo($v));
            $puntos = 0;

            if ($categoria !== Clasificador::SIN_CATEGORIA
                && Clasificador::clasificarTexto(Clasificador::textoVideo($v))['categoria'] === $categoria) {
                $puntos += 2;
            }
            $coinciden = 0;
            foreach ($palabras as $p) {
                if (str_contains($vNorm, ' ' . $p)) {
                    $coinciden++;
                }
            }
            $puntos += min(5, $coinciden);
            foreach ($v['hashtags'] as $tag) {
                if (isset($escritosSet[$tag])) {
                    $puntos++;
                }
            }

            if ($puntos === 0) {
                continue;
            }

            $relevancia[] = ['video' => $v, 'puntos' => $puntos];
            foreach (array_unique($v['hashtags']) as $tag) {
                if (isset($escritosSet[$tag])) {
                    continue;
                }
                $peso[$tag] = ($peso[$tag] ?? 0) + $puntos;
                $enCategoria[$tag] = ($enCategoria[$tag] ?? 0) + 1;
            }
        }

        // Un hashtag que usa casi todo el canal dice poco del tema: se le resta peso (idea del IDF).
        foreach ($peso as $tag => $p) {
            $peso[$tag] = $p * log(1 + $total / max(1, $usos[$tag] ?? 1));
        }
        arsort($peso);
        $maximoPeso = $peso ? max($peso) : 1;

        $candidatos = [];
        foreach (array_slice(array_keys($peso), 0, 40) as $tag) {
            $grupo = array_values(array_filter($videos, fn($v) => in_array($tag, $v['hashtags'], true)));
            $e = Analisis::estadisticas($grupo);
            if ($e['mediana'] === null) {
                continue;
            }
            $veces = $medianaGlobal > 0 ? $e['mediana'] / $medianaGlobal : 1.0;
            $rendimiento = max(0.0, min(1.0, log(max($veces, 0.01) + 1) / log(4)));
            $muestra = min(1.0, $e['videos'] / Analisis::MINIMO_MUESTRA);
            $puntaje = 0.6 * ($peso[$tag] / $maximoPeso) + 0.4 * $rendimiento * $muestra;

            $motivos = [];
            foreach ($palabras as $p) {
                if (str_contains(Clasificador::normalizar($tag), $p)) {
                    $motivos[] = 'Coincide con tu texto';
                    break;
                }
            }
            if ($categoria !== Clasificador::SIN_CATEGORIA && $enCategoria[$tag] >= 2) {
                $motivos[] = 'Relación temática con ' . $categoria;
            }
            if ($e['videos'] >= max(Analisis::MINIMO_MUESTRA, $total * 0.2)) {
                $motivos[] = 'Presencia histórica de la cuenta';
            }
            if (!$motivos) {
                $motivos[] = 'Aparece junto a videos parecidos';
            }

            $candidatos[] = [
                'hashtag' => $tag,
                'videos' => $e['videos'],
                'mediana' => $e['mediana'],
                'engagement' => $e['engagement'],
                'veces_mediana' => $veces,
                'motivos' => $motivos,
                'muestra_pequena' => $e['videos'] < Analisis::MINIMO_MUESTRA,
                'puntaje' => $puntaje,
            ];
        }
        usort($candidatos, fn($a, $b) => $b['puntaje'] <=> $a['puntaje']);

        usort($relevancia, fn($a, $b) => $b['puntos'] <=> $a['puntos'] ?: $b['video']['ts'] <=> $a['video']['ts']);

        return [
            'hay_texto' => true,
            'categoria' => $categoria,
            'secundaria' => $clasificacion['secundaria'],
            'palabras' => array_slice($palabras, 0, 12),
            'escritos' => $escritos,
            'hashtags' => array_slice($candidatos, 0, $maximo),
            'similares' => array_map(fn($r) => $r['video'], array_slice($relevancia, 0, 5)),
            'videos_relacionados' => count($relevancia),
        ];
    }

    // ------------------------------------------------------------------ Ideas de publicación

    public static function ideas(array $videos, array $clasificacion, float $medianaGlobal): array
    {
        $porVideo = $clasificacion['por_video'];
        $categorias = array_values(array_filter(
            $clasificacion['categorias'],
            fn($c) => $c['nombre'] !== Clasificador::SIN_CATEGORIA && $c['mediana'] !== null
        ));
        if (!$categorias || $medianaGlobal <= 0) {
            return [];
        }

        $propuestas = [];

        // 1) Temas que mejor rinden (con muestra suficiente).
        $rinden = array_values(array_filter($categorias, fn($c) => $c['confiable'] && $c['mediana'] > $medianaGlobal));
        usort($rinden, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
        foreach (array_slice($rinden, 0, 2) as $c) {
            $propuestas[] = self::proponer('rinde', $c, $videos, $porVideo, $medianaGlobal,
                sprintf(
                    'Es de las categorías que mejor rinden: mediana de %s vistas en %d videos (%s veces la mediana del canal).',
                    number_format($c['mediana'], 0, ',', '.'), $c['videos'], number_format($c['mediana'] / $medianaGlobal, 1, ',', '.')
                ));
        }

        // 2) Temas poco explotados: rinden bien pero se publican poco.
        $yaIncluidas = array_column($propuestas, 'tema');
        $poco = array_values(array_filter(
            $categorias,
            fn($c) => $c['videos'] >= 3 && $c['porcentaje'] < 0.08 && $c['mediana'] >= $medianaGlobal && !in_array($c['nombre'], $yaIncluidas, true)
        ));
        usort($poco, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
        foreach (array_slice($poco, 0, 2) as $c) {
            $propuestas[] = self::proponer('oportunidad', $c, $videos, $porVideo, $medianaGlobal,
                sprintf(
                    'Tema poco explotado: solo el %s de los videos, con mediana de %s vistas (por encima de la del canal). Hay espacio para publicar más.',
                    number_format($c['porcentaje'] * 100, 1, ',', '.') . ' %', number_format($c['mediana'], 0, ',', '.')
                ));
        }

        // 3) Temas emergentes detectados en lo que no encajó en ninguna categoría.
        foreach (array_slice($clasificacion['emergentes'], 0, 1) as $t) {
            $propuestas[] = [
                'tipo' => 'emergente',
                'tema' => 'Tema emergente: «' . $t['termino'] . '»',
                'idea' => 'Un video que desarrolle «' . $t['termino'] . '» con un mensaje claro desde los primeros segundos',
                'hashtags' => ['#' . $t['termino'], '#umsa'],
                'justificacion' => sprintf(
                    'El término aparece en %d videos que no encajan en ninguna categoría y %s.',
                    $t['videos_sin_categoria'],
                    $t['mediana'] !== null ? 'su mediana es de ' . number_format($t['mediana'], 0, ',', '.') . ' vistas' : 'aún no tiene métricas'
                ),
                'recomendacion' => 'Pruébalo como serie corta antes de convertirlo en una categoría fija.',
            ];
        }

        return array_slice($propuestas, 0, self::MAXIMO_IDEAS);
    }

    private static function proponer(string $tipo, array $categoria, array $videos, array $porVideo, float $medianaGlobal, string $justificacion): array
    {
        $nombre = $categoria['nombre'];
        $grupo = array_values(array_filter($videos, fn($v) => ($porVideo[$v['id']]['categoria'] ?? '') === $nombre));

        // Hashtags que más acompañan a esta categoría.
        $conteo = [];
        foreach ($grupo as $v) {
            foreach (array_unique($v['hashtags']) as $tag) {
                $conteo[$tag][] = $v;
            }
        }
        uasort($conteo, fn($a, $b) => count($b) <=> count($a));
        $tags = [];
        foreach ($conteo as $tag => $lista) {
            if (count($lista) >= 2) {
                $tags[] = '#' . $tag;
            }
            if (count($tags) >= 5) {
                break;
            }
        }

        // Formato: duración de la mitad que mejor rindió dentro de la categoría.
        $ordenados = array_values(array_filter($grupo, fn($v) => $v['vistas'] !== null && $v['duracion'] !== null));
        usort($ordenados, fn($a, $b) => $b['vistas'] <=> $a['vistas']);
        $mejores = array_slice($ordenados, 0, max(1, intdiv(count($ordenados), 2)));
        $duracion = Analisis::mediana(array_column($mejores, 'duracion'));

        $recomendacion = $duracion !== null
            ? sprintf('Video vertical de unos %d segundos (la duración mediana de los más vistos de esta categoría), con el tema claro desde el inicio.', round($duracion))
            : 'Video vertical corto, con el tema claro desde el inicio.';

        return [
            'tipo' => $tipo,
            'tema' => $nombre,
            'idea' => self::IDEAS[$nombre] ?? ('Una publicación sobre ' . mb_strtolower($nombre)),
            'hashtags' => $tags,
            'justificacion' => $justificacion,
            'recomendacion' => $recomendacion,
        ];
    }
}
