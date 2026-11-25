<div class="pagina-cabecera">
    <div>
        <h1 class="pagina-titulo">¿Qué red quieres analizar?</h1>
        <p class="pagina-subtitulo">Elige una plataforma para ver su análisis.</p>
    </div>
</div>

<div class="menu-rejilla">
    <?php foreach ($plataformas as $clave => $p): ?>
        <a href="<?php echo url($clave); ?>" class="menu-tarjeta <?php echo $p['activa'] ? '' : 'menu-tarjeta-vacia'; ?>">
            <span class="menu-icono"><?php echo Inicio::icono($clave); ?></span>
            <span class="menu-nombre"><?php echo e($p['nombre']); ?></span>
            <span class="menu-descripcion"><?php echo e($p['descripcion']); ?></span>
            <span class="badge <?php echo $p['activa'] ? 'badge-ok' : 'badge-info'; ?>"><?php echo $p['activa'] ? 'Disponible' : 'Sin datos aún'; ?></span>
        </a>
    <?php endforeach; ?>
</div>
