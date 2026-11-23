<?php
// Portal security, layout and shared helpers.
if (!defined('TMB_PORTAL')) { http_response_code(404); exit; }
require dirname(__DIR__) . '/inc/core.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; form-action 'self' https://www.facebook.com; frame-ancestors 'none'");

session_name('tmb_portal');
session_set_cookie_params(['lifetime' => 0, 'path' => '/portal/', 'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
                           'httponly' => true, 'samesite' => 'Strict']);
session_start();

const IDLE_LIMIT = 3600;       // sign out after 1 hour without activity
const ABS_LIMIT  = 12 * 3600;  // and after 12 hours in any case

function user() {
  if (empty($_SESSION['uid'])) return null;
  if (time() - ($_SESSION['seen'] ?? 0) > IDLE_LIMIT || time() - ($_SESSION['born'] ?? 0) > ABS_LIMIT) { logout(); return null; }
  $_SESSION['seen'] = time();
  $s = db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); return $s->fetch() ?: null;
}
function login_user($u) {
  session_regenerate_id(true);
  $_SESSION['uid'] = $u['id']; $_SESSION['born'] = $_SESSION['seen'] = time();
  db()->prepare('UPDATE users SET last_login=? WHERE id=?')->execute([now(), $u['id']]);
  audit($u['email'], 'login');
}
function logout() { $_SESSION = []; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); }

function csrf() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf() {
  $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
  if (!is_string($t) || !hash_equals(csrf(), $t)) { http_response_code(400); exit('Session expired. Go back, refresh the page and try again.'); }
}
function redirect($q) { header('Location: /portal/' . $q); exit; }
function flash($msg = null, $kind = 'ok') {
  if ($msg !== null) { $_SESSION['flash'] = [$msg, $kind]; return ''; }
  $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
  return $f ? '<div class="flash ' . $f[1] . '">' . h($f[0]) . '</div>' : '';
}

// ---------------------------------------------------------------- one-time links (setup and password reset)
function make_token($purpose, $minutes) {
  $t = bin2hex(random_bytes(24));
  db()->prepare('INSERT INTO tokens(purpose,hash,expires_at) VALUES(?,?,?)')->execute([$purpose, hash('sha256', $t), date('Y-m-d H:i:s', time() + 60 * $minutes)]);
  return $t;
}
function use_token($purpose, $t, $consume) {
  $s = db()->prepare('SELECT id FROM tokens WHERE purpose=? AND hash=? AND used=0 AND expires_at>=?');
  $s->execute([$purpose, hash('sha256', (string)$t), now()]); $id = $s->fetchColumn();
  if ($id && $consume) db()->prepare('UPDATE tokens SET used=1 WHERE purpose=?')->execute([$purpose]);
  return (bool)$id;
}

// ---------------------------------------------------------------- TOTP (RFC 6238, works with Google/Microsoft Authenticator)
function b32_encode($bin) {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
  foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
  foreach (str_split($bits, 5) as $chunk) $out .= $a[bindec(str_pad($chunk, 5, '0'))];
  return $out;
}
function b32_decode($s) {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
  foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
  foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
  return $out;
}
function totp_at($secret, $step) {
  $h = hash_hmac('sha1', pack('N*', 0) . pack('N*', $step), b32_decode($secret), true);
  $o = ord($h[19]) & 0xf;
  $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
  return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_ok($secret, $code) {
  $code = preg_replace('/\D/', '', (string)$code); if (strlen($code) !== 6) return false;
  $t = (int)floor(time() / 30);
  for ($i = -1; $i <= 1; $i++) if (hash_equals(totp_at($secret, $t + $i), $code)) return true;
  return false;
}

// ---------------------------------------------------------------- layout
function nav_link($p, $label, $cur) { return '<a href="/portal/?p=' . $p . '"' . ($cur === $p ? ' class="on"' : '') . '>' . $label . '</a>'; }
function layout($title, $body, $cur = '') {
  $u = user();
  $v = substr(md5_file(__DIR__ . '/assets/portal.css') . md5_file(__DIR__ . '/assets/portal.js'), 0, 8);
  $due = 0;
  if ($u) { $s = db()->prepare("SELECT COUNT(*) FROM leads WHERE status IN ('new','contacted','consultation','quoted') AND next_follow_up<=?"); $s->execute([date('Y-m-d')]); $due = (int)$s->fetchColumn(); }
  echo '<!doctype html><html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
     . '<meta name="robots" content="noindex,nofollow"><title>' . h($title) . ' · ' . h(cfg('short')) . ' portal</title>'
     . '<link rel="icon" href="/assets/img/favicon.png"><link rel="stylesheet" href="/portal/assets/portal.css?v=' . $v . '"></head><body>';
  if ($u) {
    echo '<header class="top"><a class="brand" href="/portal/"><img src="/assets/img/favicon.png" alt="">' . h(cfg('short')) . ' <span>Practice portal</span></a>'
       . '<button class="mt" type="button" aria-label="Menu" data-toggle="nav">☰</button><nav id="nav">'
       . nav_link('dashboard', 'Dashboard', $cur) . nav_link('leads', 'Leads' . ($due ? ' <b class="badge">' . $due . '</b>' : ''), $cur)
       . nav_link('campaigns', 'Campaigns', $cur) . nav_link('social', 'Social posts', $cur) . nav_link('subscribers', 'Subscribers', $cur)
       . nav_link('settings', 'Settings', $cur)
       . '<a href="/" target="_blank" rel="noopener">View site ↗</a>'
       . '<form method="post" action="/portal/?p=logout" class="inline">' . csrf_field() . '<button class="link">Sign out</button></form></nav></header>';
  }
  echo '<main class="wrap">' . flash() . $body . '</main><script src="/portal/assets/portal.js?v=' . $v . '"></script></body></html>';
  exit;
}
function card($title, $inner, $extra = '') { return '<section class="pcard ' . $extra . '"><h2>' . $title . '</h2>' . $inner . '</section>'; }
function table($heads, $rows, $empty = 'Nothing yet.') {
  if (!$rows) return '<p class="muted">' . $empty . '</p>';
  $o = '<div class="tw"><table><thead><tr>';
  foreach ($heads as $hd) $o .= '<th>' . $hd . '</th>';
  $o .= '</tr></thead><tbody>';
  foreach ($rows as $r) { $o .= '<tr>'; foreach ($r as $i => $c) $o .= '<td data-label="' . strip_tags($heads[$i]) . '">' . $c . '</td>'; $o .= '</tr>'; }
  return $o . '</tbody></table></div>';
}
function wa_number($phone) {
  $d = preg_replace('/\D/', '', (string)$phone);
  if (strlen($d) === 10 && $d[0] === '0') return '27' . substr($d, 1);
  if (strlen($d) >= 11) return $d;
  return '';
}
const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'consultation' => 'Consultation booked', 'quoted' => 'Quoted',
                  'won' => 'Won (client)', 'lost' => 'Lost', 'spam' => 'Spam'];
function status_pill($s) { return '<span class="pill s-' . h($s) . '">' . h(STATUSES[$s] ?? $s) . '</span>'; }
