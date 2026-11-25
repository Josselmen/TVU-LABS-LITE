<div class="acceso">
    <div class="tarjeta">
        <div class="tarjeta-cuerpo">
            <h1 class="pagina-titulo">Ingresar</h1>
            <p class="pagina-subtitulo">TV UMSA Lab · Análisis de redes</p>
            <form action="<?php echo url('ingresar'); ?>" method="POST">
                <?php echo campo_csrf(); ?>
                <div class="campo">
                    <label class="campo-etiqueta" for="correo">Correo</label>
                    <input type="email" id="correo" name="correo" class="campo-entrada" required autofocus autocomplete="username">
                </div>
                <div class="campo">
                    <label class="campo-etiqueta" for="clave">Contraseña</label>
                    <input type="password" id="clave" name="clave" class="campo-entrada" required autocomplete="current-password">
                </div>
                <button type="submit" class="boton boton-primario boton-ancho">Ingresar</button>
            </form>
        </div>
    </div>
</div>
