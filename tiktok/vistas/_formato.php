<?php
// Funciones de formato y piezas comunes de las pantallas de TikTok.
// Se incluye con require_once desde cada vista (comparten el mismo ámbito).

$entero = fn($n) => $n === null ? '—' : number_format((float)$n, 0, ',', '.');
$decimal = fn($n, $d = 1) => $n === null ? '—' : number_format((float)$n, $d, ',', '.');
$compacto = function ($n) {
    if ($n === null) {
        return '—';
    }
    $n = (float)$n;
    if ($n >= 1000000) {
        return number_format($n / 1000000, 1, ',', '.') . ' M';
    }
    if ($n >= 10000) {
        return number_format($n / 1000, 0, ',', '.') . ' mil';
    }
    if ($n >= 1000) {
        return number_format($n / 1000, 1, ',', '.') . ' mil';
    }
    return number_format($n, 0, ',', '.');
};
$corto = function ($n) {
    if ($n === null) {
        return '';
    }
    $n = (float)$n;
    if ($n >= 1000000) {
        return number_format($n / 1000000, 1, ',', '.') . 'M';
    }
    if ($n >= 1000) {
        return number_format($n / 1000, $n >= 10000 ? 0 : 1, ',', '.') . 'k';
    }
    return (string)round($n);
};
$porcentaje = fn($x, $d = 1) => $x === null ? '—' : number_format($x * 100, $d, ',', '.') . ' %';
$fecha = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$fechaHora = fn($f) => $f ? date('d/m/Y H:i', strtotime($f)) : '—';
$duracion = function ($s) {
    if ($s === null) {
        return '—';
    }
    return $s >= 60 ? intdiv($s, 60) . ':' . sprintf('%02d', $s % 60) : $s . ' s';
};
$mesesNombre = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$etiquetaMes = fn($m) => ($mesesNombre[substr($m, 5, 2)] ?? '') . ' ' . substr($m, 2, 2);
$grafico = fn(array $config) => e(json_encode($config, JSON_UNESCAPED_UNICODE));
$valorFormato = fn($valor, string $formato) => $formato === 'porcentaje' ? $porcentaje($valor, 2) : $entero($valor);

/** Dirección de una pantalla de TikTok conservando el filtro de excepciones elegido. */
$enlaceTk = function (string $ruta, array $extra = []) use ($opciones) {
    $parametros = [];
    if (!$opciones['ignorar_excepciones']) {
        $parametros['excepciones'] = 'todos';
    }
    if ($opciones['umbral'] !== Analisis::UMBRAL_EXCEPCION_DEFECTO) {
        $parametros['umbral'] = $opciones['umbral'];
    }
    $parametros = array_merge($parametros, $extra);
    return url($ruta) . ($parametros ? '?' . http_build_query($parametros) : '');
};

/** Tabla de videos con miniatura. $columna: 'mediana' (veces la mediana) o 'engagement'. */
$tablaVideos = function (array $lista, string $titulo, string $columna = 'mediana', string $nota = '') use ($entero, $compacto, $porcentaje, $fecha) {
    ?>
    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo"><?php echo e($titulo); ?></h3></div>
        <div class="tarjeta-cuerpo p-0">
            <?php if (!$lista): ?>
                <p class="tk-vacio">No hay videos para mostrar.</p>
            <?php else: ?>
            <div class="tabla-responsiva">
                <table class="tabla tk-tabla-videos">
                    <thead>
                        <tr><th>Video</th><th>Fecha</th><th>Vistas</th><th><?php echo $columna === 'engagement' ? 'Interacción' : 'Frente a la mediana'; ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lista as $v): ?>
                        <tr>
                            <td>
                                <div class="tk-video">
                                    <?php if ($v['portada'] !== ''): ?>
                                        <img class="tk-miniatura" src="<?php echo e($v['portada']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <span class="tk-miniatura tk-miniatura-rota"></span>
                                    <?php endif; ?>
                                    <a href="<?php echo e($v['url']); ?>" target="_blank" rel="noopener" class="tk-video-texto"><?php echo e(mb_substr($v['descripcion'] !== '' ? $v['descripcion'] : 'Sin descripción', 0, 90)); ?></a>
                                </div>
                            </td>
                            <td><?php echo $fecha($v['fecha']); ?></td>
                            <td><strong><?php echo $compacto($v['vistas']); ?></strong></td>
                            <td>
                                <?php if ($columna === 'engagement'): ?>
                                    <?php echo $porcentaje($v['engagement']); ?>
                                <?php else: ?>
                                    <?php echo ($v['veces_mediana'] ?? null) !== null ? number_format($v['veces_mediana'], 1, ',', '.') . '×' : '—'; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php if ($nota !== ''): ?><p class="tk-nota tk-nota-tabla"><?php echo e($nota); ?></p><?php endif; ?>
        </div>
    </div>
    <?php
};
