<div class="pagina-cabecera">
    <div>
        <h1 class="pagina-titulo"><?php echo e($plataforma['nombre']); ?></h1>
        <p class="pagina-subtitulo">Análisis del canal de <?php echo e($plataforma['nombre']); ?></p>
    </div>
    <div class="pagina-acciones">
        <a href="<?php echo url('menu'); ?>" class="boton boton-secundario">Volver al menú</a>
    </div>
</div>

<div class="tarjeta">
    <div class="tarjeta-cuerpo tk-vacio">
        <span class="menu-icono menu-icono-centro"><?php echo Inicio::icono($clave); ?></span>
        <h2>Este apartado todavía está vacío</h2>
        <p>Aquí irá el análisis de <?php echo e($plataforma['nombre']); ?>. Por ahora solo TikTok tiene datos y gráficos.</p>
        <a href="<?php echo url('tiktok'); ?>" class="boton boton-primario">Ir a TikTok</a>
    </div>
</div>
