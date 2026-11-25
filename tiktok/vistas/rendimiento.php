<?php
require_once __DIR__ . '/_formato.php';

$hayDatos = !empty($resumen['hay_datos']);
$titulo_pagina = 'Rendimiento';
$subtitulo = 'Qué publicaciones funcionan mejor, cuáles peor y cuáles se salen de lo habitual';
require __DIR__ . '/_cabecera.php';

if (!$hayDatos): ?>
<div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio"><p>Todavía no hay datos para analizar.</p></div></div>
<?php return; endif;

$r = $resumen['rendimiento'];
$p = $r['promedios'];
$t = $resumen['totales'];
$ext = $resumen['extraordinarias'];
?>

<section class="tk-seccion" aria-labelledby="rd-promedios">
    <h2 class="tk-seccion-titulo" id="rd-promedios">Lo típico de un video</h2>
    <div class="tk-kpis">
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Vistas promedio</span>
            <strong class="tk-kpi-valor"><?php echo $compacto($p['vistas']); ?></strong>
            <span class="tk-kpi-detalle">Mediana <?php echo $compacto($t['vistas_mediana']); ?></span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Interacciones por video</span>
            <strong class="tk-kpi-valor"><?php echo $decimal($p['interacciones'], 1); ?></strong>
            <span class="tk-kpi-detalle">Me gusta + comentarios + compartidos + guardados</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Me gusta por video</span>
            <strong class="tk-kpi-valor"><?php echo $decimal($p['likes'], 1); ?></strong>
            <span class="tk-kpi-detalle">Promedio</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Comentarios por video</span>
            <strong class="tk-kpi-valor"><?php echo $decimal($p['comentarios'], 1); ?></strong>
            <span class="tk-kpi-detalle">Promedio</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Compartidos por video</span>
            <strong class="tk-kpi-valor"><?php echo $decimal($p['compartidos'], 1); ?></strong>
            <span class="tk-kpi-detalle">Promedio</span>
        </article>
        <article class="tk-kpi">
            <span class="tk-kpi-etiqueta">Guardados por video</span>
            <strong class="tk-kpi-valor"><?php echo $decimal($p['guardados'], 1); ?></strong>
            <span class="tk-kpi-detalle">Promedio</span>
        </article>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="rd-records">
    <h2 class="tk-seccion-titulo" id="rd-records">Los que lideran cada métrica</h2>
    <div class="tk-records">
        <?php foreach ($r['records'] as $rec): $v = $rec['video']; ?>
        <article class="tk-record">
            <span class="tk-record-titulo"><?php echo e($rec['titulo']); ?></span>
            <strong class="tk-record-valor"><?php echo $valorFormato($rec['valor'], $rec['formato']); ?></strong>
            <a href="<?php echo e($v['url']); ?>" target="_blank" rel="noopener" class="tk-record-video"><?php echo e(mb_substr($v['descripcion'] !== '' ? $v['descripcion'] : 'Sin descripción', 0, 80)); ?></a>
            <span class="tk-secundario"><?php echo $fecha($v['fecha']); ?> · <?php echo $compacto($v['vistas']); ?> vistas · <?php echo $porcentaje($v['engagement']); ?> de interacción</span>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="tk-nota">No confundas alcance con respuesta: un video con pocas vistas puede tener proporcionalmente más interacción que uno muy visto. «Mayor interacción» exige al menos <?php echo Rendimiento::VISTAS_MINIMAS_INTERACCION; ?> vistas para que el porcentaje sea representativo.</p>
</section>

<section class="tk-seccion" aria-labelledby="rd-ranking">
    <h2 class="tk-seccion-titulo" id="rd-ranking">Ranking de publicaciones</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaVideos($resumen['top_vistas'], 'Mejores: los 10 más vistos'); ?>
        <?php $tablaVideos($resumen['top_engagement'], 'Mejores: mayor interacción (desde 1.000 vistas)', 'engagement'); ?>
    </div>
    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaVideos($r['peores_vistas'], 'Peores: los 10 menos vistos', 'mediana', 'Solo videos con más de ' . $r['dias_ranking'] . ' días, para no penalizar a los que aún están sumando vistas.'); ?>
        <?php $tablaVideos($r['peores_interaccion'], 'Peores: menor interacción (desde ' . Rendimiento::VISTAS_MINIMAS_INTERACCION . ' vistas)', 'engagement', 'Solo videos con más de ' . $r['dias_ranking'] . ' días.'); ?>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="rd-extra">
    <h2 class="tk-seccion-titulo" id="rd-extra">Publicaciones extraordinarias</h2>
    <p class="tk-nota tk-nota-superior">
        Se detectan con el método del rango intercuartílico (IQR) sobre <strong>todos</strong> los videos del canal, sin importar el filtro de excepciones.
        Solo se comparan los <?php echo $entero($ext['maduros']); ?> videos con más de <?php echo $ext['horas_maduracion'] ?? Rendimiento::HORAS_MADURACION; ?> horas;
        <?php echo $entero($ext['en_maduracion']); ?> más reciente<?php echo $ext['en_maduracion'] === 1 ? '' : 's'; ?> todavía <?php echo $ext['en_maduracion'] === 1 ? 'está sumando' : 'están sumando'; ?> vistas.
    </p>
    <?php if (empty($ext['grupos'])): ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio">No hay suficientes videos para calcular lo que es habitual en la cuenta.</div></div>
    <?php else: ?>
    <div class="tk-rejilla tk-rejilla-2">
        <?php foreach ($ext['grupos'] as $g): $res = $g['resultado']; ?>
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo"><?php echo e($g['titulo']); ?></h3>
                <span class="badge badge-alerta"><?php echo $entero($res['total']); ?> video<?php echo $res['total'] === 1 ? '' : 's'; ?></span>
            </div>
            <div class="tarjeta-cuerpo p-0">
                <p class="tk-nota tk-nota-tabla"><?php echo e($g['detalle']); ?> Límite: <strong><?php echo $valorFormato($g['limite'], $g['formato']); ?></strong>.</p>
                <?php if (!$res['lista']): ?>
                    <p class="tk-vacio">Ningún video supera el límite.</p>
                <?php else: ?>
                <div class="tabla-responsiva">
                    <table class="tabla tk-tabla-videos">
                        <thead><tr><th>Video</th><th><?php echo e($g['columna']); ?></th><th>Vistas</th></tr></thead>
                        <tbody>
                        <?php foreach ($res['lista'] as $it): $v = $it['video']; ?>
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
                                <td><strong><?php echo $valorFormato($it['valor'], $g['formato']); ?></strong></td>
                                <td><?php echo $compacto($v['vistas']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="tk-nota">Un video extraordinario indica un rendimiento superior al habitual de la cuenta; no explica por qué ocurrió.</p>
    <?php endif; ?>
</section>

<section class="tk-seccion" aria-labelledby="rd-distribucion">
    <h2 class="tk-seccion-titulo" id="rd-distribucion">Cómo se reparten las vistas</h2>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo">
            <div class="tk-lienzo">
                <canvas data-grafico="<?php echo $grafico([
                    'tipo' => 'barra',
                    'etiquetas' => array_column($resumen['distribucion_vistas'], 'etiqueta'),
                    'series' => [['nombre' => 'Videos', 'datos' => array_column($resumen['distribucion_vistas'], 'videos'), 'color' => 'rojo']],
                ]); ?>" role="img" aria-label="Cantidad de videos por rango de vistas"></canvas>
            </div>
        </div>
    </div>
</section>
