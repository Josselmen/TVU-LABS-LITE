<?php

/**
 * Evolución en el tiempo: cada vez que se guardan los videos, se registra una «captura» de sus métricas
 * si cambiaron desde la última. Con eso se calcula cuánto crece cada video entre recolecciones.
 */
class Historial
{
    private static bool $verificada = false;

    /** Crea la tabla si no existe. Debe llamarse fuera de una transacción (CREATE TABLE confirma la transacción). */
    public static function asegurarTabla(): void
    {
        if (self::$verificada) {
            return;
        }

        $pdo = bd();
        $existe = $pdo->query("SHOW TABLES LIKE 'metricas_historial'")->fetchColumn();
        if (!$existe) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS metricas_historial (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                video_id VARCHAR(25) NOT NULL,
                capturado_en DATETIME NOT NULL,
                vistas INT UNSIGNED NULL,
                likes INT UNSIGNED NULL,
                comentarios INT UNSIGNED NULL,
                compartidos INT UNSIGNED NULL,
                guardados INT UNSIGNED NULL,
                INDEX idx_historial_video (video_id, capturado_en),
                INDEX idx_historial_fecha (capturado_en)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        self::$verificada = true;
    }

    private static function tablaLista(): bool
    {
        if (self::$verificada) {
            return true;
        }
        return (bool)bd()->query("SHOW TABLES LIKE 'metricas_historial'")->fetchColumn();
    }

    /**
     * Guarda una captura por cada video cuyas métricas cambiaron (o que aún no tenía ninguna).
     * Devuelve cuántas capturas nuevas se registraron.
     */
    public static function registrar(array $filas): int
    {
        if (!$filas || !self::tablaLista()) {
            return 0;
        }

        $pdo = bd();
        $ultimas = [];
        $consulta = $pdo->query('SELECT h.video_id, h.vistas, h.likes, h.comentarios, h.compartidos, h.guardados
            FROM metricas_historial h
            JOIN (SELECT MAX(id) AS id FROM metricas_historial GROUP BY video_id) u ON u.id = h.id');
        foreach ($consulta->fetchAll() as $u) {
            $ultimas[$u['video_id']] = $u;
        }

        $insertar = $pdo->prepare('INSERT INTO metricas_historial (video_id, capturado_en, vistas, likes, comentarios, compartidos, guardados)
            VALUES (:video_id, NOW(), :vistas, :likes, :comentarios, :compartidos, :guardados)');

        $campos = ['vistas', 'likes', 'comentarios', 'compartidos', 'guardados'];
        $nuevas = 0;
        foreach ($filas as $f) {
            if ($f['vistas'] === null) {
                continue;
            }
            $previa = $ultimas[$f['video_id']] ?? null;
            if ($previa !== null) {
                $igual = true;
                foreach ($campos as $c) {
                    if ($previa[$c] !== null && (int)$previa[$c] !== (int)$f[$c]) {
                        $igual = false;
                        break;
                    }
                }
                if ($igual) {
                    continue;
                }
            }
            $insertar->execute([
                'video_id' => $f['video_id'], 'vistas' => $f['vistas'], 'likes' => $f['likes'],
                'comentarios' => $f['comentarios'], 'compartidos' => $f['compartidos'], 'guardados' => $f['guardados'],
            ]);
            $nuevas++;
        }

        return $nuevas;
    }

    public static function resumen(): array
    {
        if (!self::tablaLista()) {
            return ['capturas' => 0, 'videos' => 0, 'primera' => null, 'ultima' => null, 'fechas' => 0];
        }

        $fila = bd()->query('SELECT COUNT(*) AS capturas, COUNT(DISTINCT video_id) AS videos, MIN(capturado_en) AS primera,
            MAX(capturado_en) AS ultima, COUNT(DISTINCT DATE(capturado_en)) AS fechas FROM metricas_historial')->fetch();

        return [
            'capturas' => (int)$fila['capturas'], 'videos' => (int)$fila['videos'],
            'primera' => $fila['primera'], 'ultima' => $fila['ultima'], 'fechas' => (int)$fila['fechas'],
        ];
    }

    /** Capturas de un video, de la más antigua a la más reciente. */
    public static function serie(string $videoId): array
    {
        if (!self::tablaLista()) {
            return [];
        }
        $q = bd()->prepare('SELECT capturado_en, vistas, likes, comentarios, compartidos, guardados
            FROM metricas_historial WHERE video_id = :id ORDER BY capturado_en, id');
        $q->execute(['id' => $videoId]);
        return $q->fetchAll();
    }

    /**
     * Videos que más crecieron entre su primera y su última captura (solo los que tienen al menos dos).
     * Cada fila: vistas ganadas, horas transcurridas y ritmo por hora.
     */
    public static function crecimiento(int $limite = 10): array
    {
        if (!self::tablaLista()) {
            return [];
        }

        $sql = 'SELECT p.video_id,
                    p.capturado_en AS desde, u.capturado_en AS hasta,
                    p.vistas AS vistas_antes, u.vistas AS vistas_ahora,
                    p.likes AS likes_antes, u.likes AS likes_ahora,
                    p.compartidos AS compartidos_antes, u.compartidos AS compartidos_ahora,
                    TIMESTAMPDIFF(MINUTE, p.capturado_en, u.capturado_en) AS minutos,
                    (SELECT COUNT(*) FROM metricas_historial c WHERE c.video_id = p.video_id) AS capturas
                FROM metricas_historial p
                JOIN (SELECT video_id, MIN(id) AS id FROM metricas_historial GROUP BY video_id) pm ON pm.id = p.id
                JOIN (SELECT video_id, MAX(id) AS id FROM metricas_historial GROUP BY video_id) um ON um.video_id = p.video_id
                JOIN metricas_historial u ON u.id = um.id
                WHERE um.id <> pm.id AND u.vistas IS NOT NULL AND p.vistas IS NOT NULL
                ORDER BY (u.vistas - p.vistas) DESC
                LIMIT ' . max(1, min(50, $limite));

        $filas = [];
        foreach (bd()->query($sql)->fetchAll() as $f) {
            $horas = max(1, (int)$f['minutos']) / 60;
            $ganadas = (int)$f['vistas_ahora'] - (int)$f['vistas_antes'];
            $filas[] = [
                'video_id' => $f['video_id'],
                'desde' => $f['desde'], 'hasta' => $f['hasta'],
                'horas' => $horas,
                'capturas' => (int)$f['capturas'],
                'vistas_ganadas' => $ganadas,
                'likes_ganados' => (int)$f['likes_ahora'] - (int)$f['likes_antes'],
                'compartidos_ganados' => (int)$f['compartidos_ahora'] - (int)$f['compartidos_antes'],
                'vistas_por_hora' => $ganadas / $horas,
            ];
        }
        return array_values(array_filter($filas, fn($f) => $f['vistas_ganadas'] > 0));
    }
}
