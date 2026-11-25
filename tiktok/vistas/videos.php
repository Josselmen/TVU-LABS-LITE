<?php
require_once __DIR__ . '/_formato.php';

$ordenes = [
    'fecha' => 'Fecha de publicación', 'vistas' => 'Vistas', 'likes' => 'Me gusta', 'comentarios' => 'Comentarios',
    'compartidos' => 'Compartidos', 'guardados' => 'Guardados', 'engagement' => 'Interacción', 'duracion' => 'Duración',
];

$consulta = function (array $cambios) use ($filtros) {
    $parametros = array_merge([
        'q' => $filtros['q'], 'anio' => $filtros['anio'], 'orden' => $filtros['orden'],
        'dir' => $filtros['dir'], 'por_pagina' => $filtros['por_pagina'], 'categoria' => $filtros['categoria'],
        'excepciones' => $filtros['excepciones'] === 'todos' ? '' : $filtros['excepciones'], 'umbral' => $filtros['umbral'],
    ], $cambios);
    $parametros = array_filter($parametros, fn($v) => $v !== '' && $v !== null);
    return url('tiktok/videos') . ($parametros ? '?' . http_build_query($parametros) : '');
};

$ordenarPor = function (string $campo) use ($filtros, $consulta) {
    $dir = ($filtros['orden'] === $campo && $filtros['dir'] === 'desc') ? 'asc' : 'desc';
    return $consulta(['orden' => $campo, 'dir' => $dir, 'pagina' => 1]);
};
$flecha = fn(string $campo) => $filtros['orden'] === $campo ? ($filtros['dir'] === 'desc' ? ' ▼' : ' ▲') : '';
?>

<?php
$titulo_pagina = 'Videos';
$subtitulo = 'Todos los videos del canal con sus métricas actuales';
$con_filtro = false;
require __DIR__ . '/_cabecera.php';
?>

<?php if (!$hay_datos): ?>
<div class="tarjeta">
    <div class="tarjeta-cuerpo tk-vacio">
        <h2>Todavía no hay datos de TikTok</h2>
        <p>Pulsa <strong>Recolectar datos</strong> para traer los videos de la cuenta.</p>
    </div>
</div>
<?php return; endif; ?>

<form action="<?php echo url('tiktok/videos'); ?>" method="GET" class="tarjeta tk-filtros">
    <div class="tk-filtros-campos">
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-q">Buscar</label>
            <input type="search" id="filtro-q" name="q" class="campo-entrada" value="<?php echo e($filtros['q']); ?>" placeholder="Texto o #hashtag">
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-anio">Año</label>
            <select id="filtro-anio" name="anio" class="campo-select">
                <option value="">Todos</option>
                <?php foreach ($datos['anios'] as $a): ?>
                    <option value="<?php echo e($a); ?>" <?php echo $filtros['anio'] === (string)$a ? 'selected' : ''; ?>><?php echo e($a); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-categoria">Categoría</label>
            <select id="filtro-categoria" name="categoria" class="campo-select">
                <option value="">Todas</option>
                <?php foreach ($datos['categorias'] as $cat): ?>
                    <option value="<?php echo e($cat); ?>" <?php echo $filtros['categoria'] === $cat ? 'selected' : ''; ?>><?php echo e($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-orden">Ordenar por</label>
            <select id="filtro-orden" name="orden" class="campo-select">
                <?php foreach ($ordenes as $clave => $nombre): ?>
                    <option value="<?php echo $clave; ?>" <?php echo $filtros['orden'] === $clave ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-dir">Sentido</label>
            <select id="filtro-dir" name="dir" class="campo-select">
                <option value="desc" <?php echo $filtros['dir'] === 'desc' ? 'selected' : ''; ?>>Mayor a menor</option>
                <option value="asc" <?php echo $filtros['dir'] === 'asc' ? 'selected' : ''; ?>>Menor a mayor</option>
            </select>
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-excepciones">Excepciones (one-hit)</label>
            <select id="filtro-excepciones" name="excepciones" class="campo-select">
                <option value="todos" <?php echo $filtros['excepciones'] === 'todos' ? 'selected' : ''; ?>>Mostrar todos</option>
                <option value="sin" <?php echo $filtros['excepciones'] === 'sin' ? 'selected' : ''; ?>>Ocultar excepciones</option>
                <option value="solo" <?php echo $filtros['excepciones'] === 'solo' ? 'selected' : ''; ?>>Solo excepciones</option>
            </select>
        </div>
        <div class="campo">
            <label class="campo-etiqueta" for="filtro-umbral">Excepción desde</label>
            <select id="filtro-umbral" name="umbral" class="campo-select">
                <?php foreach (Analisis::UMBRALES_EXCEPCION as $u): ?>
                    <option value="<?php echo $u; ?>" <?php echo $filtros['umbral'] === $u ? 'selected' : ''; ?>><?php echo $u; ?>× la mediana</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="tk-filtros-acciones">
        <button type="submit" class="boton boton-primario">Aplicar</button>
        <a href="<?php echo url('tiktok/videos'); ?>" class="boton boton-secundario">Limpiar</a>
    </div>
</form>

<div class="tk-resumen-filtro">
    <span><strong><?php echo $entero($datos['total']); ?></strong> videos</span>
    <span><strong><?php echo $entero($datos['resumen']['vistas']); ?></strong> vistas</span>
    <span>Mediana <strong><?php echo $entero($datos['resumen']['mediana']); ?></strong></span>
    <span>Interacción <strong><?php echo $porcentaje($datos['resumen']['engagement']); ?></strong></span>
    <span>Excepciones: desde <strong><?php echo $entero($datos['limite_excepcion']); ?></strong> vistas (<?php echo $datos['cantidad_excepciones']; ?> videos)</span>
</div>

<div class="tarjeta">
    <div class="tarjeta-cuerpo p-0">
        <?php if (empty($datos['items'])): ?>
            <p class="tk-vacio">Ningún video coincide con los filtros.</p>
        <?php else: ?>
        <div class="tabla-responsiva">
            <table class="tabla tk-tabla-videos">
                <thead>
                    <tr>
                        <th>Video</th>
                        <th><a href="<?php echo $ordenarPor('fecha'); ?>">Fecha<?php echo $flecha('fecha'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('duracion'); ?>">Duración<?php echo $flecha('duracion'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('vistas'); ?>">Vistas<?php echo $flecha('vistas'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('likes'); ?>">Me gusta<?php echo $flecha('likes'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('comentarios'); ?>">Coment.<?php echo $flecha('comentarios'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('compartidos'); ?>">Compart.<?php echo $flecha('compartidos'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('guardados'); ?>">Guard.<?php echo $flecha('guardados'); ?></a></th>
                        <th><a href="<?php echo $ordenarPor('engagement'); ?>">Interacción<?php echo $flecha('engagement'); ?></a></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($datos['items'] as $v): ?>
                    <tr>
                        <td>
                            <div class="tk-video">
                                <?php if ($v['portada'] !== ''): ?>
                                    <img class="tk-miniatura" src="<?php echo e($v['portada']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <span class="tk-miniatura tk-miniatura-rota"></span>
                                <?php endif; ?>
                                <div>
                                    <a href="<?php echo e($v['url']); ?>" target="_blank" rel="noopener" class="tk-video-texto"><?php echo e(mb_substr($v['descripcion'] !== '' ? $v['descripcion'] : 'Sin descripción', 0, 110)); ?></a>
                                    <?php if ($v['fijado']): ?><span class="badge badge-info">Fijado</span><?php endif; ?>
                                    <?php if ($v['categoria'] !== Clasificador::SIN_CATEGORIA): ?><span class="badge badge-info"><?php echo e($v['categoria']); ?></span><?php endif; ?>
                                    <?php if (!empty($v['excepcion'])): ?><span class="badge badge-alerta">Excepción</span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $fecha($v['fecha']); ?></td>
                        <td><?php echo $duracion($v['duracion']); ?></td>
                        <td><strong><?php echo $entero($v['vistas']); ?></strong></td>
                        <td><?php echo $entero($v['likes']); ?></td>
                        <td><?php echo $entero($v['comentarios']); ?></td>
                        <td><?php echo $entero($v['compartidos']); ?></td>
                        <td><?php echo $entero($v['guardados']); ?></td>
                        <td><?php echo $porcentaje($v['engagement']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($datos['paginas'] > 1): ?>
<nav class="tk-paginacion" aria-label="Paginación">
    <?php if ($datos['pagina'] > 1): ?>
        <a class="boton boton-secundario boton-pequeno" href="<?php echo $consulta(['pagina' => $datos['pagina'] - 1]); ?>">Anterior</a>
    <?php endif; ?>
    <span>Página <?php echo $datos['pagina']; ?> de <?php echo $datos['paginas']; ?></span>
    <?php if ($datos['pagina'] < $datos['paginas']): ?>
        <a class="boton boton-secundario boton-pequeno" href="<?php echo $consulta(['pagina' => $datos['pagina'] + 1]); ?>">Siguiente</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
