<?php
/**
 * Cabecera común de TikTok: título, botón único de recolección, pestañas y filtro de excepciones.
 * Variables: $seccion, $titulo_pagina, $subtitulo, $con_filtro (opcional).
 */
require_once __DIR__ . '/_formato.php';

$conFiltro = $con_filtro ?? true;
$estadoActual = $actualizacion['estado'];
$enCurso = $estadoActual === 'ejecutando';
$pendiente = $recoleccion['faltan'] || $estadoActual === 'parcial';
$etiquetaBoton = $pendiente ? 'Continuar recolección' : 'Recolectar datos';
$clasesEstado = ['completado' => 'tk-recoleccion-ok', 'parcial' => 'tk-recoleccion-aviso', 'error' => 'tk-recoleccion-error', 'ejecutando' => 'tk-recoleccion-curso'];

$pestanas = [
    'panel' => ['Resumen', 'tiktok'],
    'rendimiento' => ['Rendimiento', 'tiktok/rendimiento'],
    'horarios' => ['Horarios', 'tiktok/horarios'],
    'contenido' => ['Contenido', 'tiktok/contenido'],
    'estrategia' => ['Estrategia', 'tiktok/estrategia'],
    'evolucion' => ['Evolución', 'tiktok/evolucion'],
    'videos' => ['Videos', 'tiktok/videos'],
];
?>
<div class="pagina-cabecera">
    <div>
        <h1 class="pagina-titulo"><?php echo e($titulo_pagina); ?></h1>
        <p class="pagina-subtitulo"><?php echo e($subtitulo); ?></p>
    </div>

    <div class="tk-recoleccion <?php echo e($clasesEstado[$estadoActual] ?? ''); ?>"
         data-actualizacion
         data-url="<?php echo url('tiktok/estado'); ?>"
         data-estado="<?php echo e($estadoActual); ?>">
        <form action="<?php echo url('tiktok/actualizar'); ?>" method="POST" class="tk-formulario-linea" data-confirmar="Se recolectarán los videos nuevos y los que falten. Sigue aunque cierres la página. ¿Continuar?">
            <?php echo campo_csrf(); ?>
            <button type="submit" class="boton boton-primario" data-boton-recolectar <?php echo $enCurso ? 'disabled' : ''; ?>><?php echo e($etiquetaBoton); ?></button>
        </form>
        <?php if ($estadoActual !== 'inactivo'): ?>
            <span class="tk-recuperados" data-actualizacion-mensaje role="status" aria-live="polite">
                <?php if ($enCurso): ?><span class="tk-rueda" aria-hidden="true"></span><?php endif; ?>
                <span data-actualizacion-texto><?php echo e(in_array($estadoActual, ['completado', 'parcial'], true) && $actualizacion['videos_nuevos'] !== null ? 'Recuperados: ' . (int)$actualizacion['videos_nuevos'] : $actualizacion['mensaje']); ?></span>
            </span>
        <?php endif; ?>
    </div>
</div>

<nav class="tk-pestanas" aria-label="Secciones de TikTok">
    <?php foreach ($pestanas as $clave => [$nombre, $ruta]): ?>
        <a href="<?php echo $clave === 'videos' ? url($ruta) : $enlaceTk($ruta); ?>"<?php echo $seccion === $clave ? ' aria-current="page"' : ''; ?>><?php echo e($nombre); ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($conFiltro && $hay_videos): ?>
<form action="<?php echo url($seccion === 'panel' ? 'tiktok' : 'tiktok/' . $seccion); ?>" method="GET" class="tk-filtro" data-autoenviar>
    <?php foreach ($parametros_extra ?? [] as $nombre => $valor): ?>
        <input type="hidden" name="<?php echo e($nombre); ?>" value="<?php echo e($valor); ?>">
    <?php endforeach; ?>
    <label class="tk-interruptor">
        <input type="hidden" name="excepciones" value="todos">
        <input type="checkbox" name="excepciones" value="ignorar" <?php echo $opciones['ignorar_excepciones'] ? 'checked' : ''; ?>>
        <span class="tk-interruptor-pista" aria-hidden="true"></span>
        <span class="tk-interruptor-texto">Ignorar excepciones (one-hit)</span>
    </label>
    <label class="tk-filtro-umbral">
        <span>desde</span>
        <select name="umbral" class="campo-select" aria-label="Una excepción supera esta cantidad de veces la mediana">
            <?php foreach ($umbrales as $u): ?>
                <option value="<?php echo $u; ?>" <?php echo $opciones['umbral'] === $u ? 'selected' : ''; ?>><?php echo $u; ?>× la mediana</option>
            <?php endforeach; ?>
        </select>
    </label>
    <span class="tk-filtro-info">
        <?php echo $opciones['ignorar_excepciones'] ? 'Se excluyen' : 'Hay'; ?>
        <strong><?php echo $entero($excepciones_info['cantidad']); ?></strong> video<?php echo $excepciones_info['cantidad'] === 1 ? '' : 's'; ?>
        con <?php echo $compacto($excepciones_info['limite']); ?> vistas o más
    </span>
    <noscript><button type="submit" class="boton boton-primario boton-pequeno">Aplicar</button></noscript>
</form>
<?php endif; ?>
