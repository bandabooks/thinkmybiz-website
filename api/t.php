<?php
// Cookieless first-party analytics. Records page views, time on page and lead actions.
// Stores no IP address, no cookie and no personal data: see daily-salted visitor_id() in inc/core.php.
require dirname(__DIR__) . '/inc/core.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (is_bot($ua) || ($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') { http_response_code(204); exit; }
$raw = file_get_contents('php://input', false, null, 0, 4096);
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); exit; }
$str = function ($k, $max) use ($d) { return isset($d[$k]) && is_scalar($d[$k]) ? mb_substr(preg_replace('/[\x00-\x1f]/', '', (string)$d[$k]), 0, $max) : ''; };
$path = $str('p', 200);
if ($path === '' || $path[0] !== '/' || strpos($path, '/portal') === 0) { http_response_code(204); exit; }
try {
  if (rate_limited('t', 300, 600)) { http_response_code(204); exit; }
  note_attempt('t');
  $t = $str('t', 8); $day = date('Y-m-d');
  if ($t === 'pv') {
    $refHost = strtolower((string)parse_url($str('r', 300), PHP_URL_HOST));
    if (strpos($refHost, 'thinkmybiz.co.za') !== false) $refHost = '';
    $entry = !empty($d['e']) ? 1 : 0;
    list($src, $med) = $entry ? classify_source($str('us', 60), $str('um', 60), $refHost) : ['', ''];
    db()->prepare('INSERT INTO pageviews(pvid,ts,day,path,ref_host,source,medium,campaign,device,vid,is_entry) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$str('id', 24), now(), $day, $path, $refHost, $src, $med, $entry ? $str('uc', 100) : '', device_class($ua), visitor_id(), $entry]);
  } elseif ($t === 'end') {
    $ms = max(0, min(1800000, (int)($d['d'] ?? 0)));
    db()->prepare('UPDATE pageviews SET dur_ms=? WHERE pvid=? AND day=?')->execute([$ms, $str('id', 24), $day]);
  } elseif ($t === 'ev') {
    $name = preg_replace('/[^a-z0-9_]/', '', strtolower($str('n', 40)));
    if ($name !== '') db()->prepare('INSERT INTO events(ts,day,name,path,label,vid) VALUES(?,?,?,?,?,?)')
        ->execute([now(), $day, $name, $path, $str('l', 120), visitor_id()]);
  }
} catch (Throwable $e) { error_log('t.php: ' . $e->getMessage()); }
http_response_code(204);
