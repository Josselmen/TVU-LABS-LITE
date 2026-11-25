<?php
$rutaActual = ruta_actual();
$actual = function (string $ruta) use ($rutaActual): string {
    $activa = $rutaActual === $ruta || str_starts_with($rutaActual, $ruta . '/');
    return $activa ? ' aria-current="page"' : '';
};
$conSesion = usuario_actual() !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($titulo); ?> — TV UMSA Lab</title>
    <link rel="stylesheet" href="<?php echo recurso('estilos.css'); ?>">
</head>
<body>
    <header class="cabecera">
        <div class="cabecera-interior">
            <a href="<?php echo url($conSesion ? 'menu' : 'inicio'); ?>" class="cabecera-logo"><span class="logo-icono">TV</span> TV UMSA Lab</a>
            <nav class="cabecera-menu" aria-label="Principal">
                <?php if ($conSesion): ?>
                    <a href="<?php echo url('menu'); ?>"<?php echo $actual('menu'); ?>>Menú</a>
                    <a href="<?php echo url('tiktok'); ?>"<?php echo $actual('tiktok'); ?>>TikTok</a>
                    <a href="<?php echo url('youtube'); ?>"<?php echo $actual('youtube'); ?>>YouTube</a>
                    <a href="<?php echo url('facebook'); ?>"<?php echo $actual('facebook'); ?>>Facebook</a>
                    <a href="<?php echo url('salir'); ?>">Salir (<?php echo e(usuario_actual()['nombre']); ?>)</a>
                <?php else: ?>
                    <a href="<?php echo url('ingresar'); ?>"<?php echo $actual('ingresar'); ?>>Ingresar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="contenido">
        <?php foreach (tomar_avisos() as $aviso): ?>
            <div class="alerta alerta-<?php echo $aviso['tipo'] === 'exito' ? 'exito' : 'error'; ?>" role="alert"><?php echo e($aviso['mensaje']); ?></div>
        <?php endforeach; ?>

        <?php echo $contenido; ?>
    </main>

    <script src="<?php echo recurso('grafico.min.js'); ?>"></script>
    <script src="<?php echo recurso('app.js'); ?>"></script>
</body>
</html>
