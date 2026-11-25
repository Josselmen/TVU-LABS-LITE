<?php

class Actualizador
{
    public const CAPTURADOR = '/recursos/capcher.php';
    public const EJECUTOR = '/tiktok/ejecutor.php';
    public const REGISTRO = '/recursos/actualizacion.log';

    public static ?string $capturador = null;
    public static ?string $registro = null;

    public static function cuentaValida(string $cuenta): bool
    {
        return preg_match('/^[A-Za-z0-9._]{2,40}$/', $cuenta) === 1;
    }

    public static function cuenta(): string
    {
        $cuenta = (string)(configuracion('tiktok')['cuenta'] ?? '');
        return self::cuentaValida($cuenta) ? $cuenta : 'tvu_umsa_lp';
    }

    private static function rutaCapturador(): string
    {
        return self::$capturador ?? RAIZ . self::CAPTURADOR;
    }

    private static function rutaRegistro(): string
    {
        return self::$registro ?? RAIZ . self::REGISTRO;
    }

    private static function minutosMaximos(): int
    {
        return (int)(configuracion('tiktok')['minutos_maximos'] ?? 45);
    }

    public static function estado(): array
    {
        $fila = bd()->query('SELECT * FROM actualizaciones ORDER BY id DESC LIMIT 1')->fetch();
        if (!$fila) {
            return ['estado' => 'inactivo', 'etapa' => '', 'mensaje' => '', 'inicio' => null, 'fin' => null, 'videos_total' => null, 'videos_nuevos' => null];
        }

        if ($fila['estado'] === 'ejecutando' && strtotime($fila['latido']) < time() - self::minutosMaximos() * 60) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'La recolección dejó de responder y se dio por cancelada.';
            bd()->prepare("UPDATE actualizaciones SET estado = 'error', mensaje = :mensaje, fin = NOW() WHERE id = :id")
                ->execute(['mensaje' => $fila['mensaje'], 'id' => $fila['id']]);
        }

        return $fila;
    }

    public static function ultimaCompletada(): ?string
    {
        $fecha = bd()->query("SELECT MAX(fin) FROM actualizaciones WHERE estado IN ('completado', 'parcial')")->fetchColumn();
        return $fecha ?: null;
    }

    public static function enEjecucion(): bool
    {
        return self::estado()['estado'] === 'ejecutando';
    }

    public static function iniciar(?int $usuarioId): array
    {
        if (self::enEjecucion()) {
            return ['exito' => false, 'error' => 'Ya hay una recolección en curso.'];
        }

        if (!is_file(self::rutaCapturador())) {
            return ['exito' => false, 'error' => 'No se encontró recursos/capcher.php.'];
        }

        $insertar = bd()->prepare("INSERT INTO actualizaciones (inicio, estado, etapa, mensaje, latido, usuario_id) VALUES (NOW(), 'ejecutando', 'iniciando', 'Recuperados: 0', NOW(), :usuario)");
        $insertar->execute(['usuario' => $usuarioId]);
        $id = (int)bd()->lastInsertId();

        if (!self::lanzarSegundoPlano(self::binarioPhp(), [RAIZ . self::EJECUTOR, (string)$id])) {
            self::marcar($id, 'error', 'iniciando', 'No se pudo iniciar la recolección en este servidor.', true);
            return ['exito' => false, 'error' => 'No se pudo iniciar la recolección en este servidor.'];
        }

        return ['exito' => true, 'id' => $id];
    }

    public static function binarioPhp(): string
    {
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        $nombre = DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php';
        $candidatos = [];
        $ini = php_ini_loaded_file();
        if ($ini) {
            $candidatos[] = dirname($ini) . DIRECTORY_SEPARATOR . $nombre;
        }
        $candidatos[] = PHP_BINDIR . DIRECTORY_SEPARATOR . $nombre;
        $candidatos[] = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $nombre;
        $candidatos[] = '/usr/bin/php';
        $candidatos[] = '/usr/local/bin/php';

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return 'php';
    }

    public static function lanzarSegundoPlano(string $php, array $argumentos): bool
    {
        $comando = implode(' ', array_map('escapeshellarg', array_merge([$php], $argumentos)));

        if (DIRECTORY_SEPARATOR === '\\') {
            $proceso = @popen('start /B "" ' . $comando . ' > NUL 2>&1', 'r');
            if ($proceso === false) {
                return false;
            }
            pclose($proceso);
            return true;
        }

        @exec('nohup ' . $comando . ' > /dev/null 2>&1 &', $salida, $codigo);
        return $codigo === 0;
    }

    public static function ejecutar(int $id): array
    {
        @set_time_limit(0);

        try {
            self::marcar($id, 'ejecutando', 'capturando', 'Recuperados: 0');
            $captura = self::capturar($id, self::cuenta());

            if (!$captura['exito']) {
                return self::marcar($id, 'error', 'capturando', $captura['mensaje'], true);
            }

            self::marcar($id, 'ejecutando', 'guardando', $captura['mensaje']);
            $importacion = VideosModelo::importar();

            if (!$importacion['exito']) {
                return self::marcar($id, 'parcial', 'guardando', $importacion['error'], true);
            }

            bd()->prepare('UPDATE actualizaciones SET videos_total = :total, videos_nuevos = :nuevos WHERE id = :id')
                ->execute(['total' => $importacion['total'], 'nuevos' => $captura['recuperados'], 'id' => $id]);

            // Si la recolección quedó a medias (corte, límite de tiempo), se guarda lo avanzado y se avisa como parcial.
            return self::marcar($id, $captura['parcial'] ? 'parcial' : 'completado', 'completado', $captura['mensaje'], true);
        } catch (Throwable $e) {
            error_log('Actualizador::ejecutar - ' . $e->getMessage());
            return self::marcar($id, 'error', 'error', 'La recolección falló por un error interno.', true);
        }
    }

    public static function capturar(int $id, string $cuenta): array
    {
        if (!self::cuentaValida($cuenta)) {
            return ['exito' => false, 'mensaje' => 'El nombre de la cuenta de TikTok no es válido.'];
        }

        $script = self::rutaCapturador();
        if (!is_file($script)) {
            return ['exito' => false, 'mensaje' => 'No se encontró el programa de captura.'];
        }

        $datos = VideosModelo::rutaJson();
        $respaldo = (string)preg_replace('/\.json$/', '.respaldo.json', $datos);
        $antes = count(VideosModelo::leerJson($datos) ?? []);
        if (is_file($datos)) {
            @copy($datos, $respaldo);
        }

        $registro = self::rutaRegistro();
        file_put_contents($registro, '[' . date('Y-m-d H:i:s') . "] Inicio de la captura de @$cuenta\n");

        // El contador de "recuperados" es de esta corrida: se borra el de la anterior antes de empezar.
        self::reiniciarContador($script);

        $descriptores = [0 => ['pipe', 'r'], 1 => ['file', $registro, 'a'], 2 => ['file', $registro, 'a']];
        $proceso = @proc_open([PHP_BINARY, basename($script), '--user=' . $cuenta], $descriptores, $tuberias, dirname($script));
        if (!is_resource($proceso)) {
            return ['exito' => false, 'mensaje' => 'No se pudo iniciar el programa de captura.'];
        }
        fclose($tuberias[0]);

        $limite = time() + self::minutosMaximos() * 60;
        $codigo = null;
        $agotado = false;
        while (true) {
            $estado = proc_get_status($proceso);
            if (!$estado['running']) {
                $codigo = $estado['exitcode'];
                break;
            }
            if (time() > $limite) {
                proc_terminate($proceso);
                proc_close($proceso);
                $agotado = true;
                break;
            }
            self::marcar($id, 'ejecutando', 'capturando', 'Recuperados: ' . self::estadoRecoleccion($script)['recuperados']);
            sleep(2);
        }
        if (!$agotado) {
            proc_close($proceso);
        }

        $despues = VideosModelo::leerJson($datos);
        $cuantos = $despues === null ? 0 : count($despues);

        // Un archivo ilegible o con menos videos que antes nunca reemplaza al respaldo.
        if ($cuantos === 0 || $cuantos < $antes) {
            self::restaurar($datos, $respaldo);
            return ['exito' => false, 'mensaje' => $agotado
                ? 'La recolección superó el tiempo máximo y se canceló. Se conservaron los datos anteriores.'
                : 'El archivo resultante tenía menos videos que antes. Se restauró el respaldo.'];
        }

        $recolectados = self::estadoRecoleccion($script);
        $recuperados = $recolectados['existe'] ? $recolectados['recuperados'] : max(0, $cuantos - $antes);
        $mensaje = 'Recuperados: ' . $recuperados;

        // El programa guarda su avance cada pocos videos, así que un corte conserva lo ya recuperado.
        if ($agotado) {
            return ['exito' => true, 'parcial' => true, 'recuperados' => $recuperados, 'mensaje' => $mensaje];
        }

        if ($codigo !== 0 && $codigo !== -1) {
            if ($codigo === 2 || $cuantos === $antes && $recuperados === 0) {
                self::restaurar($datos, $respaldo);
                return ['exito' => false, 'mensaje' => $codigo === 2
                    ? 'TikTok no devolvió videos (posible bloqueo o captcha). Se conservaron los datos anteriores.'
                    : 'El programa de captura terminó con un error. Se conservaron los datos anteriores.'];
            }
            return ['exito' => true, 'parcial' => true, 'recuperados' => $recuperados, 'mensaje' => $mensaje];
        }

        return ['exito' => true, 'parcial' => false, 'recuperados' => $recuperados, 'mensaje' => $mensaje];
    }

    /** Progreso que escribe capcher.php (recoleccion_estado.json), junto al propio programa. */
    public static function estadoRecoleccion(?string $script = null): array
    {
        $archivo = dirname($script ?? self::rutaCapturador()) . '/recoleccion_estado.json';
        $datos = is_file($archivo) ? json_decode((string)file_get_contents($archivo), true) : null;

        return [
            'existe' => is_array($datos),
            'recuperados' => (int)($datos['recuperados'] ?? 0),
            'faltan' => (bool)($datos['faltan'] ?? false),
            'pendientes' => (int)($datos['pendientes'] ?? 0),
        ];
    }

    private static function reiniciarContador(string $script): void
    {
        $archivo = dirname($script) . '/recoleccion_estado.json';
        $datos = is_file($archivo) ? json_decode((string)file_get_contents($archivo), true) : null;
        if (is_array($datos)) {
            $datos['recuperados'] = 0;
            file_put_contents($archivo, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private static function restaurar(string $datos, string $respaldo): void
    {
        if (is_file($respaldo)) {
            @copy($respaldo, $datos);
        }
    }

    private static function marcar(int $id, string $estado, string $etapa, string $mensaje, bool $final = false): array
    {
        $sql = 'UPDATE actualizaciones SET estado = :estado, etapa = :etapa, mensaje = :mensaje, latido = NOW()'
            . ($final ? ', fin = NOW()' : '') . ' WHERE id = :id';
        bd()->prepare($sql)->execute(['estado' => $estado, 'etapa' => $etapa, 'mensaje' => mb_substr($mensaje, 0, 500), 'id' => $id]);

        return ['estado' => $estado, 'etapa' => $etapa, 'mensaje' => $mensaje];
    }
}
