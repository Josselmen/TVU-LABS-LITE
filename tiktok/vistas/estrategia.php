<?php
require_once __DIR__ . '/_formato.php';

$hayDatos = !empty($resumen['hay_datos']);
$titulo_pagina = 'Estrategia';
$subtitulo = 'Hashtags e ideas para tus próximas publicaciones, basados en el historial de la cuenta';
$parametros_extra = $texto !== '' ? ['texto' => $texto] : [];
require __DIR__ . '/_cabecera.php';

if (!$hayDatos): ?>
<div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio"><p>Todavía no hay datos para analizar.</p></div></div>
<?php return; endif;

$ideas = $resumen['ideas'];
$tipos = ['rinde' => 'Rinde bien', 'oportunidad' => 'Poco explotado', 'emergente' => 'Tema emergente'];
?>

<section class="tk-seccion" aria-labelledby="es-hashtags">
    <h2 class="tk-seccion-titulo" id="es-hashtags">Recomendador de hashtags</h2>
    <form action="<?php echo url('tiktok/estrategia'); ?>" method="GET" class="tarjeta tk-recomendador">
        <?php if (!$opciones['ignorar_excepciones']): ?><input type="hidden" name="excepciones" value="todos"><?php endif; ?>
        <?php if ($opciones['umbral'] !== Analisis::UMBRAL_EXCEPCION_DEFECTO): ?><input type="hidden" name="umbral" value="<?php echo (int)$opciones['umbral']; ?>"><?php endif; ?>
        <div class="campo">
            <label class="campo-etiqueta" for="es-texto">Describe tu próxima publicación</label>
            <textarea id="es-texto" name="texto" class="campo-entrada tk-textarea" rows="3" maxlength="600" placeholder="Ej.: Talleres intensivos de danza y teatro para estudiantes de la UMSA"><?php echo e($texto); ?></textarea>
        </div>
        <button type="submit" class="boton boton-primario">Sugerir hashtags</button>
    </form>

    <?php if ($recomendacion !== null && $recomendacion['hay_texto']): $rec = $recomendacion; ?>
    <div class="tk-rejilla tk-rejilla-2">
        <div class="tarjeta">
            <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Lo que entendí de tu texto</h3></div>
            <div class="tarjeta-cuerpo">
                <dl class="tk-datos">
                    <div><dt>Categoría</dt><dd><?php echo e($rec['categoria']); ?><?php if ($rec['secundaria'] !== ''): ?> <span class="tk-secundario">· <?php echo e($rec['secundaria']); ?></span><?php endif; ?></dd></div>
                    <div><dt>Palabras clave</dt><dd><?php echo $rec['palabras'] ? e(implode(', ', $rec['palabras'])) : '—'; ?></dd></div>
                    <div><dt>Videos relacionados en el historial</dt><dd><?php echo $entero($rec['videos_relacionados']); ?></dd></div>
                </dl>
            </div>
        </div>
        <?php $tablaVideos($rec['similares'], 'Publicaciones parecidas del historial', 'engagement'); ?>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-cabecera"><h3 class="tarjeta-titulo">Hashtags sugeridos</h3></div>
        <div class="tarjeta-cuerpo p-0">
            <?php if (!$rec['hashtags']): ?>
                <p class="tk-vacio">No encontré hashtags relacionados en el historial. Prueba con más detalle en la descripción.</p>
            <?php else: ?>
            <div class="tabla-responsiva">
                <table class="tabla">
                    <thead><tr><th>Hashtag</th><th>Motivo</th><th>Usado en</th><th>Mediana</th><th>Frente al canal</th><th>Interacción</th></tr></thead>
                    <tbody>
                    <?php foreach ($rec['hashtags'] as $h): ?>
                        <tr>
                            <td><strong>#<?php echo e($h['hashtag']); ?></strong></td>
                            <td><?php echo e(implode(' · ', $h['motivos'])); ?></td>
                            <td><?php echo $entero($h['videos']); ?> video<?php echo $h['videos'] === 1 ? '' : 's'; ?><?php if ($h['muestra_pequena']): ?> <span class="badge badge-info" title="Menos de <?php echo Analisis::MINIMO_MUESTRA; ?> videos">poca muestra</span><?php endif; ?></td>
                            <td><?php echo $compacto($h['mediana']); ?></td>
                            <td><?php echo $decimal($h['veces_mediana'], 1); ?>×</td>
                            <td><?php echo $porcentaje($h['engagement']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <p class="tk-nota tk-nota-tabla">Las sugerencias salen de lo que la cuenta ya publicó con temas parecidos. Que un hashtag haya funcionado antes no garantiza que funcione en una publicación nueva.</p>
        </div>
    </div>
    <?php elseif ($recomendacion !== null): ?>
        <p class="tk-nota">Escribe una descripción con algunas palabras para poder buscar coincidencias.</p>
    <?php endif; ?>
</section>

<section class="tk-seccion" aria-labelledby="es-ideas">
    <h2 class="tk-seccion-titulo" id="es-ideas">Asistente de estrategia de contenido</h2>
    <?php if (!$ideas): ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo tk-vacio">Todavía no hay suficiente historial por categoría para proponer ideas.</div></div>
    <?php else: ?>
    <div class="tk-ideas">
        <?php foreach ($ideas as $idea): ?>
        <article class="tarjeta tk-idea">
            <div class="tarjeta-cabecera">
                <h3 class="tarjeta-titulo"><?php echo e($idea['tema']); ?></h3>
                <span class="badge <?php echo $idea['tipo'] === 'rinde' ? 'badge-ok' : 'badge-alerta'; ?>"><?php echo e($tipos[$idea['tipo']] ?? ''); ?></span>
            </div>
            <div class="tarjeta-cuerpo">
                <p class="tk-idea-texto">«<?php echo e($idea['idea']); ?>»</p>
                <?php if ($idea['hashtags']): ?>
                    <p class="tk-idea-tags"><?php foreach ($idea['hashtags'] as $tag): ?><span class="tk-etiqueta"><?php echo e($tag); ?></span><?php endforeach; ?></p>
                <?php endif; ?>
                <dl class="tk-datos tk-datos-lista">
                    <div><dt>Por qué</dt><dd><?php echo e($idea['justificacion']); ?></dd></div>
                    <div><dt>Recomendación</dt><dd><?php echo e($idea['recomendacion']); ?></dd></div>
                </dl>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="tk-nota">Son propuestas ilustrativas construidas con el historial de la cuenta (qué temas rinden y cuáles se publican poco). No son una predicción de visualizaciones.</p>
    <?php endif; ?>
</section>
