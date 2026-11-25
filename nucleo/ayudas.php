<?php

function configuracion(?string $clave = null)
{
    static $datos = null;

    if ($datos === null) {
        $datos = require RAIZ . '/configuracion.php';
        $local = RAIZ . '/configuracion.local.php';
        if (is_file($local)) {
            $datos = array_replace_recursive($datos, require $local);
        }
        date_default_timezone_set($datos['zona_horaria']);
        mb_internal_encoding('UTF-8');
    }

    return $clave === null ? $datos : ($datos[$clave] ?? null);
}

function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $carpeta = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return rtrim($carpeta, '/');
}

function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

function recurso(string $archivo): string
{
    $ruta = RAIZ . '/diseno/' . $archivo;
    $version = is_file($ruta) ? filemtime($ruta) : 1;
    return base_url() . '/diseno/' . $archivo . '?v=' . $version;
}

/** Ruta pedida, sin barras (por ejemplo «tiktok/horarios»). */
function ruta_actual(): string
{
    return trim((string)($_GET['ruta'] ?? ''), '/');
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('tvumsa_tiktok');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function avisar(string $tipo, string $mensaje): void
{
    $_SESSION['avisos'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_avisos(): array
{
    $avisos = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $avisos;
}

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

function csrf_valido(): bool
{
    $enviado = (string)($_POST['csrf'] ?? '');
    return $enviado !== '' && hash_equals(token_csrf(), $enviado);
}

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigir_sesion(): void
{
    if (usuario_actual() === null) {
        redirigir('ingresar');
    }
}

function responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function pagina(string $vista, array $datos = [], string $titulo = 'TV UMSA Lab'): void
{
    extract($datos);
    ob_start();
    require $vista;
    $contenido = ob_get_clean();
    require RAIZ . '/diseno/plantilla.php';
}

function error_pagina(int $codigo, string $mensaje): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($codigo);
    pagina(RAIZ . '/diseno/error.php', ['codigo' => $codigo, 'mensaje' => $mensaje], 'Error');
    exit;
}
