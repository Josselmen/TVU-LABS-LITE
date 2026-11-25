# TV UMSA Lab · Análisis de redes

Panel web para analizar el rendimiento de las redes de la TV universitaria. Solo usa **PHP + MySQL** (XAMPP). Por ahora **TikTok** tiene análisis; **YouTube** y **Facebook** tienen su apartado vacío, listo para llenarse. Los datos de TikTok los recolecta `recursos/capcher.php`.

## Datos de conexión a la base de datos

Se configuran en [configuracion.php](configuracion.php) (base `tvumsa_tiktok`, servidor `127.0.0.1`, usuario `root`, sin contraseña; el puerto actual del archivo es `3307`, cámbialo si tu MySQL usa otro). Es el único lugar donde se escribe la conexión.

## Instalación

1. Copia esta carpeta a `C:\xampp\htdocs\TV-UMSA-TIKTOK`.
2. Enciende **Apache** y **MySQL** en XAMPP.
3. Crea la base con `base_datos/crear_base_datos.sql` (phpMyAdmin → Importar, o `C:\xampp\mysql\bin\mysql.exe -u root < base_datos\crear_base_datos.sql`).
4. Inserta usuario y videos: `C:\xampp\php\php.exe base_datos\insertar_datos.php`
5. Abre http://localhost/TV-UMSA-TIKTOK/ e ingresa con `admin@tvumsa.bo` / `admin123` (cambia la contraseña después).

Si ya tenías la base creada no hace falta repetirlo: la tabla nueva del historial (`metricas_historial`) se crea sola.

## Cómo se usa

Portada → **Ingresar** → **Menú** (TikTok, YouTube, Facebook) → TikTok.

Pestañas de TikTok:

| Pestaña | Qué muestra |
|---|---|
| Resumen | Indicadores, hallazgos, evolución por mes y año, excepciones (one-hit) |
| Rendimiento | Promedios, récords por métrica, mejores y peores, publicaciones extraordinarias (IQR) |
| Horarios | Madrugada/mañana/tarde/noche, días, mapa de calor, mejores franjas, duración |
| Contenido | Clasificación automática por tema, temas emergentes, hashtags (mejores, peores, juntos, poco usados), audio |
| Estrategia | Recomendador de hashtags para una nueva publicación e ideas de contenido según el historial |
| Evolución | Cuánto crece cada video entre una recolección y otra |
| Videos | Explorador con búsqueda, filtro por año, categoría y excepción |

**Filtro one-hit**: viene **activado** (interruptor bajo las pestañas). Excluye del análisis los videos que superan N veces la mediana del canal (5, 10, 20 o 50; por defecto 20). Las excepciones siguen visibles en el Resumen. El explorador de Videos tiene su propio filtro y muestra todo por defecto.

**Recolección**: un solo botón (*Recolectar datos* / *Continuar recolección*) y un contador **Recuperados**. Sigue en segundo plano aunque cierres la página.

- Busca los videos nuevos arriba del perfil.
- Si una recolección se cortó a medias, `recoleccion_estado.json` recuerda desde dónde seguir y qué IDs aún no tienen métricas; la siguiente recolección continúa **ID por ID** hasta igualar `videos_info.json` con `video_ids_completo.txt`. El avance se guarda cada pocos videos.
- Un ID que falla 3 veces (video borrado o privado) deja de insistirse.
- Si TikTok bloquea o el archivo queda con menos videos, se restaura el respaldo.

`capcher.php` también se puede ejecutar a mano desde `recursos`: `php capcher.php --user=tvu_umsa_lp`. Crea ahí `cookies_jar.txt`: no lo compartas.

## Análisis y sus límites

- La **clasificación por tema** lee descripción y hashtags con un diccionario de palabras clave (en `tiktok/clasificador.php`, editable). No usa IA externa ni envía datos a ningún servicio, y no ve el contenido audiovisual.
- Los **hashtags sugeridos** y las **ideas** salen del historial de la cuenta. Muestran evidencia, no predicen visualizaciones.
- Las comparaciones piden una muestra mínima de 5 videos; con menos se marca «poca muestra».
- La **evolución** empieza a llenarse desde la primera recolección posterior a esta versión; se guarda una captura solo si las métricas cambiaron.
- No se analizan comentarios (el panel tiene su cantidad, no su texto) ni retención de reproducción.

## Estructura

```
index.php            entrada única y rutas
configuracion.php    base de datos, cuenta de TikTok, zona horaria
nucleo/              conexión a MySQL y funciones comunes
acceso/              inicio de sesión
inicio/              portada, menú de redes y apartados vacíos (YouTube, Facebook)
tiktok/              controlador, modelo (SQL), analisis, clasificador, rendimiento,
                     estrategia, historial, actualizador y vistas
diseno/              plantilla, estilos, JavaScript y Chart.js
recursos/            capcher.php y los datos que genera
base_datos/          crear_base_datos.sql e insertar_datos.php
pruebas/             probar.php
```

## Base de datos

`usuarios`, `videos`, `actualizaciones` (estado de cada recolección) y `metricas_historial` (capturas de métricas en el tiempo).

## Pruebas

`C:\xampp\php\php.exe pruebas\probar.php` (usa la base de datos y deshace todo lo que escribe).
