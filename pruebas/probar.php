<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('RAIZ', dirname(__DIR__));
define('CAPCHER_PRUEBAS', true);

require RAIZ . '/nucleo/ayudas.php';
require RAIZ . '/nucleo/conexion.php';
require RAIZ . '/tiktok/analisis.php';
require RAIZ . '/tiktok/modelo.php';
require RAIZ . '/tiktok/actualizador.php';
require RAIZ . '/recursos/capcher.php';

configuracion();

$pasadas = 0;
$falladas = 0;

function afirmar(string $nombre, bool $condicion, string $detalle = ''): void
{
    global $pasadas, $falladas;
    if ($condicion) {
        $pasadas++;
        echo "  [OK] $nombre\n";
    } else {
        $falladas++;
        echo "  [FALLO] $nombre" . ($detalle !== '' ? " -> $detalle" : '') . "\n";
    }
}

function fila(string $id, string $fecha, int $vistas, int $likes, int $coment, int $comp, int $guard, int $dur, array $tags = [], bool $fijado = false): array
{
    return [
        'video_id' => $id, 'url' => "https://www.tiktok.com/@tvu_umsa_lp/video/$id", 'publicado_en' => $fecha,
        'descripcion' => "Video $id", 'hashtags' => json_encode($tags), 'duracion' => $dur,
        'vistas' => $vistas, 'likes' => $likes, 'comentarios' => $coment, 'compartidos' => $comp, 'guardados' => $guard,
        'portada' => '', 'fijado' => $fijado ? 1 : 0, 'sonido' => 'sonido original', 'sonido_original' => 1, 'cuenta' => 'tvu_umsa_lp',
    ];
}

function videos(array $filas): array
{
    return array_map([VideosModelo::class, 'normalizar'], $filas);
}

function itemJson(string $id, int $vistas): array
{
    return [
        'video_id' => $id, 'url' => "https://www.tiktok.com/@tvu_umsa_lp/video/$id",
        'created_at_ts' => 1790000000, 'created_at_local' => '2026-09-21T10:00:00-04:00',
        'views' => $vistas, 'likes' => 1, 'comments' => 0, 'shares' => 0, 'collects' => 0,
        'description' => "Video $id #umsa", 'hashtags' => ['umsa'], 'duration_sec' => 30,
        'is_pinned' => false, 'is_original_sound' => true, 'music_title' => 'sonido original', 'author_username' => 'tvu_umsa_lp',
    ];
}

echo "============================================================\n";
echo " PRUEBAS DEL MODULO TIKTOK\n";
echo "============================================================\n";

echo "\n> Analisis con datos controlados\n";

$muestra = videos([
    fila('100000000000000001', '2026-01-03 12:00:00', 1000, 100, 10, 5, 5, 20, ['umsa', 'beca']),
    fila('100000000000000002', '2026-01-10 12:30:00', 3000, 300, 30, 15, 15, 25, ['umsa', 'beca']),
    fila('100000000000000003', '2026-01-17 12:10:00', 5000, 500, 50, 25, 25, 30, ['umsa', 'beca']),
    fila('100000000000000004', '2026-02-02 09:00:00', 200, 10, 0, 0, 0, 90, ['umsa']),
    fila('100000000000000005', '2026-02-09 09:00:00', 400, 20, 0, 0, 0, 100, ['umsa']),
    fila('100000000000000006', '2026-03-01 18:00:00', 0, 0, 0, 0, 0, 40, ['umsa'], true),
]);

$r = Analisis::resumen($muestra);
$t = $r['totales'];
afirmar('Suma las vistas totales', $t['vistas'] === 9600, (string)$t['vistas']);
afirmar('Suma los me gusta', $t['likes'] === 930);
afirmar('Calcula la interaccion global', abs($t['engagement'] - (1110 / 9600)) < 0.0001, (string)$t['engagement']);
afirmar('La mediana incluye el video con cero vistas', $t['vistas_mediana'] === 700.0);
afirmar('Cuenta los videos fijados', $t['fijados'] === 1);
afirmar('Detecta primer y ultimo video', $t['primer_video'] === '2026-01-03' && $t['ultimo_video'] === '2026-03-01');
afirmar('Agrupa por mes', array_column($r['mensual'], 'videos', 'mes') === ['2026-01' => 3, '2026-02' => 2, '2026-03' => 1]);
afirmar('Agrupa por dia de la semana (sabado)', $r['dias'][5]['videos'] === 3 && $r['dias'][5]['mediana'] === 3000.0);
afirmar('Mapa de calor sin color si hay menos de 3 videos', $r['mapa_calor'][0][9]['nivel'] === 0);
afirmar('Mapa de calor con color si hay 3 o mas videos', $r['mapa_calor'][5][12]['nivel'] >= 1);
afirmar('No recomienda franjas sin muestra minima', $r['franjas'] === []);
afirmar('Hashtags con menos de 5 videos no se analizan', !in_array('beca', array_column($r['hashtags']['mas_usados'], 'nombre'), true));
afirmar('Agrupa por rangos de duracion', array_column($r['duraciones'], 'videos', 'clave')['16-30'] === 3);
afirmar('El top empieza por el video con mas vistas', $r['top_vistas'][0]['id'] === '100000000000000003');
afirmar('Una lista par tiene mediana promedio', Analisis::mediana([1, 3, 5, 7]) === 4.0);
afirmar('Una lista vacia no tiene mediana', Analisis::mediana([]) === null);

$busqueda = Analisis::explorar($muestra, ['q' => '#beca']);
afirmar('El explorador busca por hashtag', $busqueda['total'] === 3);
afirmar('El explorador busca sin distinguir mayusculas', Analisis::explorar($muestra, ['q' => 'VIDEO 100000000000000004'])['total'] === 1);
afirmar('El explorador ordena por vistas', Analisis::explorar($muestra, ['orden' => 'vistas'])['items'][0]['vistas'] === 5000);
afirmar('Un orden invalido cae en la fecha', Analisis::explorar($muestra, ['orden' => 'x; DROP TABLE'])['items'][0]['id'] === '100000000000000006');
afirmar('La pagina se limita al maximo', Analisis::explorar($muestra, ['pagina' => 99])['pagina'] === 1);
afirmar('Sin videos informa que no hay datos', Analisis::resumen([])['hay_datos'] === false);

echo "\n> Excepciones (one-hit)\n";

$filas = [];
for ($i = 1; $i <= 9; $i++) {
    $filas[] = fila('20000000000000000' . $i, '2026-04-0' . $i . ' 12:00:00', 1000, 50, 5, 5, 5, 30, ['umsa']);
}
$filas[] = fila('200000000000000010', '2026-04-10 12:00:00', 500000, 20000, 500, 800, 900, 30, ['umsa', 'viral']);
$conViral = videos($filas);

$todos = Analisis::resumen($conViral, ['umbral' => 20]);
$ex = $todos['excepciones'];
afirmar('Detecta el video que se dispara', $ex['cantidad'] === 1 && $ex['lista'][0]['vistas'] === 500000);
afirmar('El limite es umbral por mediana del canal', $ex['limite'] === 20000.0);
afirmar('Calcula cuantas veces supera la mediana', abs($ex['lista'][0]['veces_mediana'] - 500) < 0.001);
afirmar('Calcula el peso sobre las vistas', abs($ex['porcentaje_vistas'] - 500000 / 509000) < 0.0001);
afirmar('Con todos los videos el promedio queda inflado', $todos['totales']['vistas_promedio'] > 50000);
afirmar('Sin excepciones el promedio es normal', abs($ex['sin']['promedio'] - 1000) < 0.001);

$sin = Analisis::resumen($conViral, ['umbral' => 20, 'ignorar_excepciones' => true]);
afirmar('Al ignorar excepciones las metricas las excluyen', $sin['total'] === 9 && $sin['totales']['vistas'] === 9000);
afirmar('Al ignorar excepciones se siguen listando aparte', $sin['excepciones']['cantidad'] === 1 && $sin['total_canal'] === 10);
afirmar('El ranking ya no incluye la excepcion', $sin['top_vistas'][0]['vistas'] === 1000);
afirmar('Un umbral invalido usa el valor por defecto', Analisis::umbralValido(7) === 20 && Analisis::umbralValido('10') === 10);
afirmar('El explorador filtra solo excepciones', Analisis::explorar($conViral, ['excepciones' => 'solo'])['total'] === 1);
afirmar('El explorador oculta excepciones', Analisis::explorar($conViral, ['excepciones' => 'sin'])['total'] === 9);

$parecidos = videos(array_map(fn($i) => fila('30000000000000000' . $i, '2026-05-0' . $i . ' 12:00:00', 1000 + $i, 10, 1, 1, 1, 30), range(1, 6)));
afirmar('Si todos son parecidos no hay excepciones', Analisis::resumen($parecidos)['excepciones']['cantidad'] === 0);

echo "\n> Lectura del JSON de capcher.php\n";

$item = itemJson('7707054001103113479', 9309);
$f = VideosModelo::filaDesdeJson($item);
afirmar('Convierte un video valido', $f !== null && $f['vistas'] === 9309 && $f['publicado_en'] === '2026-09-21 10:00:00');
afirmar('Guarda los hashtags como JSON', $f['hashtags'] === '["umsa"]');
afirmar('Rechaza un video sin id', VideosModelo::filaDesdeJson(['views' => 5]) === null);
afirmar('Rechaza un id con texto', VideosModelo::filaDesdeJson(['video_id' => '12; DROP']) === null);
afirmar('Los datos ausentes quedan nulos, no en cero', VideosModelo::filaDesdeJson(['video_id' => '7707054001103113479'])['vistas'] === null);
afirmar('Los negativos no pasan', VideosModelo::filaDesdeJson(['video_id' => '7707054001103113479', 'views' => -5])['vistas'] === 0);
afirmar('Limita la descripcion a 1000 caracteres', mb_strlen(VideosModelo::filaDesdeJson(['video_id' => '7707054001103113479', 'description' => str_repeat('a', 5000)])['descripcion']) === 1000);

echo "\n> Clasificacion automatica de contenido\n";

$cDanza = Clasificador::clasificarTexto('🎭✨SECTOR CULTURA🔴La UMSA impulsa el talento artístico con talleres intensivos de Danza y Teatro🔵#UMSA #Danza #Teatro #Cultura');
afirmar('Danza y teatro es Cultura con subtema Artes escénicas', $cDanza['categoria'] === 'Cultura' && $cDanza['secundaria'] === 'Artes escénicas', json_encode($cDanza, JSON_UNESCAPED_UNICODE));
$cEduc = Clasificador::clasificarTexto('🔴TIK TAK🔵 Con estudiantes de Ciencias de la Educación👏😎 #educacion #estudiantes #ciencias #humanidades #umsa #umsasomostodos');
afirmar('Ciencias de la Educación es Educación (no Investigación)', $cEduc['categoria'] === 'Educación', json_encode($cEduc, JSON_UNESCAPED_UNICODE));
$cCurso = Clasificador::clasificarTexto('🔴¿CURSO DE VERANO PARA RECUPERAR O ADELANTAR?🔵🎤LA UMSA OPINA✨#tvuumsalp #umsa #cursosdeverano #universitarios #umsasomostodos');
afirmar('Curso de verano es Académico', $cCurso['categoria'] === 'Académico', json_encode($cCurso, JSON_UNESCAPED_UNICODE));
afirmar('Un texto sin palabras clave queda en Otros', Clasificador::clasificarTexto('hola #umsa')['categoria'] === Clasificador::SIN_CATEGORIA);
afirmar('Ignora tildes y mayúsculas', Clasificador::clasificarTexto('INSCRIPCIÓN y MATRÍCULA')['categoria'] === 'Información administrativa');
afirmar('Palabras exactas no coinciden como prefijo (arte vs articulo)', Clasificador::clasificarTexto('articulo')['categoria'] === Clasificador::SIN_CATEGORIA);

$conTema = [];
for ($i = 1; $i <= 6; $i++) {
    $conTema[] = fila('4000000000000000' . sprintf('%02d', $i), '2026-06-0' . $i . ' 10:00:00', 1000, 50, 5, 5, 5, 30, ['umsa', 'astronautas']);
}
for ($i = 1; $i <= 24; $i++) {
    $conTema[] = fila('4100000000000000' . sprintf('%02d', $i), '2026-07-' . sprintf('%02d', $i) . ' 10:00:00', 1000, 50, 5, 5, 5, 30, ['umsa', 'danza']);
}
$conTema = videos($conTema);
foreach ($conTema as $i => $v) {
    $conTema[$i]['descripcion'] = $i < 6 ? 'Los astronautas visitan el campus' : 'Función de danza';
}
$cl = Clasificador::analizar($conTema);
$porNombre = array_column($cl['categorias'], null, 'nombre');
afirmar('Resume videos por categoria', ($porNombre['Cultura']['videos'] ?? 0) === 24 && $cl['sin_clasificar'] === 6);
afirmar('Detecta un tema emergente en lo que no se clasifica', in_array('astronautas', array_column($cl['emergentes'], 'termino'), true), json_encode($cl['emergentes']));
afirmar('No propone terminos tipicos de toda la cuenta como emergentes', !in_array('umsa', array_column($cl['emergentes'], 'termino'), true));

echo "\n> Rendimiento, franjas e IQR\n";

$rend = Analisis::resumen($muestra)['rendimiento'];
afirmar('Calcula el video con mas vistas y mayor interaccion', $rend['records'][0]['video']['id'] === '100000000000000003' && $rend['records'][1]['clave'] === 'engagement');
afirmar('Promedia las interacciones por video', abs($rend['promedios']['interacciones'] - 1110 / 6) < 0.001);
afirmar('Peores videos solo incluye los que ya maduraron', count($rend['peores_vistas']) >= 1 && $rend['peores_vistas'][0]['vistas'] <= $rend['peores_vistas'][count($rend['peores_vistas']) - 1]['vistas']);

$franjas = Rendimiento::franjasDia(videos(array_merge(
    array_map(fn($i) => fila('50000000000000000' . $i, '2026-03-0' . $i . ' 20:00:00', 3000, 100, 5, 5, 5, 30), range(1, 6)),
    array_map(fn($i) => fila('51000000000000000' . $i, '2026-04-0' . $i . ' 08:00:00', 500, 10, 1, 1, 1, 30), range(1, 6)),
    [fila('520000000000000001', '2026-05-01 14:00:00', 9999, 1, 1, 1, 1, 30)]
)));
$porClave = array_column($franjas['franjas'], null, 'clave');
afirmar('Agrupa en madrugada, mañana, tarde y noche', array_keys($porClave) === ['madrugada', 'manana', 'tarde', 'noche'] && $porClave['noche']['videos'] === 6 && $porClave['manana']['videos'] === 6);
afirmar('Recomienda la franja con mejor mediana', $franjas['mejor_vistas']['clave'] === 'noche');
afirmar('Una franja con pocos videos no se compara', $porClave['tarde']['confiable'] === false && $franjas['mejor_vistas']['clave'] !== 'tarde');

$base = [];
for ($i = 1; $i <= 12; $i++) {
    $base[] = fila('60000000000000000' . sprintf('%02d', $i), '2026-01-' . sprintf('%02d', $i) . ' 12:00:00', 1000 + $i * 10, 100, 5, 5, 5, 30);
}
$base[] = fila('600000000000000099', '2026-01-20 12:00:00', 90000, 100, 5, 5, 5, 30);
$base[] = fila('600000000000000098', '2026-01-25 12:00:00', 500000, 100, 5, 5, 5, 30);
$ext = Rendimiento::extraordinarias(videos($base));
afirmar('Detecta un video con vistas fuera de lo habitual (IQR)', ($ext['grupos']['vistas']['resultado']['lista'][0]['video']['id'] ?? '') === '600000000000000099');
afirmar('No compara los videos que aun estan madurando', $ext['en_maduracion'] === 1);
afirmar('Con pocos videos no calcula limites', Rendimiento::extraordinarias(videos([fila('700000000000000001', '2026-01-01 10:00:00', 100, 1, 1, 1, 1, 30)]))['hay_datos'] === false);
afirmar('Percentiles por interpolacion', Rendimiento::percentil([1, 2, 3, 4, 5], 0.25) === 2.0 && Rendimiento::percentil([1, 2, 3, 4], 0.5) === 2.5);

echo "\n> Hashtags y asistente de estrategia\n";

$etiquetas = [];
for ($i = 1; $i <= 6; $i++) {
    $etiquetas[] = fila('8000000000000000' . sprintf('%02d', $i), '2026-02-0' . $i . ' 12:00:00', 2000, 100, 10, 10, 10, 30, ['umsa', 'danza', 'teatro']);
}
for ($i = 1; $i <= 8; $i++) {
    $etiquetas[] = fila('8100000000000000' . sprintf('%02d', $i), '2026-03-0' . $i . ' 12:00:00', 300, 10, 1, 1, 1, 30, ['umsa', 'becas']);
}
$etiquetas[] = fila('820000000000000001', '2026-04-01 12:00:00', 9000, 100, 10, 10, 10, 30, ['nuevo', 'extra']);
$etiquetas[] = fila('820000000000000002', '2026-04-02 12:00:00', 9500, 100, 10, 10, 10, 30, ['nuevo', 'extra']);
$etiquetas = videos($etiquetas);
foreach ($etiquetas as $i => $v) {
    $etiquetas[$i]['descripcion'] = $i < 6 ? 'Taller de danza y teatro' : ($i < 14 ? 'Becas disponibles' : 'Algo distinto');
}
$ht = Analisis::resumen($etiquetas)['hashtags'];
$par = array_values(array_filter($ht['coocurrencia'], fn($p) => $p['a'] === 'danza' && $p['b'] === 'teatro'));
afirmar('Detecta hashtags que aparecen juntos', count($par) === 1 && $par[0]['videos'] === 6);
afirmar('Lista los hashtags de menor rendimiento', ($ht['peores'][0]['nombre'] ?? '') === 'becas');
afirmar('Encuentra hashtags poco usados que rinden bien', in_array('nuevo', array_column($ht['poco_usados'], 'nombre'), true));

$rec = Estrategia::recomendarHashtags('Talleres de danza para estudiantes', $etiquetas, 1000.0);
$tagsRec = array_column($rec['hashtags'], 'hashtag');
afirmar('Recomienda hashtags relacionados con el texto', $rec['hay_texto'] && $rec['categoria'] === 'Cultura' && in_array('teatro', $tagsRec, true), json_encode($tagsRec));
afirmar('No sugiere lo que el texto ya trae', !in_array('danza', array_column(Estrategia::recomendarHashtags('Danza #danza', $etiquetas, 1000.0)['hashtags'], 'hashtag'), true));
afirmar('Cada sugerencia explica su motivo y su rendimiento historico', !empty($rec['hashtags'][0]['motivos']) && $rec['hashtags'][0]['mediana'] !== null);
afirmar('Un texto vacio no genera recomendacion', Estrategia::recomendarHashtags('   ', $etiquetas, 1000.0)['hay_texto'] === false);
$ideas = Analisis::resumen($etiquetas)['ideas'];
afirmar('El asistente propone ideas con justificacion', count($ideas) >= 1 && $ideas[0]['justificacion'] !== '' && $ideas[0]['idea'] !== '');

echo "\n> Recoleccion: IDs faltantes y reanudacion (capcher.php)\n";

$infoPrueba = [
    '1' => ['video_id' => '1', 'views' => 10],
    '2' => ['video_id' => '2', 'views' => null],
    '3' => ['video_id' => '3', 'views' => 5],
];
$known = ['4', '3', '2', '1'];
afirmar('Detecta los IDs sin datos, incluso los que no estan en el JSON', pending_ids($known, $infoPrueba, []) === ['4', '2']);
afirmar('Respeta el orden: el mas nuevo primero', array_slice(pending_ids($known, $infoPrueba, []), 0, 1) === ['4']);
afirmar('Tras varios fallos deja de insistir con un ID', pending_ids($known, $infoPrueba, ['4' => MAX_FALLOS_DETALLE]) === ['2']);
afirmar('Cuenta solo los videos recuperados en la corrida', count_recovered(['1' => ['views' => 10], '2' => ['views' => 7], '4' => ['views' => null]], ['1' => true]) === 1);

$dirCap = sys_get_temp_dir() . '/tiktok_estado_' . uniqid();
mkdir($dirCap, 0775, true);
$archivoEstado = $dirCap . '/estado.json';
$st = load_state($archivoEstado);
afirmar('Sin archivo de estado asume que falta verificar el perfil', $st['paginacion_completa'] === false && $st['faltan'] === true && $st['cursor'] === 0);
$st['paginacion_completa'] = false;
$st['cursor'] = 1234;
$st['fallidos'] = ['9' => 2];
save_state($archivoEstado, $st);
$leido = load_state($archivoEstado);
afirmar('El estado guarda desde donde retomar', $leido['cursor'] === 1234 && $leido['paginacion_completa'] === false && $leido['fallidos'] === ['9' => 2]);
file_put_contents($archivoEstado, '{roto');
afirmar('Un estado corrupto no rompe la recoleccion', load_state($archivoEstado)['cursor'] === 0);
save_info($dirCap . '/info.json', ['b', 'a'], ['a' => ['video_id' => 'a', 'views' => 1], 'b' => ['video_id' => 'b', 'views' => 2], 'z' => ['video_id' => 'z', 'views' => 3]]);
$guardado = json_decode(file_get_contents($dirCap . '/info.json'), true);
afirmar('Guarda la info en el orden de los IDs y sin dejar temporales', array_column($guardado, 'video_id') === ['b', 'a', 'z'] && !is_file($dirCap . '/info.json.tmp'));
foreach (glob($dirCap . '/*') ?: [] as $archivo) {
    @unlink($archivo);
}
@rmdir($dirCap);
echo "\n> Validaciones del actualizador\n";

afirmar('Acepta una cuenta normal', Actualizador::cuentaValida('tvu_umsa_lp') && Actualizador::cuentaValida('umsa.lp2024'));
afirmar('Rechaza espacios, comillas e inyeccion', !Actualizador::cuentaValida('tvu umsa') && !Actualizador::cuentaValida('a; rm -rf /') && !Actualizador::cuentaValida('a" & b') && !Actualizador::cuentaValida(''));
afirmar('Encuentra el ejecutable de PHP', is_file(Actualizador::binarioPhp()) || Actualizador::binarioPhp() === 'php');

$directorio = sys_get_temp_dir() . '/tiktok_prueba_' . uniqid();
mkdir($directorio, 0775, true);
$marca = $directorio . '/marca.txt';
$script = $directorio . '/segundo_plano.php';
file_put_contents($script, "<?php\nsleep(1);\nfile_put_contents(" . var_export($marca, true) . ", 'listo ' . (\$argv[1] ?? ''));\n");
$inicio = microtime(true);
$lanzado = Actualizador::lanzarSegundoPlano(PHP_BINARY, [$script, '42']);
afirmar('Lanza el proceso sin esperar a que termine', $lanzado && (microtime(true) - $inicio) < 0.9);
for ($i = 0; $i < 20 && !is_file($marca); $i++) {
    usleep(500000);
}
afirmar('El proceso termina solo y recibe sus argumentos', is_file($marca) && trim((string)file_get_contents($marca)) === 'listo 42');

echo "\n> Base de datos (se deshace al terminar)\n";

try {
    $pdo = bd();
    $pdo->query('SELECT 1 FROM videos LIMIT 1');
    Historial::asegurarTabla(); // CREATE TABLE confirma la transaccion: va antes de abrirla
    $hayBd = true;
} catch (Throwable $e) {
    $hayBd = false;
    echo "  (sin conexion a la base de datos, se omiten estas pruebas)\n";
}

if ($hayBd) {
    $pdo->beginTransaction();

    $json = $directorio . '/videos_info.json';
    VideosModelo::$archivo = $json;
    Actualizador::$registro = $directorio . '/registro.log';

    $ids = ['900000000000000001', '900000000000000002', '900000000000000003'];
    $pdo->prepare('DELETE FROM videos WHERE video_id IN (?, ?, ?)')->execute($ids);

    file_put_contents($json, json_encode([itemJson($ids[0], 1000), itemJson($ids[1], 2000)]));
    $antes = VideosModelo::total();
    $r = VideosModelo::importar();
    afirmar('Importa los videos nuevos', $r['exito'] && $r['nuevos'] === 2 && VideosModelo::total() === $antes + 2, json_encode($r));

    $r = VideosModelo::importar();
    afirmar('Volver a importar no duplica videos', $r['nuevos'] === 0 && VideosModelo::total() === $antes + 2);

    file_put_contents($json, json_encode([itemJson($ids[0], 5000)]));
    VideosModelo::importar();
    $vistas = $pdo->prepare('SELECT vistas FROM videos WHERE video_id = ?');
    $vistas->execute([$ids[0]]);
    afirmar('Reimportar actualiza las metricas', (int)$vistas->fetchColumn() === 5000);

    $sinVistas = itemJson($ids[0], 0);
    unset($sinVistas['views']);
    file_put_contents($json, json_encode([$sinVistas]));
    VideosModelo::importar();
    $vistas->execute([$ids[0]]);
    afirmar('Un dato ausente no borra la metrica guardada', (int)$vistas->fetchColumn() === 5000);

    $guardado = VideosModelo::todos();
    $propio = array_values(array_filter($guardado, fn($v) => $v['id'] === $ids[1]))[0] ?? null;
    afirmar('Lee los videos guardados con su fecha local', $propio !== null && $propio['fecha'] === '2026-09-21' && $propio['hora'] === 10);

    file_put_contents($json, '{no es json');
    afirmar('Un JSON corrupto no se importa', VideosModelo::importar()['exito'] === false);

    file_put_contents($json, json_encode([itemJson($ids[0], 1000), itemJson($ids[1], 2000)]));


    echo "\n> Evolucion en el tiempo (historial de metricas)\n";

    $h = '900000000000000009';
    $cuentaHistorial = $pdo->prepare('SELECT COUNT(*) FROM metricas_historial WHERE video_id = ?');

    file_put_contents($json, json_encode([itemJson($h, 1000)]));
    VideosModelo::importar();
    $cuentaHistorial->execute([$h]);
    afirmar('Importar guarda una captura inicial de las metricas', (int)$cuentaHistorial->fetchColumn() === 1);

    VideosModelo::importar();
    $cuentaHistorial->execute([$h]);
    afirmar('Si no cambian las metricas no se repite la captura', (int)$cuentaHistorial->fetchColumn() === 1);

    file_put_contents($json, json_encode([itemJson($h, 1800)]));
    VideosModelo::importar();
    $cuentaHistorial->execute([$h]);
    afirmar('Si crecen las metricas se guarda otra captura', (int)$cuentaHistorial->fetchColumn() === 2);
    $serieVideo = Historial::serie($h);
    afirmar('La serie queda ordenada de la mas antigua a la mas reciente', count($serieVideo) === 2 && (int)$serieVideo[0]['vistas'] === 1000 && (int)$serieVideo[1]['vistas'] === 1800);
    $crece = array_values(array_filter(Historial::crecimiento(50), fn($f) => $f['video_id'] === $h));
    afirmar('Calcula las vistas ganadas entre capturas', count($crece) === 1 && $crece[0]['vistas_ganadas'] === 800);
    file_put_contents($json, json_encode([itemJson($ids[0], 1000), itemJson($ids[1], 2000)]));

    echo "\n> Actualizacion con un capturador de prueba\n";

    $cap = function (string $nombre, string $codigo) use ($directorio): string {
        $ruta = $directorio . '/' . $nombre;
        file_put_contents($ruta, "<?php\n" . $codigo);
        return $ruta;
    };
    $nuevaFila = function () use ($pdo): int {
        $pdo->exec("INSERT INTO actualizaciones (inicio, estado, etapa, latido) VALUES (NOW(), 'ejecutando', 'iniciando', NOW())");
        return (int)$pdo->lastInsertId();
    };

    $bien = $cap('bien.php', "\$d = json_decode(file_get_contents('videos_info.json'), true);\n\$d[] = ['video_id' => '900000000000000003', 'views' => 3000, 'created_at_local' => '2026-09-22T10:00:00-04:00', 'description' => 'nuevo'];\nfile_put_contents('videos_info.json', json_encode(\$d));\necho \"[*] captura de prueba\\n\";\n");
    Actualizador::$capturador = $bien;
    $id = $nuevaFila();
    $r = Actualizador::capturar($id, 'tvu_umsa_lp');
    afirmar('La captura correcta cuenta el video nuevo', $r['exito'] && $r['mensaje'] === 'Recuperados: 1' && $r['recuperados'] === 1, json_encode($r));
    afirmar('Deja un respaldo antes de capturar', is_file($directorio . '/videos_info.respaldo.json'));
    afirmar('Escribe el registro de la captura', str_contains((string)file_get_contents($directorio . '/registro.log'), 'captura de prueba'));

    $sinIds = $cap('sin_ids.php', "echo \"[x] Sin IDs.\\n\";\nexit(2);\n");
    Actualizador::$capturador = $sinIds;
    $guardadoAntes = file_get_contents($json);
    $r = Actualizador::capturar($id, 'tvu_umsa_lp');
    afirmar('Si TikTok no devuelve videos informa el bloqueo', !$r['exito'] && str_contains($r['mensaje'], 'bloqueo'));
    afirmar('Tras un fallo conserva los datos anteriores', file_get_contents($json) === $guardadoAntes);

    Actualizador::$capturador = $cap('destruye.php', "file_put_contents('videos_info.json', json_encode([]));\n");
    $r = Actualizador::capturar($id, 'tvu_umsa_lp');
    afirmar('Si quedan menos videos restaura el respaldo', !$r['exito'] && count(VideosModelo::leerJson($json)) === 3);

    Actualizador::$capturador = $cap('corrompe.php', "file_put_contents('videos_info.json', '{no es json');\n");
    $r = Actualizador::capturar($id, 'tvu_umsa_lp');
    afirmar('Un JSON corrupto no deja el panel sin datos', !$r['exito'] && count(VideosModelo::leerJson($json)) === 3);

    Actualizador::$capturador = $directorio . '/no_existe.php';
    afirmar('Si falta capcher.php informa el error', !Actualizador::capturar($id, 'tvu_umsa_lp')['exito']);

    $pdo->prepare('DELETE FROM videos WHERE video_id IN (?, ?, ?)')->execute($ids);
    file_put_contents($json, json_encode([itemJson($ids[0], 1000)]));
    Actualizador::$capturador = $bien;
    $id = $nuevaFila();
    $r = Actualizador::ejecutar($id);
    afirmar('El flujo completo captura y guarda en la base de datos', $r['estado'] === 'completado', json_encode($r));
    $estado = Actualizador::estado();
    afirmar('Registra el resultado de la actualizacion', $estado['estado'] === 'completado' && (int)$estado['videos_nuevos'] === 1, json_encode($estado));
    afirmar('Los videos capturados quedan en la tabla', (int)$pdo->query("SELECT COUNT(*) FROM videos WHERE video_id IN ('900000000000000001','900000000000000003')")->fetchColumn() === 2);

    $pdo->exec("INSERT INTO actualizaciones (inicio, estado, etapa, latido) VALUES (NOW(), 'ejecutando', 'capturando', NOW())");
    afirmar('No permite dos actualizaciones a la vez', Actualizador::iniciar(null)['exito'] === false);

    $pdo->exec("UPDATE actualizaciones SET latido = NOW() - INTERVAL 3 HOUR WHERE estado = 'ejecutando'");
    afirmar('Una actualizacion sin latido se da por cancelada', Actualizador::estado()['estado'] === 'error');

    $pdo->rollBack();
}

foreach (glob($directorio . '/*') ?: [] as $archivo) {
    @unlink($archivo);
}
@rmdir($directorio);

echo "\n============================================================\n";
echo " RESUMEN: $pasadas PASADAS, $falladas FALLADAS\n";
echo "============================================================\n";

exit($falladas > 0 ? 1 : 0);
