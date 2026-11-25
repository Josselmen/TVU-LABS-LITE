<?php
require_once __DIR__ . '/_formato.php';

$hayDatos = !empty($resumen['hay_datos']);
$titulo_pagina = 'Horarios';
$subtitulo = 'Cuándo se publica y cuándo mejor responde la audiencia';
require __DIR__ . '/_cabecera.php';

if (!$hayDatos): ?>
<div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio"><p>Todavía no hay datos para analizar.</p></div></div>
<?php return; endif;

$fd = $resumen['franjas_dia'];
$mejorV = $fd['mejor_vistas'];
$mejorI = $fd['mejor_interaccion'];
$mejorDia = null;
$diasValidos = array_filter($resumen['dias'], fn($d) => $d['videos'] >= Analisis::MINIMO_MUESTRA && $d['mediana'] !== null);
if ($diasValidos) {
    usort($diasValidos, fn($a, $b) => $b['mediana'] <=> $a['mediana']);
    $mejorDia = $diasValidos[0];
}
?>

<section class="tk-seccion" aria-labelledby="hr-recomendacion">
    <h2 class="tk-seccion-titulo" id="hr-recomendacion">Lo que dicen los datos</h2>
    <div class="tk-hallazgos">
        <article class="tk-hallazgo">
            <span class="tk-hallazgo-titulo">Momento del día con más vistas</span>
            <strong class="tk-hallazgo-valor"><?php echo $mejorV ? e($mejorV['etiqueta']) : 'Sin muestra suficiente'; ?></strong>
            <span class="tk-hallazgo-detalle"><?php echo $mejorV ? 'Mediana de ' . $entero($mejorV['mediana']) . ' vistas en ' . $mejorV['videos'] . ' videos (' . e($mejorV['rango']) . ').' : 'Se necesitan al menos ' . Analisis::MINIMO_MUESTRA . ' videos por franja.'; ?></span>
        </article>
        <article class="tk-hallazgo">
            <span class="tk-hallazgo-titulo">Momento del día con más interacción</span>
            <strong class="tk-hallazgo-valor"><?php echo $mejorI ? e($mejorI['etiqueta']) : 'Sin muestra suficiente'; ?></strong>
            <span class="tk-hallazgo-detalle"><?php echo $mejorI ? $porcentaje($mejorI['engagement'], 2) . ' de interacción en ' . $mejorI['videos'] . ' videos (' . e($mejorI['rango']) . ').' : ''; ?></span>
        </article>
        <article class="tk-hallazgo">
            <span class="tk-hallazgo-titulo">Día con más vistas</span>
            <strong class="tk-hallazgo-valor"><?php echo $mejorDia ? e($mejorDia['nombre']) : 'Sin muestra suficiente'; ?></strong>
            <span class="tk-hallazgo-detalle"><?php echo $mejorDia ? 'Mediana de ' . $entero($mejorDia['mediana']) . ' vistas en ' . $mejorDia['videos'] . ' videos.' : ''; ?></span>
        </article>
    </div>
    <?php if ($mejorV): ?>
    <p class="tk-nota">
        Las publicaciones realizadas durante la <strong><?php echo e(mb_strtolower($mejorV['etiqueta'])); ?></strong> presentan la mayor mediana de visualizaciones en el período analizado.
        Es un patrón histórico, no una garantía: otras variables (tema, duración, el día) también influyen, y las franjas con pocos videos no se comparan.
    </p>
    <?php endif; ?>
</section>

<section class="tk-seccion" aria-labelledby="hr-franjas">
    <h2 class="tk-seccion-titulo" id="hr-franjas">Madrugada, mañana, tarde y noche</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Mediana de vistas por momento del día</h3></div>
            <div class="tarjeta-cuerpo">
                <div class="tk-lienzo">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'barra',
                        'etiquetas' => array_map(fn($f) => $f['etiqueta'] . ' (' . $f['videos'] . ')', $fd['franjas']),
                        'series' => [['nombre' => 'Mediana de vistas', 'datos' => array_map(fn($f) => (int)$f['mediana'], $fd['franjas']), 'color' => 'celeste']],
                    ]); ?>" role="img" aria-label="Mediana de vistas por momento del día"></canvas>
                </div>
                <p class="tk-nota">Entre paréntesis, la cantidad de videos publicados en cada franja.</p>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Comparación por franja</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Franja</th><th>Videos</th><th>Mediana</th><th>Interacción</th><th>Coment. prom.</th><th>Compart. prom.</th></tr></thead>
                        <tbody>
                        <?php foreach ($fd['franjas'] as $f): ?>
                            <tr>
                                <td><strong><?php echo e($f['etiqueta']); ?></strong><br><span class="tk-secundario"><?php echo e($f['rango']); ?></span></td>
                                <td><?php echo $entero($f['videos']); ?><?php if (!$f['confiable']): ?> <span class="badge badge-info" title="Menos de <?php echo Analisis::MINIMO_MUESTRA; ?> videos: no se compara">poca muestra</span><?php endif; ?></td>
                                <td><?php echo $compacto($f['mediana']); ?></td>
                                <td><?php echo $porcentaje($f['engagement']); ?></td>
                                <td><?php echo $decimal($f['comentarios_prom'], 1); ?></td>
                                <td><?php echo $decimal($f['compartidos_prom'], 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="hr-dias">
    <h2 class="tk-seccion-titulo" id="hr-dias">Días de la semana</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Mediana de vistas por día</h3></div>
            <div class="tarjeta-cuerpo">
                <div class="tk-lienzo">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'barra',
                        'etiquetas' => array_column($resumen['dias'], 'nombre'),
                        'series' => [['nombre' => 'Mediana de vistas', 'datos' => array_map(fn($d) => (int)$d['mediana'], $resumen['dias']), 'color' => 'azul']],
                    ]); ?>" role="img" aria-label="Mediana de vistas por día de la semana"></canvas>
                </div>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Comparación por día</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Día</th><th>Videos</th><th>Mediana</th><th>Interacción</th><th>Coment. prom.</th><th>Compart. prom.</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen['dias'] as $d): ?>
                            <tr>
                                <td><strong><?php echo e($d['nombre']); ?></strong></td>
                                <td><?php echo $entero($d['videos']); ?></td>
                                <td><?php echo $compacto($d['mediana']); ?></td>
                                <td><?php echo $porcentaje($d['engagement']); ?></td>
                                <td><?php echo $decimal($d['comentarios_prom'], 1); ?></td>
                                <td><?php echo $decimal($d['compartidos_prom'], 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="hr-calor">
    <h2 class="tk-seccion-titulo" id="hr-calor">Mapa de calor</h2>
    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <h3 class="tarjeta-titulo">Mediana de vistas por día y hora</h3>
            <span class="tk-leyenda" aria-hidden="true">
                Menos <span class="tk-calor-muestra" data-nivel="1"></span><span class="tk-calor-muestra" data-nivel="2"></span><span class="tk-calor-muestra" data-nivel="3"></span><span class="tk-calor-muestra" data-nivel="4"></span><span class="tk-calor-muestra" data-nivel="5"></span> Más
            </span>
        </div>
        <div class="tarjeta-cuerpo">
            <div class="tk-calor-contenedor">
                <table class="tk-calor-tabla">
                    <caption class="tk-solo-lectores">Mediana de vistas por día de la semana y hora de publicación (hora de La Paz)</caption>
                    <thead>
                        <tr>
                            <th scope="col"></th>
                            <?php for ($h = 0; $h < 24; $h++): ?>
                                <th scope="col"><?php echo sprintf('%02d', $h); ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dias as $d => $nombreDia): ?>
                        <tr>
                            <th scope="row"><?php echo e(mb_substr($nombreDia, 0, 3)); ?></th>
                            <?php for ($h = 0; $h < 24; $h++): $celda = $resumen['mapa_calor'][$d][$h]; ?>
                                <td class="tk-calor" data-nivel="<?php echo (int)$celda['nivel']; ?>" title="<?php echo e($nombreDia . ' ' . sprintf('%02d:00', $h) . ' · ' . $celda['videos'] . ' videos' . ($celda['mediana'] !== null ? ' · mediana ' . $entero($celda['mediana']) . ' vistas' : '')); ?>"><?php echo $celda['nivel'] > 0 ? $corto($celda['mediana']) : ''; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="tk-nota">Hora local de La Paz. Las celdas con menos de 3 videos no se colorean.</p>
        </div>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Mejores franjas de 3 horas</h3></div>
        <div class="tarjeta-cuerpo p-0">
            <?php if (empty($resumen['franjas'])): ?>
                <p class="tk-vacio">No hay franjas con muestra suficiente (se piden <?php echo Analisis::MINIMO_MUESTRA * 2; ?> videos).</p>
            <?php else: ?>
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead><tr><th>#</th><th>Día</th><th>Franja</th><th>Videos</th><th>Mediana</th></tr></thead>
                    <tbody>
                    <?php foreach ($resumen['franjas'] as $i => $f): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo e($f['dia']); ?></strong></td>
                            <td><?php echo e($f['franja']); ?></td>
                            <td><?php echo $f['videos']; ?></td>
                            <td><?php echo $compacto($f['mediana']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="hr-duracion">
    <h2 class="tk-seccion-titulo" id="hr-duracion">Duración de los videos</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Mediana de vistas por duración</h3></div>
            <div class="tarjeta-cuerpo">
                <div class="tk-lienzo">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'barra',
                        'etiquetas' => array_map(fn($d) => $d['etiqueta'] . ' (' . $d['videos'] . ')', $resumen['duraciones']),
                        'series' => [['nombre' => 'Mediana de vistas', 'datos' => array_map(fn($d) => (int)$d['mediana'], $resumen['duraciones']), 'color' => 'celeste']],
                    ]); ?>" role="img" aria-label="Mediana de vistas por rango de duración"></canvas>
                </div>
                <p class="tk-nota">Entre paréntesis, la cantidad de videos de cada rango.</p>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Comparación por duración</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Duración</th><th>Videos</th><th>Mediana</th><th>Interacción</th><th>Me gusta</th><th>Compartidos</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen['duraciones'] as $d): ?>
                            <tr>
                                <td><strong><?php echo e($d['etiqueta']); ?></strong></td>
                                <td><?php echo $entero($d['videos']); ?></td>
                                <td><?php echo $compacto($d['mediana']); ?></td>
                                <td><?php echo $porcentaje($d['engagement']); ?></td>
                                <td><?php echo $compacto($d['likes']); ?></td>
                                <td><?php echo $compacto($d['compartidos']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="tk-nota tk-nota-tabla">No se puede saber si la gente termina de ver los videos: faltan métricas de retención y tiempo de reproducción.</p>
            </div>
        </div>
    </div>
</section>
