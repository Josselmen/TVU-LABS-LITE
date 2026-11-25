<?php

require_once __DIR__ . '/historial.php';

class VideosModelo
{
    public const ARCHIVO_JSON = '/recursos/videos_info.json';

    public static ?string $archivo = null;

    public static function rutaJson(): string
    {
        return self::$archivo ?? RAIZ . self::ARCHIVO_JSON;
    }

    public static function todos(): array
    {
        $filas = bd()->query('SELECT * FROM videos ORDER BY publicado_en DESC')->fetchAll();
        return array_map([self::class, 'normalizar'], $filas);
    }

    public static function total(): int
    {
        return (int)bd()->query('SELECT COUNT(*) FROM videos')->fetchColumn();
    }

    public static function normalizar(array $f): array
    {
        $entero = fn($v) => $v === null ? null : (int)$v;
        $ts = $f['publicado_en'] !== null ? (int)strtotime($f['publicado_en']) : 0;
        $vistas = $entero($f['vistas']);
        $interacciones = (int)$f['likes'] + (int)$f['comentarios'] + (int)$f['compartidos'] + (int)$f['guardados'];
        $hashtags = json_decode((string)$f['hashtags'], true);

        return [
            'id' => (string)$f['video_id'],
            'url' => (string)$f['url'],
            'ts' => $ts,
            'fecha' => $ts > 0 ? date('Y-m-d', $ts) : '',
            'anio' => $ts > 0 ? date('Y', $ts) : '',
            'mes' => $ts > 0 ? date('Y-m', $ts) : '',
            'dia' => $ts > 0 ? (int)date('N', $ts) - 1 : null,
            'hora' => $ts > 0 ? (int)date('G', $ts) : null,
            'vistas' => $vistas,
            'likes' => $entero($f['likes']),
            'comentarios' => $entero($f['comentarios']),
            'compartidos' => $entero($f['compartidos']),
            'guardados' => $entero($f['guardados']),
            'interacciones' => $interacciones,
            'engagement' => ($vistas !== null && $vistas > 0) ? $interacciones / $vistas : null,
            'descripcion' => (string)$f['descripcion'],
            'hashtags' => is_array($hashtags) ? array_values(array_filter($hashtags, 'is_string')) : [],
            'duracion' => $entero($f['duracion']),
            'portada' => (string)$f['portada'],
            'fijado' => (bool)$f['fijado'],
            'sonido' => (string)$f['sonido'],
            'sonido_original' => (bool)$f['sonido_original'],
            'cuenta' => (string)$f['cuenta'],
        ];
    }

    public static function leerJson(string $ruta): ?array
    {
        if (!is_file($ruta)) {
            return null;
        }
        $datos = json_decode((string)file_get_contents($ruta), true);
        return is_array($datos) ? $datos : null;
    }

    public static function importar(?string $ruta = null): array
    {
        $datos = self::leerJson($ruta ?? self::rutaJson());
        if ($datos === null || count($datos) === 0) {
            return ['exito' => false, 'error' => 'No hay datos en recursos/videos_info.json. Ejecuta capcher.php primero.'];
        }

        $pdo = bd();
        if (!$pdo->inTransaction()) {
            Historial::asegurarTabla();
        }
        $existentes = array_flip($pdo->query('SELECT video_id FROM videos')->fetchAll(PDO::FETCH_COLUMN));

        $sql = 'INSERT INTO videos (video_id, url, publicado_en, descripcion, hashtags, duracion, vistas, likes, comentarios, compartidos, guardados, portada, fijado, sonido, sonido_original, cuenta)
                VALUES (:video_id, :url, :publicado_en, :descripcion, :hashtags, :duracion, :vistas, :likes, :comentarios, :compartidos, :guardados, :portada, :fijado, :sonido, :sonido_original, :cuenta)
                ON DUPLICATE KEY UPDATE
                    url = VALUES(url),
                    publicado_en = COALESCE(VALUES(publicado_en), publicado_en),
                    descripcion = COALESCE(NULLIF(VALUES(descripcion), \'\'), descripcion),
                    hashtags = VALUES(hashtags),
                    duracion = COALESCE(VALUES(duracion), duracion),
                    vistas = COALESCE(VALUES(vistas), vistas),
                    likes = COALESCE(VALUES(likes), likes),
                    comentarios = COALESCE(VALUES(comentarios), comentarios),
                    compartidos = COALESCE(VALUES(compartidos), compartidos),
                    guardados = COALESCE(VALUES(guardados), guardados),
                    portada = COALESCE(NULLIF(VALUES(portada), \'\'), portada),
                    fijado = VALUES(fijado),
                    sonido = COALESCE(NULLIF(VALUES(sonido), \'\'), sonido),
                    sonido_original = VALUES(sonido_original),
                    cuenta = COALESCE(NULLIF(VALUES(cuenta), \'\'), cuenta)';
        $insertar = $pdo->prepare($sql);

        $nuevos = 0;
        $procesados = 0;
        $guardadas = [];
        $propia = !$pdo->inTransaction();

        try {
            if ($propia) {
                $pdo->beginTransaction();
            }

            foreach ($datos as $item) {
                $fila = self::filaDesdeJson($item);
                if ($fila === null) {
                    continue;
                }
                $insertar->execute($fila);
                $guardadas[] = $fila;
                $procesados++;
                if (!isset($existentes[$fila['video_id']])) {
                    $nuevos++;
                    $existentes[$fila['video_id']] = true;
                }
            }

            Historial::registrar($guardadas);

            if ($propia) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['exito' => true, 'total' => $procesados, 'nuevos' => $nuevos];
    }

    public static function filaDesdeJson($item): ?array
    {
        if (!is_array($item) || empty($item['video_id']) || !preg_match('/^\d{10,25}$/', (string)$item['video_id'])) {
            return null;
        }

        $entero = fn($v) => is_numeric($v) ? max(0, (int)$v) : null;
        $texto = fn($v, int $max) => mb_substr(trim((string)($v ?? '')), 0, $max);

        $local = (string)($item['created_at_local'] ?? '');
        $publicado = null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $local, $m)) {
            $publicado = $m[1] . ' ' . $m[2];
        } elseif (is_numeric($item['created_at_ts'] ?? null) && (int)$item['created_at_ts'] > 0) {
            $publicado = date('Y-m-d H:i:s', (int)$item['created_at_ts']);
        }

        $hashtags = array_values(array_unique(array_filter((array)($item['hashtags'] ?? []), 'is_string')));

        return [
            'video_id' => (string)$item['video_id'],
            'url' => $texto($item['url'] ?? '', 255),
            'publicado_en' => $publicado,
            'descripcion' => $texto($item['description'] ?? '', 1000),
            'hashtags' => json_encode($hashtags, JSON_UNESCAPED_UNICODE),
            'duracion' => $entero($item['duration_sec'] ?? null),
            'vistas' => $entero($item['views'] ?? null),
            'likes' => $entero($item['likes'] ?? null),
            'comentarios' => $entero($item['comments'] ?? null),
            'compartidos' => $entero($item['shares'] ?? null),
            'guardados' => $entero($item['collects'] ?? null),
            'portada' => $texto($item['cover_url'] ?? '', 1000),
            'fijado' => !empty($item['is_pinned']) ? 1 : 0,
            'sonido' => $texto($item['music_title'] ?? '', 255),
            'sonido_original' => ($item['is_original_sound'] ?? false) === true ? 1 : 0,
            'cuenta' => $texto($item['author_username'] ?? '', 60),
        ];
    }
}
