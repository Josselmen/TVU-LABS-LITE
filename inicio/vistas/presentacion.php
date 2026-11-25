<section class="inicio-hero">
    <p class="inicio-etiqueta">Televisión Universitaria · UMSA</p>
    <h1 class="inicio-titulo">TV UMSA Lab</h1>
    <p class="inicio-lema">Entiende cómo responde tu audiencia en redes sociales y decide qué publicar, cuándo y con qué hashtags.</p>
    <div class="inicio-acciones">
        <?php if ($con_sesion): ?>
            <a href="<?php echo url('menu'); ?>" class="boton boton-primario boton-grande">Ir al menú</a>
        <?php else: ?>
            <a href="<?php echo url('ingresar'); ?>" class="boton boton-primario boton-grande">Ingresar</a>
        <?php endif; ?>
    </div>
</section>

<section class="inicio-seccion" aria-labelledby="inicio-que">
    <h2 class="tk-seccion-titulo" id="inicio-que">Qué encontrarás</h2>
    <div class="inicio-rejilla">
        <article class="inicio-dato">
            <h3>Rendimiento</h3>
            <p>Qué publicaciones funcionan mejor y peor, con vistas e interacción por separado para no confundir alcance con respuesta.</p>
        </article>
        <article class="inicio-dato">
            <h3>Mejores horarios</h3>
            <p>Mapa de calor por día y hora, y comparación entre mañana, tarde y noche.</p>
        </article>
        <article class="inicio-dato">
            <h3>Contenido y hashtags</h3>
            <p>Clasificación automática por temática, hashtags que rinden y los que aparecen juntos.</p>
        </article>
        <article class="inicio-dato">
            <h3>Asistente de estrategia</h3>
            <p>Hashtags sugeridos para una nueva publicación e ideas basadas en el historial de la cuenta.</p>
        </article>
    </div>
</section>

<section class="inicio-seccion" aria-labelledby="inicio-redes">
    <h2 class="tk-seccion-titulo" id="inicio-redes">Redes</h2>
    <ul class="inicio-redes">
        <?php foreach ($plataformas as $clave => $p): ?>
            <li class="inicio-red">
                <span class="inicio-red-icono"><?php echo Inicio::icono($clave); ?></span>
                <strong><?php echo e($p['nombre']); ?></strong>
                <span class="badge <?php echo $p['activa'] ? 'badge-ok' : 'badge-info'; ?>"><?php echo $p['activa'] ? 'Disponible' : 'Próximamente'; ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
