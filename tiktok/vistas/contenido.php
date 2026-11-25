<?php
require_once __DIR__ . '/_formato.php';

$hayDatos = !empty($resumen['hay_datos']);
$titulo_pagina = 'Contenido';
$subtitulo = 'Qué temas se publican, cuáles rinden más y qué hashtags los acompañan';
require __DIR__ . '/_cabecera.php';

if (!$hayDatos): ?>
<div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio"><p>Todavía no hay datos para analizar.</p></div></div>
<?php return; endif;

$cl = $resumen['clasificacion'];
$categorias = $cl['categorias'];
$ht = $resumen['hashtags'];
$totalVideos = $resumen['total'];

/** Categoría que lidera una métrica entre las que tienen muestra suficiente (Otros no cuenta). */
$lider = function (string $campo, bool $mayor = true) use ($categorias) {
    $validas = array_values(array_filter($categorias, fn($c) => $c['confiable'] && $c['nombre'] !== Clasificador::SIN_CATEGORIA && $c[$campo] !== null));
    if (!$validas) {
        return null;
    }
    usort($validas, fn($a, $b) => $mayor ? $b[$campo] <=> $a[$campo] : $a[$campo] <=> $b[$campo]);
    return $validas[0];
};
$respuestas = [
    ['¿Qué categoría genera más vistas?', $lider('mediana'), fn($c) => $compacto($c['mediana']) . ' de mediana'],
    ['¿Cuál tiene mayor interacción?', $lider('engagement'), fn($c) => $porcentaje($c['engagement'], 2)],
    ['¿Cuál recibe más comentarios por video?', $lider('comentarios_prom'), fn($c) => $decimal($c['comentarios_prom'], 1) . ' por video'],
    ['¿Cuál se comparte más?', $lider('compartidos_prom'), fn($c) => $decimal($c['compartidos_prom'], 1) . ' por video'],
    ['¿Qué tema se publica con más frecuencia?', $lider('videos'), fn($c) => $entero($c['videos']) . ' videos (' . $porcentaje($c['porcentaje']) . ')'],
    ['¿Cuál tiene menor rendimiento?', $lider('mediana', false), fn($c) => $compacto($c['mediana']) . ' de mediana'],
];

$tablaHashtags = function (array $lista, string $titulo, array $columnas) use ($entero, $compacto, $porcentaje, $decimal) {
    ?>
    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo"><?php echo e($titulo); ?></h3></div>
        <div class="tarjeta-cuerpo p-0">
            <?php if (!$lista): ?>
                <p class="tk-vacio">Sin datos suficientes.</p>
            <?php else: ?>
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead><tr><th>Hashtag</th><th>Videos</th><?php foreach ($columnas as $c): ?><th><?php echo e($c[0]); ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($lista as $h): ?>
                        <tr>
                            <td><strong>#<?php echo e($h['nombre']); ?></strong></td>
                            <td><?php echo $entero($h['videos']); ?></td>
                            <?php foreach ($columnas as $c): ?><td><?php echo $c[1]($h); ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
$colMediana = ['Mediana', fn($h) => $compacto($h['mediana'])];
$colInteraccion = ['Interacción', fn($h) => $porcentaje($h['engagement'])];
?>

<section class="tk-seccion" aria-labelledby="ct-categorias">
    <h2 class="tk-seccion-titulo" id="ct-categorias">Temas de las publicaciones</h2>
    <p class="tk-nota tk-nota-superior">
        Cada video se clasifica automáticamente leyendo su descripción y sus hashtags, con un diccionario de palabras clave (sin IA externa, todo en este servidor).
        Se clasifica el texto, no lo que se ve en el video. <?php echo $entero($cl['sin_clasificar']); ?> videos no encajaron en ninguna categoría («<?php echo e(Clasificador::SIN_CATEGORIA); ?>»).
    </p>

    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Videos y mediana de vistas por categoría</h3></div>
            <div class="tarjeta-cuerpo">
                <div class="tk-lienzo tk-lienzo-alto">
                    <canvas data-grafico="<?php echo $grafico([
                        'tipo' => 'mixto',
                        'etiquetas' => array_column($categorias, 'nombre'),
                        'series' => [
                            ['nombre' => 'Videos', 'tipo' => 'bar', 'datos' => array_column($categorias, 'videos'), 'color' => 'celeste', 'eje' => 'y'],
                            ['nombre' => 'Mediana de vistas', 'tipo' => 'line', 'datos' => array_map(fn($c) => (int)$c['mediana'], $categorias), 'color' => 'rojo', 'eje' => 'y1'],
                        ],
                    ]); ?>" role="img" aria-label="Videos y mediana de vistas por categoría"></canvas>
                </div>
            </div>
        </div>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Respuestas rápidas</h3></div>
            <div class="tarjeta-cuerpo">
                <dl class="tk-datos">
                    <?php foreach ($respuestas as [$pregunta, $categoria, $formato]): ?>
                        <div>
                            <dt><?php echo e($pregunta); ?></dt>
                            <dd><?php echo $categoria ? e($categoria['nombre']) . ' <span class="tk-secundario">· ' . e($formato($categoria)) . '</span>' : '—'; ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <p class="tk-nota">Solo cuentan las categorías con al menos <?php echo Analisis::MINIMO_MUESTRA; ?> videos.</p>
            </div>
        </div>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Rendimiento por categoría</h3></div>
        <div class="tarjeta-cuerpo p-0">
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead>
                        <tr><th>Categoría</th><th>Videos</th><th>% del total</th><th>Vistas</th><th>Mediana</th><th>Interacción</th><th>Coment. prom.</th><th>Compart. prom.</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categorias as $c): ?>
                        <tr>
                            <td><strong><?php echo e($c['nombre']); ?></strong></td>
                            <td><?php echo $entero($c['videos']); ?><?php if (!$c['confiable']): ?> <span class="badge badge-info" title="Menos de <?php echo Analisis::MINIMO_MUESTRA; ?> videos">poca muestra</span><?php endif; ?></td>
                            <td><?php echo $porcentaje($c['porcentaje']); ?></td>
                            <td><?php echo $compacto($c['vistas']); ?></td>
                            <td><?php echo $compacto($c['mediana']); ?></td>
                            <td><?php echo $porcentaje($c['engagement'], 2); ?></td>
                            <td><?php echo $decimal($c['comentarios_prom'], 1); ?></td>
                            <td><?php echo $decimal($c['compartidos_prom'], 1); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="tk-nota tk-nota-tabla">
                Para revisar los videos de una categoría, ábrelos en <a href="<?php echo url('tiktok/videos'); ?>">Videos</a> y filtra por categoría.
            </p>
        </div>
    </div>

    <?php if (!empty($cl['emergentes'])): ?>
    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Temas emergentes</h3></div>
        <div class="tarjeta-cuerpo p-0">
            <p class="tk-nota tk-nota-tabla">Términos que se repiten en videos sin categoría y que no son típicos de toda la cuenta. Podrían ser nuevas categorías.</p>
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead><tr><th>Término</th><th>Videos sin categoría</th><th>Videos en total</th><th>Mediana</th><th>Interacción</th></tr></thead>
                    <tbody>
                    <?php foreach ($cl['emergentes'] as $t): ?>
                        <tr>
                            <td><strong><?php echo e($t['termino']); ?></strong></td>
                            <td><?php echo $entero($t['videos_sin_categoria']); ?></td>
                            <td><?php echo $entero($t['videos_total']); ?></td>
                            <td><?php echo $compacto($t['mediana']); ?></td>
                            <td><?php echo $porcentaje($t['engagement']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<section class="tk-seccion" aria-labelledby="ct-hashtags">
    <h2 class="tk-seccion-titulo" id="ct-hashtags">Hashtags</h2>
    <p class="tk-nota tk-nota-superior"><?php echo $entero($ht['distintos']); ?> hashtags distintos; las tablas muestran los que aparecen en al menos <?php echo Analisis::MINIMO_MUESTRA; ?> videos.</p>

    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaHashtags($ht['mas_usados'], 'Más usados', [$colMediana, $colInteraccion]); ?>
        <?php $tablaHashtags($ht['por_rendimiento'], 'Mejor rendimiento (mediana de vistas)', [$colMediana, $colInteraccion]); ?>
    </div>
    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaHashtags($ht['por_interaccion'], 'Mayor interacción', [$colInteraccion, $colMediana]); ?>
        <?php $tablaHashtags($ht['por_compartidos'], 'Más compartidos por video', [['Compart. prom.', fn($h) => $decimal($h['compartidos_prom'], 1)], $colMediana]); ?>
    </div>
    <div class="tk-rejilla tk-rejilla-2">
        <?php $tablaHashtags($ht['peores'], 'No están rindiendo (menor mediana)', [$colMediana, $colInteraccion]); ?>
        <?php $tablaHashtags($ht['poco_usados'], 'Poco usados que rinden bien', [$colMediana, ['Veces la mediana', fn($h) => $decimal($h['veces_mediana'], 1) . '×']]); ?>
    </div>
    <p class="tk-nota">«Poco usados que rinden bien» son hashtags con 2 a <?php echo Analisis::MINIMO_MUESTRA - 1; ?> videos cuya mediana supera la del canal: una pista, no una conclusión, porque la muestra es pequeña.</p>

    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Hashtags que aparecen juntos</h3></div>
        <div class="tarjeta-cuerpo p-0">
            <?php if (empty($ht['coocurrencia'])): ?>
                <p class="tk-vacio">No hay pares que se repitan en al menos <?php echo Analisis::MINIMO_PAR_HASHTAGS; ?> videos.</p>
            <?php else: ?>
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead><tr><th>Combinación</th><th>Videos juntos</th><th>Mediana</th><th>Interacción</th></tr></thead>
                    <tbody>
                    <?php foreach ($ht['coocurrencia'] as $par): ?>
                        <tr>
                            <td><strong>#<?php echo e($par['a']); ?></strong> + <strong>#<?php echo e($par['b']); ?></strong></td>
                            <td><?php echo $entero($par['videos']); ?></td>
                            <td><?php echo $compacto($par['mediana']); ?></td>
                            <td><?php echo $porcentaje($par['engagement']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="tk-nota tk-nota-tabla">Sirven para descubrir agrupaciones temáticas: etiquetas que la cuenta suele usar en conjunto.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="tk-seccion" aria-labelledby="ct-audio">
    <h2 class="tk-seccion-titulo" id="ct-audio">Audio</h2>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Sonido original o música</h3></div>
            <div class="tarjeta-cuerpo">
                <dl class="tk-datos">
                    <div><dt>Con sonido original</dt><dd><?php echo $entero($resumen['sonidos']['originales']['videos']); ?> videos <span class="tk-secundario">· mediana <?php echo $compacto($resumen['sonidos']['originales']['mediana']); ?></span></dd></div>
                    <div><dt>Con música de terceros</dt><dd><?php echo $entero($resumen['sonidos']['con_musica']['videos']); ?> videos <span class="tk-secundario">· mediana <?php echo $compacto($resumen['sonidos']['con_musica']['mediana']); ?></span></dd></div>
                </dl>
            </div>
        </div>
        <?php if (!empty($resumen['sonidos']['canciones'])): ?>
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Canciones más repetidas</h3></div>
            <div class="tarjeta-cuerpo p-0">
                <div class="tabla-responsiva">
                    <table class="tabla">
                        <thead><tr><th>Canción</th><th>Videos</th><th>Mediana</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen['sonidos']['canciones'] as $s): ?>
                            <tr>
                                <td><?php echo e(mb_substr($s['titulo'], 0, 45)); ?></td>
                                <td><?php echo $s['videos']; ?></td>
                                <td><?php echo $compacto($s['mediana']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
