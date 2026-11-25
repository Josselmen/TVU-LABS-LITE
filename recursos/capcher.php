<?php
declare(strict_types=1);

/**
 * capcher.php - perfil TikTok por HTTP, sin navegador. ACUMULATIVO Y REANUDABLE.
 * --user=  --limit=  --cookies=  --sleep=  --debug  --max-pages=
 *
 * video_ids_completo.txt queda ordenado: más reciente arriba, más viejo al final.
 * Nunca se duplica un ID ya presente.
 *
 * Recolección en dos fases, con progreso guardado en recoleccion_estado.json:
 *   1. IDs: busca los nuevos arriba del perfil; si una corrida anterior se cortó a medias,
 *      continúa desde el cursor guardado hasta llegar al final del perfil.
 *   2. Detalle: los IDs que ya se conocen pero todavía no tienen métricas se completan uno por uno
 *      (más nuevo primero), guardando el avance cada pocos videos. Si no hay IDs nuevos, esta
 *      fase es la que iguala videos_info.json con video_ids_completo.txt.
 */

const UA_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';
const UA_MOBILE  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
const DESC_MAX_CHARS = 1000;
const JSON_OUT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
const MAX_PAGES_DEFAULT = 400;

$OPTS = parse_args($argv);
$USER          = $OPTS['user'] ?? '';
$LIMIT         = (int)($OPTS['limit'] ?? 100000);
$COOKIES_FILE  = $OPTS['cookies'] ?? null;
$SLEEP_FACTOR  = (float)($OPTS['sleep'] ?? 1.2);
$MAX_PAGES     = (int)($OPTS['max-pages'] ?? MAX_PAGES_DEFAULT);
$DEBUG         = isset($OPTS['debug']);

if ($USER === '' && !defined('CAPCHER_PRUEBAS')) { fwrite(STDERR, "Falta --user=USUARIO\n"); exit(1); }

$VIDEO_IDS_FILE   = 'video_ids_completo.txt';
$VIDEOS_INFO_FILE = 'videos_info.json';
$META_FILE        = 'video_ids_meta.json';
$STATE_FILE       = 'recoleccion_estado.json';
$COOKIE_JAR       = 'cookies_jar.txt';
$LOCAL_TZ         = new DateTimeZone('-04:00');

function parse_args(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $a) {
        if (!str_starts_with($a, '--')) continue;
        $a = substr($a, 2);
        if (str_contains($a, '=')) { [$k, $v] = explode('=', $a, 2); $out[$k] = $v; }
        else { $out[$a] = true; }
    }
    return $out;
}

function jitter(float $base): void { usleep((int)(($base + mt_rand(0, 900) / 1000) * 1_000_000)); }

function looks_blocked(string $html, int $status, int $minLen = 2000): bool
{
    if ($status === 429 || $status === 403 || $status === 412) return true;
    foreach (['captcha-verify-container', 'verify.tiktok.com', 'captcha_slide_button', 'tiktok-verify-page'] as $n) {
        if (stripos($html, $n) !== false) return true;
    }
    return strlen($html) < $minLen;
}

// ---------------------------------------------------------------------------
// Estado persistente

function load_known_ids(string $file): array
{
    if (!is_file($file)) return [];
    $ids = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if (preg_match('/^\d{15,25}$/', $line)) $ids[] = $line;
    }
    return array_values(array_unique($ids));
}

function load_meta(string $file): array
{
    if (!is_file($file)) return [];
    $d = json_decode((string)file_get_contents($file), true);
    if (!is_array($d)) return [];
    $m = [];
    foreach ($d as $k => $v) if (is_numeric($v)) $m[(string)$k] = (int)$v;
    return $m;
}

function load_info(string $file): array
{
    if (!is_file($file)) return [];
    $d = json_decode((string)file_get_contents($file), true);
    if (!is_array($d)) return [];
    $m = [];
    foreach ($d as $v) if (isset($v['video_id'])) $m[(string)$v['video_id']] = $v;
    return $m;
}

/**
 * Inserta los IDs nuevos respetando el orden por recencia.
 * $known: lista ordenada (más nuevo → más viejo).
 * $newVideos: lista nueva también ordenada, con 'id' y 'ts'.
 * Entradas conocidas sin timestamp se mantienen en su posición relativa.
 */
function merge_ordered(array $known, array $newVideos, array $knownTs): array
{
    $knownSet = array_fill_keys($known, true);
    $out = [];
    $i = 0; $n = count($known);
    foreach ($newVideos as $v) {
        $ts = $v['ts'];
        while ($i < $n) {
            $kts = $knownTs[$known[$i]] ?? null;
            if ($ts !== null && $kts !== null && $kts > $ts) { $out[] = $known[$i]; $i++; }
            else break;
        }
        if (!isset($knownSet[$v['id']])) $out[] = $v['id'];
    }
    while ($i < $n) $out[] = $known[$i++];
    return array_values(array_unique($out));
}

/** Persiste IDs + meta de forma atómica. Devuelve la lista fusionada. */
function save_ids(string $file, array $known, array $newVideos, array $meta, string $metaFile): array
{
    $merged = merge_ordered($known, $newVideos, $meta);
    $fp = fopen($file, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, implode("\n", $merged) . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    foreach ($newVideos as $v) if ($v['ts'] !== null) $meta[$v['id']] = (int)$v['ts'];
    file_put_contents($metaFile, json_encode($meta));
    return $merged;
}

/** Escribe en un temporal y lo renombra: un corte a medias nunca deja el archivo truncado. */
function write_atomic(string $file, string $content): void
{
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $content) === false) return;
    if (!@rename($tmp, $file)) { @unlink($file); @rename($tmp, $file); }
}

/**
 * Estado de la recolección (el "sw" que permite retomar después de un corte):
 *   paginacion_completa  false si el recorrido de IDs se interrumpió y faltan IDs en medio
 *   cursor               punto del perfil desde donde retomar
 *   fallidos             intentos de detalle fallidos por ID (tras 3 se deja de insistir)
 *   faltan / pendientes  hay trabajo por terminar / cuántos IDs sin métricas
 *   recuperados          videos con datos obtenidos en la corrida actual
 */
function load_state(string $file): array
{
    $base = [
        'paginacion_completa' => false, 'paginacion_posible' => true, 'cursor' => 0, 'fallidos' => [],
        'faltan' => true, 'pendientes' => 0, 'recuperados' => 0, 'total_ids' => 0, 'con_datos' => 0,
        'actualizado' => null,
    ];
    if (!is_file($file)) return $base;
    $d = json_decode((string)file_get_contents($file), true);
    return is_array($d) ? array_replace($base, $d) : $base;
}

function save_state(string $file, array $state): void
{
    $state['actualizado'] = date('c');
    write_atomic($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

const MAX_FALLOS_DETALLE = 3;

/** IDs conocidos que todavía no tienen métricas (más nuevo primero). Salta los que ya fallaron varias veces. */
function pending_ids(array $known, array $info, array $fallidos): array
{
    $out = [];
    foreach ($known as $id) {
        if (isset($info[$id]) && $info[$id]['views'] !== null) continue;
        if (($fallidos[$id] ?? 0) >= MAX_FALLOS_DETALLE) continue;
        $out[] = $id;
    }
    return $out;
}

/** Une los datos de $info al orden de $known y los guarda de forma atómica. */
function save_info(string $file, array $known, array $info): void
{
    $final = []; $rest = $info;
    foreach ($known as $id) {
        if (isset($rest[$id])) { $final[] = $rest[$id]; unset($rest[$id]); }
    }
    foreach ($rest as $v) $final[] = $v;
    write_atomic($file, json_encode($final, JSON_OUT));
}

/** Videos que ahora tienen métricas y antes no (los "recuperados" de la corrida). */
function count_recovered(array $info, array $hadData): int
{
    $n = 0;
    foreach ($info as $id => $v) if ($v['views'] !== null && !isset($hadData[$id])) $n++;
    return $n;
}

// ---------------------------------------------------------------------------

final class Client
{
    public string $ua = UA_DESKTOP;
    public array $cookies = [];
    private string $jar;
    private float $sleepFactor;

    public function __construct(string $jar, float $sleepFactor)
    {
        $this->jar = $jar;
        $this->sleepFactor = $sleepFactor;
        $this->cookies['s_v_web_id'] = 'verify_' . bin2hex(random_bytes(8)) . '_' . bin2hex(random_bytes(8));
    }

    public function loadCookiesFromFile(string $path): void
    {
        $raw = @file_get_contents($path);
        if ($raw === false) { fwrite(STDERR, "[!] No pude leer $path\n"); return; }
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $p = preg_split('/\t+/', $line);
            if (count($p) >= 7) { $this->cookies[$p[5]] = $p[6]; }
        }
        echo "[*] Cookies cargadas: " . count($this->cookies) . "\n";
    }

    public function syncFromJar(): void
    {
        if (!is_file($this->jar)) return;
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) as $line) {
            if ($line === '' || $line[0] === '#') continue;
            $p = preg_split('/\t+/', $line);
            if (count($p) >= 7 && in_array($p[5], ['ttwid', 'tt_csrf_token', 'tt_chain_token', 'msToken', 's_v_web_id'], true)) {
                if ($p[6] !== '') $this->cookies[$p[5]] = $p[6];
            }
        }
    }

    public function get(string $url, string $referer = 'https://www.tiktok.com/', bool $firstNav = false): array
    {
        $pairs = [];
        foreach ($this->cookies as $k => $v) $pairs[] = "$k=$v";
        $mobile = str_contains($this->ua, 'iPhone');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->ua,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Upgrade-Insecure-Requests: 1',
                'Cookie: ' . implode('; ', $pairs),
                'Referer: ' . $referer,
                'sec-ch-ua-mobile: ' . ($mobile ? '?1' : '?0'),
                'sec-ch-ua-platform: ' . ($mobile ? '"iOS"' : '"Windows"'),
                'sec-fetch-dest: document',
                'sec-fetch-mode: navigate',
                'sec-fetch-site: ' . ($firstNav ? 'none' : 'same-origin'),
                'sec-fetch-user: ?1',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        jitter($this->sleepFactor);
        return [$status, (string)$body];
    }

    public function getJson(string $url, string $referer): ?array
    {
        [$s, $b] = $this->get($url, $referer);
        if ($s !== 200 || $b === '') return null;
        $d = json_decode($b, true);
        return is_array($d) ? $d : null;
    }

    public function bootstrap(): void
    {
        $this->get('https://www.tiktok.com/', 'https://www.tiktok.com/', true);
        $this->syncFromJar();
        if (empty($this->cookies['msToken'])) {
            $this->cookies['msToken'] = substr(strtr(base64_encode(random_bytes(81)), '+/', '-_'), 0, 107);
        }
        echo "[*] tokens: " . implode(', ', array_keys($this->cookies)) . "\n";
    }
}

// ---------------------------------------------------------------------------

function format_dates(?int $ts, DateTimeZone $tz): array
{
    if (!$ts) return [null, null, null, null];
    $utc = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
    $loc = $utc->setTimezone($tz);
    return [$utc->format('c'), $loc->format('c'), $loc->format('l'), (int)$loc->format('G')];
}

function extract_hashtags(array $item): array
{
    $tags = [];
    foreach ($item['textExtra'] ?? [] as $e) if (!empty($e['hashtagName'])) $tags[] = $e['hashtagName'];
    if (!$tags && !empty($item['desc'])) { preg_match_all('/#(\w+)/u', $item['desc'], $m); $tags = $m[1] ?? []; }
    return array_values(array_unique(array_map('mb_strtolower', $tags)));
}

function extract_mentions(array $item): array
{
    $ms = [];
    foreach ($item['textExtra'] ?? [] as $e) if (!empty($e['userUniqueId'])) $ms[] = $e['userUniqueId'];
    if (!$ms && !empty($item['desc'])) { preg_match_all('/@([\w.]+)/u', $item['desc'], $m); $ms = $m[1] ?? []; }
    return array_values(array_unique(array_map('mb_strtolower', $ms)));
}

function num($v): ?int { return is_numeric($v) ? (int)$v : null; }

function parse_item(array $item, DateTimeZone $tz, string $username = ''): array
{
    $vid    = (string)($item['id'] ?? '');
    $stats  = $item['stats'] ?? $item['statsV2'] ?? [];
    $video  = $item['video'] ?? [];
    $music  = $item['music'] ?? [];
    $author = $item['author'] ?? [];
    $uname  = $author['uniqueId'] ?? $username;

    $added   = $item['addedSoundMusicInfo'] ?? $item['added_sound_music_info'] ?? [];
    $matched = $music['matchedSongInfo'] ?? $item['matchedSongInfo'] ?? [];
    $commercial = $music['commercialMusicInfo'] ?? $item['commercialMusicInfo'] ?? [];
    $songTitle  = $matched['title'] ?? $added['title'] ?? $commercial['musicInfo']['title'] ?? null;
    $songAuthor = $matched['authorName'] ?? $added['authorName'] ?? $commercial['musicInfo']['authorName'] ?? null;

    [$utcIso, $locIso, $weekday, $hour] = format_dates(isset($item['createTime']) ? (int)$item['createTime'] : null, $tz);

    $views    = num($stats['playCount']    ?? null);
    $likes    = num($stats['diggCount']    ?? null);
    $comments = num($stats['commentCount'] ?? null);
    $shares   = num($stats['shareCount']   ?? null);
    $collects = num($stats['collectCount'] ?? null);

    $engRate = null; $likeRate = null;
    if ($views && $views > 0) {
        $total = 0;
        foreach ([$likes, $comments, $shares, $collects] as $x) if ($x !== null) $total += $x;
        $engRate  = round($total / $views, 4);
        $likeRate = $likes !== null ? round($likes / $views, 4) : null;
    }

    return [
        'video_id' => $vid,
        'url' => $vid ? "https://www.tiktok.com/@{$uname}/video/{$vid}" : null,
        'created_at_ts' => $item['createTime'] ?? null,
        'created_at_utc' => $utcIso, 'created_at_local' => $locIso,
        'weekday' => $weekday, 'hour_local' => $hour,
        'views' => $views, 'likes' => $likes, 'comments' => $comments,
        'shares' => $shares, 'collects' => $collects,
        'engagement_rate' => $engRate, 'like_rate' => $likeRate,
        'description' => mb_substr((string)($item['desc'] ?? ''), 0, DESC_MAX_CHARS),
        'hashtags' => extract_hashtags($item), 'mentions' => extract_mentions($item),
        'duration_sec' => $video['duration'] ?? null,
        'width' => $video['width'] ?? null, 'height' => $video['height'] ?? null,
        'cover_url' => $video['cover'] ?? $video['originCover'] ?? null,
        'is_ad' => (bool)($item['isAd'] ?? $item['isAdvertisement'] ?? false),
        'music_id' => $music['id'] ?? null, 'music_title' => $music['title'] ?? null,
        'music_author' => $music['authorName'] ?? null,
        'is_original_sound' => isset($music['original']) ? (bool)$music['original'] : null,
        'music_play_url' => $music['playUrl'] ?? null,
        'music_cover_url' => $music['coverLarge'] ?? $music['coverMedium'] ?? $music['coverThumb'] ?? null,
        'music_duration_sec' => $music['duration'] ?? null,
        'music_album' => $music['album'] ?? null,
        'music_commercial' => (bool)($music['isCommerceMusic'] ?? false),
        'song_title' => $songTitle,
        'song_author' => $songAuthor,
        'is_comment_disabled' => (($item['item_comment_settings'] ?? null) === 3),
        'duet_enabled' => $item['duetEnabled'] ?? null,
        'stitch_enabled' => $item['stitchEnabled'] ?? null,
        'is_pinned' => (bool)($item['isPinnedItem'] ?? false),
        'challenges' => array_values(array_filter(array_column($item['challenges'] ?? [], 'title'))),
        'poi_location' => $item['poi']['name'] ?? $item['poi']['address'] ?? null,
        'subtitles_available' => !empty($item['subtitleInfos']),
        'author_username' => $uname ?: null,
        'author_nickname' => $author['nickname'] ?? null,
        'source' => 'api',
    ];
}

function blank_video(string $vid, string $username): array
{
    return [
        'video_id' => $vid, 'url' => "https://www.tiktok.com/@{$username}/video/{$vid}",
        'created_at_ts' => null, 'created_at_utc' => null, 'created_at_local' => null,
        'weekday' => null, 'hour_local' => null,
        'views' => null, 'likes' => null, 'comments' => null, 'shares' => null, 'collects' => null,
        'engagement_rate' => null, 'like_rate' => null,
        'description' => null, 'hashtags' => [], 'mentions' => [],
        'duration_sec' => null, 'width' => null, 'height' => null, 'cover_url' => null, 'is_ad' => null,
        'music_id' => null, 'music_title' => null, 'music_author' => null, 'is_original_sound' => null,
        'music_play_url' => null, 'music_cover_url' => null, 'music_duration_sec' => null,
        'music_album' => null, 'music_commercial' => null, 'song_title' => null, 'song_author' => null,
        'is_comment_disabled' => null, 'duet_enabled' => null, 'stitch_enabled' => null, 'is_pinned' => null,
        'challenges' => [], 'poi_location' => null, 'subtitles_available' => null,
        'author_username' => $username, 'author_nickname' => null, 'source' => 'dom_fallback',
    ];
}

function merge_video(array $base, array $new): array
{
    foreach ($new as $k => $v) {
        if ($v === null || $v === []) continue;
        if ($k === 'source' && $base['source'] === 'api') continue;
        $base[$k] = $v;
    }
    return $base;
}

function hunt_items($node, array &$out): void
{
    if (!is_array($node)) return;
    if (isset($node['id']) && (isset($node['stats']) || isset($node['statsV2']) || isset($node['desc']) || isset($node['video']))) {
        $out[(string)$node['id']] = $node;
        return;
    }
    foreach ($node as $child) if (is_array($child)) hunt_items($child, $out);
}

function extract_items_from_html(string $html): array
{
    $out = [];
    if (!preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $m)) return $out;
    foreach ($m[1] as $blob) {
        if (!str_contains($blob, '"id"')) continue;
        $start = strpos($blob, '{');
        $end   = strrpos($blob, '}');
        if ($start === false || $end === false || $end <= $start) continue;
        $d = json_decode(substr($blob, $start, $end - $start + 1), true);
        if (is_array($d)) hunt_items($d, $out);
    }
    return $out;
}

function extract_ids(string $html): array
{
    $ids = [];
    if (preg_match_all('#/video/(\d{15,25})#', $html, $m)) $ids = $m[1];
    if (preg_match_all('/"id"\s*:\s*"(\d{18,20})"/', $html, $m2)) $ids = array_merge($ids, $m2[1]);
    return array_values(array_unique($ids));
}

function extract_secuid(string $html): ?string
{
    return preg_match('/"secUid"\s*:\s*"([A-Za-z0-9_\-]+)"/', $html, $m) ? $m[1] : null;
}

function read_profile(Client $c, string $username, DateTimeZone $tz, bool $debug): array
{
    $clean = ltrim($username, '@');
    $intentos = [
        [UA_DESKTOP, "https://www.tiktok.com/embed/@{$clean}"],
        [UA_MOBILE,  "https://www.tiktok.com/embed/@{$clean}"],
        [UA_MOBILE,  "https://www.tiktok.com/@{$clean}"],
        [UA_DESKTOP, "https://www.tiktok.com/@{$clean}"],
    ];

    $videos = []; $secUid = null; $lastHtml = '';

    foreach ($intentos as $i => [$ua, $url]) {
        $c->ua = $ua;
        for ($try = 1; $try <= 3; $try++) {
            [$status, $html] = $c->get($url, 'https://www.tiktok.com/', true);
            $lastHtml = $html;
            $nIds = count(extract_ids($html));
            echo "    [i" . ($i + 1) . ".{$try}] HTTP $status, " . strlen($html) . " bytes, $nIds ids\n";
            if ($debug) file_put_contents("debug_p{$i}_{$try}.html", $html);
            if (looks_blocked($html, $status)) { jitter(2.5); continue; }

            foreach (extract_items_from_html($html) as $raw) {
                $p = parse_item($raw, $tz, $clean);
                if ($p['video_id'] !== '') {
                    $videos[$p['video_id']] = isset($videos[$p['video_id']])
                        ? merge_video($videos[$p['video_id']], $p) : $p;
                }
            }
            $secUid ??= extract_secuid($html);
            foreach (extract_ids($html) as $id) {
                if (!isset($videos[$id])) $videos[$id] = blank_video($id, $clean);
            }
            if ($videos) echo "    [ok] acumulado: " . count($videos) . "\n";
            if (count($videos) > 3) return [$secUid, array_values($videos), $html];
        }
    }
    return [$secUid, array_values($videos), $lastHtml];
}

function fetch_item_page(Client $c, string $secUid, int $cursor): array
{
    $p = [
        'aid' => 1988, 'app_language' => 'es-ES', 'app_name' => 'tiktok_web',
        'browser_language' => 'es-ES', 'browser_name' => 'Mozilla', 'browser_online' => 'true',
        'browser_platform' => 'Win32', 'browser_version' => '5.0 (Windows)', 'channel' => 'tiktok_web',
        'cookie_enabled' => 'true', 'count' => 35, 'cursor' => $cursor, 'device_platform' => 'web_pc',
        'focus_state' => 'true', 'from_page' => 'user', 'history_len' => 3, 'is_fullscreen' => 'false',
        'is_page_visible' => 'true', 'language' => 'es-ES', 'os' => 'windows', 'priority_region' => 'BO',
        'referer' => '', 'region' => 'BO', 'screen_height' => 800, 'screen_width' => 1280,
        'secUid' => $secUid, 'tz_name' => 'America/La_Paz', 'webcast_language' => 'es-ES',
        'msToken' => $c->cookies['msToken'] ?? '',
    ];
    return $c->getJson('https://www.tiktok.com/api/post/item_list/?' . http_build_query($p),
        'https://www.tiktok.com/') ?? [];
}

function fetch_item_page_retry(Client $c, string $secUid, int $cursor, int $tries = 3): array
{
    for ($i = 1; $i <= $tries; $i++) {
        $d = fetch_item_page($c, $secUid, $cursor);
        if (!empty($d['itemList']) || array_key_exists('hasMore', $d)) return $d;
        echo "    [!] item_list sin datos (intento $i)\n";
        jitter(1.5 * $i);
    }
    return [];
}

function fetch_detail(Client $c, string $vid, string $username, DateTimeZone $tz): ?array
{
    $rutas = [
        [UA_MOBILE,  "https://www.tiktok.com/@{$username}/video/{$vid}?lang=es"],
        [UA_DESKTOP, "https://www.tiktok.com/embed/{$vid}?lang=es"],
        [UA_MOBILE,  "https://www.tiktok.com/embed/v2/{$vid}?lang=es"],
    ];

    foreach ($rutas as $r => [$ua, $url]) {
        $c->ua = $ua;
        [$status, $html] = $c->get($url, 'https://www.tiktok.com/');
        if ($status !== 200 || strlen($html) < 1000) continue;

        $items = extract_items_from_html($html);
        if (isset($items[$vid])) { echo " (ruta " . ($r + 1) . ")"; return parse_item($items[$vid], $tz, $username); }

        if (preg_match('/"playCount"\s*:\s*"?(\d+)"?/', $html, $v)) {
            echo " (regex " . ($r + 1) . ")";
            return parse_item([
                'id' => $vid,
                'statsV2' => [
                    'playCount'    => $v[1],
                    'diggCount'    => preg_match('/"diggCount"\s*:\s*"?(\d+)"?/', $html, $l) ? $l[1] : null,
                    'commentCount' => preg_match('/"commentCount"\s*:\s*"?(\d+)"?/', $html, $cm) ? $cm[1] : null,
                    'shareCount'   => preg_match('/"shareCount"\s*:\s*"?(\d+)"?/', $html, $sh) ? $sh[1] : null,
                ],
                'createTime' => preg_match('/"createTime"\s*:\s*"?(\d{9,11})"?/', $html, $ct) ? (int)$ct[1] : null,
                'desc'       => preg_match('/"desc"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $html, $d) ? stripcslashes($d[1]) : null,
            ], $tz, $username);
        }
        jitter(1.2);
    }
    return null;
}

function enrich_oembed(Client $c, array $video): array
{
    $d = $c->getJson('https://www.tiktok.com/oembed?url=' . urlencode($video['url']), 'https://www.tiktok.com/');
    if ($d) {
        $video['description'] = mb_substr((string)($d['title'] ?? ''), 0, DESC_MAX_CHARS);
        $video['author_username'] = $video['author_username'] ?: ($d['author_name'] ?? null);
        $video['cover_url'] = $d['thumbnail_url'] ?? null;
        $video['hashtags'] = extract_hashtags(['desc' => $d['title'] ?? '']);
        $video['source'] = 'oembed';
    }
    return $video;
}

function main(): void
{
    global $USER, $LIMIT, $COOKIES_FILE, $SLEEP_FACTOR, $MAX_PAGES, $DEBUG, $LOCAL_TZ,
           $VIDEO_IDS_FILE, $VIDEOS_INFO_FILE, $META_FILE, $STATE_FILE, $COOKIE_JAR;

    $t0 = microtime(true);
    $clean = ltrim($USER, '@');

    $known = load_known_ids($VIDEO_IDS_FILE);
    $meta  = load_meta($META_FILE);
    $info  = load_info($VIDEOS_INFO_FILE);
    $state = load_state($STATE_FILE);

    // Videos que ya tenían métricas antes de esta corrida: lo demás que se complete cuenta como "recuperado".
    $hadData = [];
    foreach ($info as $id => $v) if ($v['views'] !== null) $hadData[$id] = true;

    echo "[*] IDs acumulados: " . count($known) . " | con datos: " . count($hadData) . "\n";

    $checkpoint = function () use (&$known, &$info, &$state, $hadData, $VIDEOS_INFO_FILE, $STATE_FILE): void {
        save_info($VIDEOS_INFO_FILE, $known, $info);
        $pend = pending_ids($known, $info, $state['fallidos']);
        $state['pendientes']  = count($pend);
        $state['recuperados'] = count_recovered($info, $hadData);
        $state['total_ids']   = count($known);
        $state['con_datos']   = count(array_filter($info, fn($v) => $v['views'] !== null));
        $state['faltan']      = $state['pendientes'] > 0 || ($state['paginacion_posible'] && !$state['paginacion_completa']);
        save_state($STATE_FILE, $state);
    };

    // Estado inicial visible desde el primer segundo (la pantalla lo lee mientras trabaja).
    $state['recuperados'] = 0;
    $checkpoint();

    $c = new Client($COOKIE_JAR, $SLEEP_FACTOR);
    if ($COOKIES_FILE) $c->loadCookiesFromFile($COOKIES_FILE);
    $c->bootstrap();

    // ---------------------------------------------------------------- Fase 1: IDs
    echo "[*] Leyendo perfil @{$USER}...\n";
    [$secUid, $videos, ] = read_profile($c, $USER, $LOCAL_TZ, $DEBUG);

    $state['paginacion_posible'] = (bool)$secUid; // sin secUid no se puede recorrer el perfil
    $pendientesPrevios = pending_ids($known, $info, $state['fallidos']);
    if (!$videos && !$pendientesPrevios) { fwrite(STDERR, "[x] Sin IDs.\n"); exit(2); }

    $knownSet = array_fill_keys($known, true);
    $seen = $knownSet;

    // Lo que trae el HTML: lo nuevo se guarda arriba; lo conocido refresca sus métricas.
    $batch = [];
    foreach ($videos as $v) {
        $id = $v['video_id'];
        $info[$id] = isset($info[$id]) ? merge_video($info[$id], $v) : $v;
        $seen[$id] = true;
        if (isset($knownSet[$id])) continue;
        $ts = is_numeric($v['created_at_ts'] ?? null) ? (int)$v['created_at_ts'] : null;
        $batch[] = ['id' => $id, 'ts' => $ts];
    }
    if ($batch) { $known = save_ids($VIDEO_IDS_FILE, $known, $batch, $meta, $META_FILE); $checkpoint(); }

    if ($secUid) {
        $completaAntes = (bool)$state['paginacion_completa'];
        $retomarEn     = (int)$state['cursor'];
        echo $completaAntes
            ? "[*] Buscando videos nuevos...\n"
            : "[*] El recorrido anterior quedó incompleto: busco nuevos y luego continúo" . ($retomarEn > 0 ? " desde el cursor $retomarEn" : "") . ".\n";

        $cursor = 0; $stagnant = 0; $pages = 0; $fase = 'nuevos';
        while (count($seen) < $LIMIT && $pages < $MAX_PAGES) {
            $pages++;
            $prev  = $cursor;
            $data  = fetch_item_page_retry($c, $secUid, $cursor);
            $items = $data['itemList'] ?? [];
            if (!$items) { echo "    [x] item_list vacío, corto\n"; break; }

            $batch = []; $n = 0;
            foreach ($items as $raw) {
                $vid = (string)($raw['id'] ?? '');
                if ($vid === '') continue;
                $p = parse_item($raw, $LOCAL_TZ, $clean);
                if (isset($seen[$vid])) {
                    // Ya conocido: solo se actualizan sus métricas (alimenta la evolución en el tiempo).
                    if (isset($info[$vid])) $info[$vid] = merge_video($info[$vid], $p);
                    continue;
                }
                $seen[$vid] = true;
                $info[$vid] = $p;
                $batch[] = ['id' => $vid, 'ts' => is_numeric($p['created_at_ts'] ?? null) ? (int)$p['created_at_ts'] : null];
                $n++;
            }
            echo "    [{$fase}] p{$pages} +{$n} | únicos: " . count($seen) . "\n";
            if ($batch) $known = save_ids($VIDEO_IDS_FILE, $known, $batch, $meta, $META_FILE);

            $hasMore = !empty($data['hasMore']);
            $next = (int)($data['cursor'] ?? ($prev + 35));
            if ($next <= $prev) $next = $prev + 35;

            if (!$hasMore) {
                $state['paginacion_completa'] = true; $state['cursor'] = 0;
                $checkpoint();
                break;
            }

            $stagnant = $n === 0 ? $stagnant + 1 : 0;

            if ($fase === 'nuevos' && $stagnant >= 3) {
                if ($completaAntes) {
                    // Todo lo de arriba ya se conocía y el recorrido anterior estaba completo: nada más que buscar.
                    $state['paginacion_completa'] = true; $state['cursor'] = 0;
                    $checkpoint();
                    break;
                }
                $fase = 'retomar'; $stagnant = 0;
                if ($retomarEn > 0) $next = $retomarEn;
            }

            // Si se corta aquí, la próxima corrida sabe desde dónde seguir.
            if ($fase === 'retomar' || ($completaAntes && $n > 0)) {
                $state['paginacion_completa'] = false;
                $state['cursor'] = $next;
            }
            $checkpoint();
            $cursor = $next;
        }
        echo "[*] Páginas: $pages\n";
    } else {
        echo "[!] Sin secUid: no hay paginación.\n";
    }

    // ---------------------------------------------------------------- Fase 2: detalle, ID por ID
    $pend = pending_ids($known, $info, $state['fallidos']);
    if ($pend) {
        echo "[*] Faltan métricas de " . count($pend) . " video(s). Avanzando uno por uno...\n";
        $fails = 0; $ok = 0; $hechos = [];
        foreach ($pend as $vid) {
            $full = fetch_detail($c, $vid, $clean, $LOCAL_TZ);
            if ($full && ($full['views'] !== null || $full['created_at_ts'] !== null)) {
                $info[$vid] = isset($info[$vid]) ? merge_video($info[$vid], $full) : $full;
                unset($state['fallidos'][$vid]);
                $hechos[] = $vid; $fails = 0; $ok++;
                if ($ok % 5 === 0) { echo "\n    ok: $ok\n"; $checkpoint(); }
            } else {
                $state['fallidos'][$vid] = ($state['fallidos'][$vid] ?? 0) + 1;
                $fails++;
                echo "\n    [!] $vid sin detalle (intento " . $state['fallidos'][$vid] . ")\n";
                if ($fails >= 8) { echo "    [x] 8 fallos seguidos, corto.\n"; break; }
            }
        }
        echo "\n    [detalle] completados: $ok\n";

        $sinDesc = array_filter($hechos, fn($id) => empty($info[$id]['description']));
        if ($sinDesc) {
            echo "[*] Títulos por oembed (" . count($sinDesc) . ")...\n";
            foreach ($sinDesc as $id) $info[$id] = enrich_oembed($c, $info[$id]);
        }
    } else {
        echo "[*] Todos los IDs ya tienen métricas.\n";
    }

    $checkpoint();
    echo "[+] IDs totales: " . count($known) . " -> $VIDEO_IDS_FILE\n";
    echo "[+] Info -> $VIDEOS_INFO_FILE\n";
    echo "[+] Recuperados: " . $state['recuperados'] . ($state['faltan'] ? " | faltan: " . $state['pendientes'] . "\n" : "\n");

    printf("\n[OK] %.1fs\n", microtime(true) - $t0);
}

// pruebas/probar.php define CAPCHER_PRUEBAS para usar las funciones sin lanzar la recolección.
if (!defined('CAPCHER_PRUEBAS')) {
    main();
}
