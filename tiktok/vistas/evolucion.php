<?php
require_once __DIR__ . '/_formato.php';

$titulo_pagina = 'Evolución';
$subtitulo = 'Cómo crecen las métricas de cada video entre una recolección y otra';
$con_filtro = false;
require __DIR__ . '/_cabecera.php';

$tiene = $historial['capturas'] > 0;
$conVarias = count($serie) >= 2;
?>

<section class="tk-seccion" aria-labelledby="ev-estado">
    <h2 class="tk-seccion-titulo" id="ev-estado">Capturas guardadas</h2>
    <div class="tk-kpis">
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Capturas</span>
            <strong class="tk-kpi-valor"><?php echo $entero($historial['capturas']); ?></strong>
            <span class="tk-kpi-detalle">Cada una es una foto de las métricas de un video</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Videos con historial</span>
            <strong class="tk-kpi-valor"><?php echo $entero($historial['videos']); ?></strong>
            <span class="tk-kpi-detalle">Con al menos una captura</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Primera captura</span>
            <strong class="tk-kpi-valor tk-kpi-valor-chico"><?php echo $fechaHora($historial['primera']); ?></strong>
            <span class="tk-kpi-detalle"><?php echo $entero($historial['fechas']); ?> día<?php echo $historial['fechas'] === 1 ? '' : 's'; ?> con recolección</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Última captura</span>
            <strong class="tk-kpi-valor tk-kpi-valor-chico"><?php echo $fechaHora($historial['ultima']); ?></strong>
            <span class="tk-kpi-detalle">Se guarda una nueva solo si las métricas cambiaron</span>
        </article>
    </div>
</section>

<?php if (!$crecimiento): ?>
<div class="tarjeta">
    <div class="tarjeta-cuerpo tk-vacio">
        <h2>Todavía no hay evolución para mostrar</h2>
        <p>Cada vez que recolectas datos se guarda una captura de las métricas. Con al menos dos capturas de un mismo video, aquí verás cuánto creció y a qué ritmo.</p>
        <?php if ($tiene): ?><p>Ya hay una primera captura: vuelve a recolectar más tarde para ver el cambio.</p><?php endif; ?>
    </div>
</div>
<?php return; endif; ?>

<section class="tk-seccion" aria-labelledby="ev-crecimiento">
    <h2 class="tk-seccion-titulo" id="ev-crecimiento">Los que más crecieron</h2>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo p-0">
            <div class="tabla-responsiva">
                <table class="tabla tk-tabla-videos">
                    <thead><tr><th>Video</th><th>Entre</th><th>Vistas ganadas</th><th>Vistas por hora</th><th>Me gusta</th><th>Compartidos</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($crecimiento as $f): $v = $f['video']; ?>
                        <tr>
                            <td>
                                <div class="tk-video">
                                    <?php if ($v['portada'] !== ''): ?>
                                        <img class="tk-miniatura" src="<?php echo e($v['portada']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <span class="tk-miniatura tk-miniatura-rota"></span>
                                    <?php endif; ?>
                                    <a href="<?php echo e($v['url']); ?>" target="_blank" rel="noopener" class="tk-video-texto"><?php echo e(mb_substr($v['descripcion'] !== '' ? $v['descripcion'] : 'Sin descripción', 0, 70)); ?></a>
                                </div>
                            </td>
                            <td class="tk-secundario"><?php echo $fechaHora($f['desde']); ?><br><?php echo $fechaHora($f['hasta']); ?></td>
                            <td><strong>+<?php echo $entero($f['vistas_ganadas']); ?></strong></td>
                            <td><?php echo $decimal($f['vistas_por_hora'], 1); ?></td>
                            <td>+<?php echo $entero($f['likes_ganados']); ?></td>
                            <td>+<?php echo $entero($f['compartidos_ganados']); ?></td>
                            <td><a class="boton boton-pequeno boton-secundario" href="<?php echo url('tiktok/evolucion') . '?video=' . e($v['id']); ?>">Ver evolución</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php if ($video_elegido && $serie): ?>
<section class="tk-seccion" aria-labelledby="ev-video">
    <h2 class="tk-seccion-titulo" id="ev-video">Evolución de un video</h2>
    <p class="tk-nota tk-nota-superior"><a href="<?php echo e($video_elegido['url']); ?>" target="_blank" rel="noopener"><?php echo e(mb_substr($video_elegido['descripcion'] !== '' ? $video_elegido['descripcion'] : 'Sin descripción', 0, 120)); ?></a> · publicado el <?php echo $fecha($video_elegido['fecha']); ?></p>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Vistas y me gusta en cada captura</h3></div>
            <div class="tarjeta-cuerpo">
                <?php if ($conVarias): ?>
                <div class="tk-lienzo">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'mixto',
                        'etiquetas' => array_map(fn($s) => date('d/m H:i', strtotime($s['capturado_en'])), $serie),
                        'series' => [
                            ['nombre' => 'Vistas', 'tipo' => 'line', 'datos' => array_map(fn($s) => (int)$s['vistas'], $serie), 'color' => 'azul', 'eje' => 'y'],
                            ['nombre' => 'Me gusta', 'tipo' => 'line', 'datos' => array_map(fn($s) => (int)$s['likes'], $serie), 'color' => 'rojo', 'eje' => 'y1'],
                        ],
                    ]); ?>" role="img" aria-label="Evolución de vistas y me gusta del video"></canvas>
                </div>
                <?php else: ?>
                <p class="tk-vacio">Este video solo tiene una captura. Recolecta de nuevo más tarde.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Capturas</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Fecha</th><th>Vistas</th><th>Me gusta</th><th>Coment.</th><th>Compart.</th><th>Guard.</th></tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($serie) as $s): ?>
                            <tr>
                                <td><?php echo $fechaHora($s['capturado_en']); ?></td>
                                <td><strong><?php echo $entero($s['vistas']); ?></strong></td>
                                <td><?php echo $entero($s['likes']); ?></td>
                                <td><?php echo $entero($s['comentarios']); ?></td>
                                <td><?php echo $entero($s['compartidos']); ?></td>
                                <td><?php echo $entero($s['guardados']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
