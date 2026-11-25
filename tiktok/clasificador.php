<?php

/**
 * Clasificación automática de publicaciones a partir de la descripción y los hashtags.
 *
 * No usa ninguna IA externa ni API: es un clasificador por palabras clave (ponderado por cuántas veces
 * aparecen) más un detector de temas emergentes (términos frecuentes en lo que no encaja en ninguna
 * categoría y que no son típicos de toda la cuenta). Es reproducible y corre en el mismo servidor.
 * Clasifica el texto, no el contenido audiovisual del video.
 */
class Clasificador
{
    public const SIN_CATEGORIA = 'Otros';

    /**
     * Palabras clave por categoría, sin tildes y en minúscula.
     * Una palabra coincide con el inicio de cualquier palabra del texto («cultur» → cultura, cultural).
     * Con «!» al final solo coincide la palabra exacta.
     */
    private const CATEGORIAS = [
        'Académico' => ['clase', 'examen', 'semestre', 'curso', 'materia', 'facultad', 'carrera', 'docente', 'catedra', 'academic', 'estudiar', 'posgrado', 'licenciatura', 'diplomado', 'maestria', 'titulacion', 'graduacion', 'aprobar', 'reprobar', 'nota!', 'notas!', 'psa!', 'prueba de suficiencia', 'tutoria', 'recuperar', 'adelantar'],
        'Educación' => ['educacion', 'pedagog', 'ensenanza', 'aprendizaje', 'estudiante', 'humanidades', 'escuela', 'colegio', 'maestro', 'formacion', 'didactic', 'normalista', 'bachiller'],
        'Cultura' => ['cultur', 'danza', 'teatro', 'arte!', 'artes!', 'artistic', 'artista', 'musica', 'folklor', 'folclor', 'exposicion', 'pintura', 'cine!', 'literatura', 'taller', 'concierto', 'baile', 'canto!', 'poesia', 'festival', 'obra!', 'coro!', 'orquesta', 'escenic', 'patrimonio', 'tradicion', 'museo', 'fotografia', 'entrada', 'efu!', 'fraternidad', 'morenada', 'caporal', 'diablada', 'tinku', 'saya!', 'wititis', 'folclore', 'danzas!'],
        'Deportes' => ['deport', 'futbol', 'basquet', 'voleibol', 'voley', 'atletismo', 'natacion', 'torneo', 'campeonato', 'olimpiada', 'interfacultad', 'gimnasio', 'competencia', 'copa!', 'partido!', 'ajedrez', 'karate', 'taekwondo', 'ciclismo'],
        'Noticias' => ['noticia', 'comunicado', 'ultima hora', 'ultimo momento', 'urgente', 'informe', 'conferencia de prensa', 'actualidad', 'paro!', 'marcha!', 'bloqueo', 'conflicto', 'protesta', 'denuncia', 'boletin', 'anuncia'],
        'Institucional' => ['rector', 'autoridad', 'consejo', 'congreso', 'aniversario', 'convenio', 'decano', 'vicerrector', 'autonomia', 'honorable', 'resolucion', 'posesion', 'homenaje', 'institucional', 'conmemor', 'gestion!', 'directivo'],
        'Investigación' => ['investig', 'cientific', 'ciencia!', 'tesis', 'laboratorio', 'innovacion', 'tecnolog', 'descubrimiento', 'publicacion cientifica', 'hallazgo', 'semillero', 'biolog', 'quimic', 'geolog', 'astronom'],
        'Entretenimiento' => ['tik tak', 'tiktak', 'humor', 'reto!', 'retos!', 'challenge', 'trend', 'tendencia', 'divertid', 'meme', 'juego', 'trivia', 'opina', 'opinion', 'sorteo', 'dinamica', 'encuesta', 'entrevista', 'viral', 'sabias', 'curiosidad', 'preguntamos'],
        'Eventos universitarios' => ['evento', 'feria', 'charla', 'seminario', 'conversatorio', 'jornada', 'bienvenida', 'inauguracion', 'encuentro', 'simposio', 'foro!', 'webinar', 'conferencia', 'mesa redonda', 'ceremonia', 'lanzamiento', 'recibimiento', 'mechones', 'fiesta', 'festejo'],
        'Información administrativa' => ['inscripcion', 'matricula', 'tramite', 'requisito', 'certificado', 'calendario', 'fechas!', 'plazo', 'convocatoria', 'admision', 'ingreso', 'arancel', 'pago!', 'pagos!', 'formulario', 'documento', 'horario de atencion', 'solicitud', 'beca', 'registro!', 'habilitacion'],
    ];

    /** Etiquetas de segundo nivel que se muestran como categoría secundaria. */
    private const SUBTEMAS = [
        'Artes escénicas' => ['danza', 'teatro', 'escenic', 'baile'],
        'Música' => ['music', 'concierto', 'coro!', 'orquesta'],
        'Vida universitaria' => ['estudiante', 'universitari', 'campus', 'opina', 'mechones'],
        'Información académica' => ['curso', 'examen', 'semestre', 'calendario', 'inscripcion', 'materia'],
        'Becas y apoyo' => ['beca', 'bienestar', 'apoyo'],
        'Salud y bienestar' => ['salud', 'medic', 'enfermer', 'psicolog', 'nutricion'],
        'Medio ambiente' => ['ambient', 'ecolog', 'reciclaj', 'sostenib'],
        'Tecnología' => ['tecnolog', 'software', 'programacion', 'inteligencia artificial', 'robot'],
        'Derecho y sociedad' => ['derecho', 'juridic', 'social', 'ciudadan'],
    ];

    /** Palabras que no aportan como tema. */
    private const IGNORAR = [
        'para', 'como', 'esta', 'este', 'estos', 'estas', 'pero', 'porque', 'cuando', 'donde', 'desde', 'hasta', 'sobre', 'entre', 'tambien',
        'ellos', 'ellas', 'nosotros', 'todos', 'todas', 'cada', 'otro', 'otra', 'otros', 'otras', 'muy', 'mas', 'menos', 'solo', 'sino',
        'tiene', 'tienen', 'hacer', 'hace', 'hacen', 'puede', 'pueden', 'ser', 'son', 'fue', 'hay', 'una', 'unos', 'unas', 'del', 'los', 'las',
        'con', 'por', 'que', 'sus', 'nos', 'les', 'ese', 'esa', 'eso', 'aqui', 'alli', 'ahora', 'siempre', 'nunca', 'video', 'videos',
        'umsa', 'tvuumsalp', 'umsasomostodos', 'tvu', 'lapaz', 'bolivia', 'fyp', 'parati', 'viral', 'foryou', 'tiktok', 'universidad',
        'mayor', 'andres', 'san', 'sector',
    ];

    private static array $patrones = [];

    public static function categorias(): array
    {
        return array_merge(array_keys(self::CATEGORIAS), [self::SIN_CATEGORIA]);
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    /** Palabras significativas de un texto ya normalizado. */
    public static function palabras(string $normalizado): array
    {
        $salida = [];
        foreach (explode(' ', $normalizado) as $p) {
            // Se descartan palabras con números (hashtags de año) y los hashtags propios de la cuenta (umsa..., tvu...).
            if (strlen($p) >= 4 && ctype_alpha($p) && !in_array($p, self::IGNORAR, true)
                && !str_starts_with($p, 'umsa') && !str_starts_with($p, 'tvu')) {
                $salida[$p] = true;
            }
        }
        return array_keys($salida);
    }

    private static function patron(string $clave, array $palabras): string
    {
        if (!isset(self::$patrones[$clave])) {
            $partes = array_map(function (string $p) {
                $exacta = str_ends_with($p, '!');
                $p = preg_quote(rtrim($p, '!'), '/');
                return $exacta ? '\b' . $p . '\b' : '\b' . $p;
            }, $palabras);
            self::$patrones[$clave] = '/' . implode('|', $partes) . '/';
        }
        return self::$patrones[$clave];
    }

    private static function puntuar(string $normalizado, array $grupos, string $prefijo): array
    {
        $puntos = [];
        foreach ($grupos as $nombre => $palabras) {
            $n = preg_match_all(self::patron($prefijo . $nombre, $palabras), $normalizado);
            if ($n > 0) {
                $puntos[$nombre] = $n;
            }
        }
        arsort($puntos);
        return $puntos;
    }

    /** Clasifica un texto (descripción + hashtags) en una categoría principal y una secundaria. */
    public static function clasificarTexto(string $texto): array
    {
        $normalizado = self::normalizar($texto);
        $puntos = self::puntuar($normalizado, self::CATEGORIAS, 'c:');

        if (!$puntos) {
            return ['categoria' => self::SIN_CATEGORIA, 'secundaria' => '', 'puntaje' => 0];
        }

        $categorias = array_keys($puntos);
        $principal = $categorias[0];
        $subtemas = self::puntuar($normalizado, self::SUBTEMAS, 's:');
        $secundaria = $subtemas ? (string)array_key_first($subtemas) : ($categorias[1] ?? '');

        return ['categoria' => $principal, 'secundaria' => $secundaria, 'puntaje' => (int)$puntos[$principal]];
    }

    public static function textoVideo(array $video): string
    {
        return $video['descripcion'] . ' ' . implode(' ', $video['hashtags']);
    }

    /** Clasifica todos los videos y resume el rendimiento por categoría. */
    public static function analizar(array $videos): array
    {
        $porVideo = [];
        $grupos = [];
        $sinClasificar = [];

        foreach ($videos as $v) {
            $r = self::clasificarTexto(self::textoVideo($v));
            $porVideo[$v['id']] = $r;
            $grupos[$r['categoria']][] = $v;
            if ($r['categoria'] === self::SIN_CATEGORIA) {
                $sinClasificar[] = $v;
            }
        }

        $total = count($videos);
        $categorias = [];
        foreach ($grupos as $nombre => $grupo) {
            $e = Analisis::estadisticas($grupo);
            $n = count($grupo);
            $categorias[] = [
                'nombre' => $nombre,
                'videos' => $n,
                'porcentaje' => $total > 0 ? $n / $total : 0.0,
                'vistas' => $e['vistas'],
                'mediana' => $e['mediana'],
                'promedio' => $e['promedio'],
                'engagement' => $e['engagement'],
                'comentarios_prom' => $n > 0 ? $e['comentarios'] / $n : 0.0,
                'compartidos_prom' => $n > 0 ? $e['compartidos'] / $n : 0.0,
                'guardados_prom' => $n > 0 ? $e['guardados'] / $n : 0.0,
                'confiable' => $n >= Analisis::MINIMO_MUESTRA,
            ];
        }
        usort($categorias, fn($a, $b) => $b['videos'] <=> $a['videos']);

        return [
            'por_video' => $porVideo,
            'categorias' => $categorias,
            'sin_clasificar' => count($sinClasificar),
            'emergentes' => self::temasEmergentes($sinClasificar, $videos),
        ];
    }

    /**
     * Temas que no están en el diccionario: términos que se repiten en los videos sin categoría
     * y que no son típicos de toda la cuenta (peso tipo TF-IDF: frecuentes aquí, raros en el resto).
     */
    public static function temasEmergentes(array $sinClasificar, array $todos, int $maximo = 8): array
    {
        $totalVideos = count($todos);
        if ($totalVideos === 0 || count($sinClasificar) < self::minimoEmergente($sinClasificar)) {
            return [];
        }

        $docs = [];
        foreach ($todos as $v) {
            $docs[$v['id']] = self::palabras(self::normalizar(self::textoVideo($v)));
        }

        $dfTotal = [];
        foreach ($docs as $palabras) {
            foreach ($palabras as $p) {
                $dfTotal[$p] = ($dfTotal[$p] ?? 0) + 1;
            }
        }

        $dfSin = [];
        $conTermino = [];
        foreach ($sinClasificar as $v) {
            foreach ($docs[$v['id']] as $p) {
                $dfSin[$p] = ($dfSin[$p] ?? 0) + 1;
                $conTermino[$p][] = $v;
            }
        }

        $minimo = self::minimoEmergente($sinClasificar);
        $candidatos = [];
        foreach ($dfSin as $p => $n) {
            if ($n < $minimo || $dfTotal[$p] / $totalVideos > 0.25) {
                continue;
            }
            $candidatos[$p] = $n * log($totalVideos / $dfTotal[$p]);
        }
        arsort($candidatos);

        $salida = [];
        foreach (array_slice(array_keys($candidatos), 0, $maximo) as $p) {
            $e = Analisis::estadisticas($conTermino[$p]);
            $salida[] = [
                'termino' => $p,
                'videos_sin_categoria' => $dfSin[$p],
                'videos_total' => $dfTotal[$p],
                'mediana' => $e['mediana'],
                'engagement' => $e['engagement'],
            ];
        }
        return $salida;
    }

    private static function minimoEmergente(array $sinClasificar): int
    {
        return max(Analisis::MINIMO_MUESTRA, (int)ceil(count($sinClasificar) * 0.02));
    }
}
