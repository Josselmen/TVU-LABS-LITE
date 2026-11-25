<?php
require_once __DIR__ . '/_formato.php';

$hayDatos = !empty($resumen['hay_datos']);
$titulo_pagina = 'TikTok' . ($hayDatos ? ' · @' . $resumen['cuenta'] : '');
$subtitulo = $hayDatos
    ? $entero($resumen['total']) . ' videos analizados entre el ' . $fecha($resumen['totales']['primer_video']) . ' y el ' . $fecha($resumen['totales']['ultimo_video'])
    : 'Panel de rendimiento del canal';
require __DIR__ . '/_cabecera.php';
?>

<?php if (!$hayDatos): ?>
<div class="tarjeta">
    <div class="tarjeta-cuerpo tk-vacio">
        <h2>Todavía no hay datos de TikTok</h2>
        <p>Pulsa <strong>Recolectar datos</strong> para traer los videos de la cuenta. La primera vez puede tardar unos minutos.</p>
    </div>
</div>
<?php return; endif; ?>

<?php
$t = $resumen['totales'];
$c = $resumen['comparativa'];
$ex = $resumen['excepciones'];
?>

<?php if ($resumen['ignorando_excepciones']): ?>
<p class="tk-nota tk-nota-modo">
    Cifras y gráficos calculados con <?php echo $entero($resumen['total']); ?> de <?php echo $entero($resumen['total_canal']); ?> videos: se excluyen <?php echo $entero($resumen['total_canal'] - $resumen['total']); ?> excepciones, que siguen visibles más abajo.
</p>
<?php endif; ?>

<section class="tk-kpis" aria-label="Indicadores generales">
    <article class="tk-kpi tk-kpi-principal">
        <span class="tk-kpi-etiqueta">Vistas totales</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['vistas']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $entero($t['vistas']); ?> reproducciones</span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Videos publicados</span>
        <strong class="tk-kpi-valor"><?php echo $entero($t['videos']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $t['fijados']; ?> fijados en el perfil</span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Me gusta</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['likes']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $entero($t['likes']); ?></span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Compartidos</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['compartidos']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $entero($t['compartidos']); ?></span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Guardados</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['guardados']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $entero($t['guardados']); ?></span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Comentarios</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['comentarios']); ?></strong>
        <span class="tk-kpi-detalle"><?php echo $entero($t['comentarios']); ?></span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Interacción</span>
        <strong class="tk-kpi-valor"><?php echo $porcentaje($t['engagement'], 2); ?></strong>
        <span class="tk-kpi-detalle">Me gusta, comentarios, compartidos y guardados sobre vistas</span>
    </article>
    <article class="tk-kpi">
        <span class="tk-kpi-etiqueta">Vistas por video</span>
        <strong class="tk-kpi-valor"><?php echo $compacto($t['vistas_mediana']); ?></strong>
        <span class="tk-kpi-detalle">Mediana · promedio <?php echo $compacto($t['vistas_promedio']); ?></span>
    </article>
</section>

<?php if (!empty($resumen['hallazgos'])): ?>
<section class="tk-seccion" aria-labelledby="tk-hallazgos">
    <h2 class="tk-seccion-titulo" id="tk-hallazgos">Hallazgos principales</h2>
    <div class="tk-hallazgos">
        <?php foreach ($resumen['hallazgos'] as $h): ?>
        <article class="tk-hallazgo">
            <span class="tk-hallazgo-titulo"><?php echo e($h['titulo']); ?></span>
            <strong class="tk-hallazgo-valor"><?php echo e($h['valor']); ?></strong>
            <span class="tk-hallazgo-detalle"><?php echo e($h['detalle']); ?></span>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="tk-nota">Son patrones históricos con tamaño de muestra mínimo de <?php echo Analisis::MINIMO_MUESTRA; ?> videos. Indican qué rindió mejor, no que sea la causa del resultado.</p>
</section>
<?php endif; ?>

<section class="tk-seccion" aria-labelledby="tk-evolucion">
    <h2 class="tk-seccion-titulo" id="tk-evolucion">Evolución del canal</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta tk-ancho">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Vistas y videos publicados por mes</h3></div>
            <div class="tarjeta-cuerpo">
                <div class="tk-lienzo tk-lienzo-alto">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'mixto',
                        'etiquetas' => array_map(fn($m) => $etiquetaMes($m['mes']), $resumen['mensual']),
                        'series' => [
                            ['nombre' => 'Vistas', 'tipo' => 'bar', 'datos' => array_map(fn($m) => (int)$m['vistas'], $resumen['mensual']), 'color' => 'celeste', 'eje' => 'y'],
                            ['nombre' => 'Videos', 'tipo' => 'line', 'datos' => array_map(fn($m) => $m['videos'], $resumen['mensual']), 'color' => 'rojo', 'eje' => 'y1'],
                        ],
                    ]); ?>" role="img" aria-label="Gráfico de vistas y videos por mes"></canvas>
                </div>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Resumen por año</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Año</th><th>Videos</th><th>Vistas</th><th>Mediana</th><th>Interacción</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen['anual'] as $a): ?>
                            <tr>
                                <td><strong><?php echo e($a['anio']); ?></strong></td>
                                <td><?php echo $entero($a['videos']); ?></td>
                                <td><?php echo $compacto($a['vistas']); ?></td>
                                <td><?php echo $compacto($a['mediana']); ?></td>
                                <td><?php echo $porcentaje($a['engagement']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tk-rejilla tk-rejilla-3">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Últimos 30 días</h3></div>
            <div class="tarjeta-cuerpo">
                <dl class="tk-datos">
                    <div><dt>Videos publicados</dt><dd><?php echo $c['actual']['videos']; ?> <span class="tk-secundario">(antes <?php echo $c['anterior']['videos']; ?>)</span></dd></div>
                    <div><dt>Vistas acumuladas</dt><dd><?php echo $compacto($c['actual']['vistas']); ?> <span class="tk-secundario">(antes <?php echo $compacto($c['anterior']['vistas']); ?>)</span></dd></div>
                    <div><dt>Mediana por video</dt><dd><?php echo $compacto($c['actual']['mediana']); ?> <span class="tk-secundario">(antes <?php echo $compacto($c['anterior']['mediana']); ?>)</span></dd></div>
                </dl>
                <p class="tk-nota">Los videos recientes siguen sumando vistas, por eso la comparación favorece a los más antiguos.</p>
            </div>
        </div>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="tk-excepciones">
    <h2 class="tk-seccion-titulo" id="tk-excepciones">Excepciones (one-hit)</h2>
    <?php if ($ex['cantidad'] === 0): ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio">Ningún video supera <?php echo $ex['umbral']; ?> veces la mediana del canal.</div></div>
    <?php else: ?>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Cuánto distorsionan las métricas</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th></th><th>Con excepciones</th><th>Sin excepciones</th></tr></thead>
                        <tbody>
                            <tr><td>Videos</td><td><?php echo $entero($ex['con']['videos']); ?></td><td><?php echo $entero($ex['sin']['videos']); ?></td></tr>
                            <tr><td>Vistas totales</td><td><?php echo $compacto($ex['con']['vistas']); ?></td><td><?php echo $compacto($ex['sin']['vistas']); ?></td></tr>
                            <tr><td>Vistas promedio</td><td><strong><?php echo $compacto($ex['con']['promedio']); ?></strong></td><td><strong><?php echo $compacto($ex['sin']['promedio']); ?></strong></td></tr>
                            <tr><td>Vistas mediana</td><td><?php echo $compacto($ex['con']['mediana']); ?></td><td><?php echo $compacto($ex['sin']['mediana']); ?></td></tr>
                            <tr><td>Interacción</td><td><?php echo $porcentaje($ex['con']['engagement'], 2); ?></td><td><?php echo $porcentaje($ex['sin']['engagement'], 2); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="tk-nota tk-nota-tabla">
                    <?php echo $ex['cantidad']; ?> videos (<?php echo $porcentaje($ex['porcentaje_videos']); ?>) acumulan el <?php echo $porcentaje($ex['porcentaje_vistas']); ?> de las vistas.
                    El promedio se mueve mucho más que la mediana, por eso conviene mirar ambos.
                </p>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo">Videos excepcionales</h3>
                <a class="boton boton-pequeno boton-secundario" href="<?php echo url('tiktok/videos') . '?' . http_build_query(['excepciones' => 'solo', 'orden' => 'vistas', 'umbral' => $opciones['umbral']]); ?>">Ver los <?php echo $ex['cantidad']; ?></a>
            </div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla tk-tabla-videos">
                        <thead><tr><th>Video</th><th>Fecha</th><th>Vistas</th><th>Veces la mediana</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($ex['lista'], 0, 8) as $v): ?>
                            <tr>
                                <td>
                                    <div class="tk-video">
                                        <?php if ($v['portada'] !== ''): ?>
                                            <img class="tk-miniatura" src="<?php echo e($v['portada']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                        <?php else: ?>
                                            <span class="tk-miniatura tk-miniatura-rota"></span>
                                        <?php endif; ?>
                                        <a href="<?php echo e($v['url']); ?>" target="_blank" rel="noopener" class="tk-video-texto"><?php echo e(mb_substr($v['descripcion'] !== '' ? $v['descripcion'] : 'Sin descripción', 0, 80)); ?></a>
                                    </div>
                                </td>
                                <td><?php echo $fecha($v['fecha']); ?></td>
                                <td><strong><?php echo $compacto($v['vistas']); ?></strong></td>
                                <td><?php echo number_format($v['veces_mediana'], 0, ',', '.'); ?>×</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<section class="tk-seccion" aria-labelledby="tk-recientes">
    <h2 class="tk-seccion-titulo" id="tk-recientes">Publicaciones recientes</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaVideos($resumen['recientes'], 'Últimas publicaciones'); ?>
        <?php if (!empty($resumen['fijados'])): ?>
            <?php $tablaVideos(array_map(fn($v) => $v + ['veces_mediana' => ($t['vistas_mediana'] > 0 && $v['vistas'] !== null) ? $v['vistas'] / $t['vistas_mediana'] : null], $resumen['fijados']), 'Fijados en el perfil'); ?>
        <?php endif; ?>
    </div>
    <p class="tk-nota">«Frente a la mediana» compara las vistas del video con la mediana del canal (<?php echo $entero($t['vistas_mediana']); ?> vistas).</p>
</section>
