<?php

class Acceso
{
    private const MAX_INTENTOS = 5;
    private const MINUTOS_BLOQUEO = 15;

    public static function formulario(): void
    {
        if (usuario_actual() !== null) {
            redirigir('menu');
        }
        pagina(RAIZ . '/acceso/vistas/ingresar.php', [], 'Ingresar');
    }

    public static function entrar(): void
    {
        $correo = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
        $clave = (string)($_POST['clave'] ?? '');

        $consulta = bd()->prepare('SELECT * FROM usuarios WHERE correo = :correo');
        $consulta->execute(['correo' => $correo]);
        $usuario = $consulta->fetch();

        if (!$usuario) {
            password_verify($clave, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234');
            avisar('error', 'Correo o contraseña incorrectos.');
            redirigir('ingresar');
        }

        if ($usuario['bloqueado_hasta'] !== null && strtotime($usuario['bloqueado_hasta']) > time()) {
            $minutos = (int)ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
            avisar('error', "Cuenta bloqueada por intentos fallidos. Intenta en $minutos minuto(s).");
            redirigir('ingresar');
        }

        if (!password_verify($clave, $usuario['clave'])) {
            $intentos = (int)$usuario['intentos'] + 1;
            $bloqueo = $intentos >= self::MAX_INTENTOS ? date('Y-m-d H:i:s', time() + self::MINUTOS_BLOQUEO * 60) : null;
            $actualizar = bd()->prepare('UPDATE usuarios SET intentos = :intentos, bloqueado_hasta = :bloqueo WHERE id = :id');
            $actualizar->execute(['intentos' => $bloqueo ? 0 : $intentos, 'bloqueo' => $bloqueo, 'id' => $usuario['id']]);
            avisar('error', $bloqueo ? 'Demasiados intentos. Cuenta bloqueada ' . self::MINUTOS_BLOQUEO . ' minutos.' : 'Correo o contraseña incorrectos.');
            redirigir('ingresar');
        }

        bd()->prepare('UPDATE usuarios SET intentos = 0, bloqueado_hasta = NULL WHERE id = :id')->execute(['id' => $usuario['id']]);

        session_regenerate_id(true);
        $_SESSION['usuario'] = ['id' => (int)$usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo']];
        redirigir('menu');
    }

    public static function salir(): void
    {
        $_SESSION = [];
        session_destroy();
        iniciar_sesion();
        avisar('exito', 'Sesión cerrada.');
        redirigir('ingresar');
    }
}
