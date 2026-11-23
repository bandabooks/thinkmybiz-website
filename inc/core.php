<?php
// Shared core: configuration, private SQLite store, mailer and helpers. Not web-accessible (.htaccess blocks /inc/).
// Written for PHP 7.4+.
if (defined('TMB_CORE')) return;
define('TMB_CORE', 1);

$GLOBALS['CFG'] = require __DIR__ . '/config.php';
function cfg($k) { return $GLOBALS['CFG'][$k] ?? null; }
date_default_timezone_set(cfg('timezone'));

// ---------------------------------------------------------------- storage
function data_dir() {
  $d = cfg('data_dir');
  if (!is_dir($d)) { @mkdir($d, 0700, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
  return $d;
}

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;
  $pdo = new PDO('sqlite:' . data_dir() . '/tmb.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
  migrate($pdo);
  return $pdo;
}

function migrate(PDO $pdo) {
  $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
  $steps = [
    1 => "
      CREATE TABLE leads(id INTEGER PRIMARY KEY, ref TEXT UNIQUE, created_at TEXT, updated_at TEXT,
        name TEXT, email TEXT, phone TEXT, province TEXT, town TEXT, service TEXT, message TEXT,
        status TEXT DEFAULT 'new', next_follow_up TEXT, source TEXT, medium TEXT, campaign TEXT,
        referrer TEXT, landing TEXT, device TEXT, consent_at TEXT, email_sent INTEGER DEFAULT 0);
      CREATE INDEX leads_status ON leads(status); CREATE INDEX leads_created ON leads(created_at);
      CREATE TABLE lead_notes(id INTEGER PRIMARY KEY, lead_id INTEGER REFERENCES leads(id), created_at TEXT, kind TEXT, body TEXT);
      CREATE TABLE pageviews(id INTEGER PRIMARY KEY, pvid TEXT, ts TEXT, day TEXT, path TEXT, ref_host TEXT,
        source TEXT, medium TEXT, campaign TEXT, device TEXT, vid TEXT, dur_ms INTEGER DEFAULT 0, is_entry INTEGER DEFAULT 0);
      CREATE INDEX pv_day ON pageviews(day); CREATE INDEX pv_pvid ON pageviews(pvid);
      CREATE TABLE events(id INTEGER PRIMARY KEY, ts TEXT, day TEXT, name TEXT, path TEXT, label TEXT, vid TEXT);
      CREATE INDEX ev_day ON events(day);
      CREATE TABLE subscribers(id INTEGER PRIMARY KEY, email TEXT UNIQUE, name TEXT, status TEXT, token TEXT, source TEXT,
        created_at TEXT, confirmed_at TEXT, unsub_at TEXT);
      CREATE TABLE campaigns(id INTEGER PRIMARY KEY, created_at TEXT, name TEXT, channel TEXT, slug TEXT, landing TEXT, url TEXT);
      CREATE TABLE posts(id INTEGER PRIMARY KEY, created_at TEXT, channel TEXT, body TEXT, link TEXT, status TEXT DEFAULT 'draft',
        published_at TEXT, external_id TEXT);
      CREATE TABLE users(id INTEGER PRIMARY KEY, email TEXT UNIQUE, pass_hash TEXT, totp_secret TEXT, created_at TEXT, last_login TEXT);
      CREATE TABLE tokens(id INTEGER PRIMARY KEY, purpose TEXT, hash TEXT, expires_at TEXT, used INTEGER DEFAULT 0);
      CREATE TABLE attempts(id INTEGER PRIMARY KEY, kind TEXT, who TEXT, ts INTEGER);
      CREATE TABLE settings(k TEXT PRIMARY KEY, v TEXT);
      CREATE TABLE audit(id INTEGER PRIMARY KEY, ts TEXT, actor TEXT, action TEXT, detail TEXT);",
  ];
  foreach ($steps as $n => $sql) {
    if ($v >= $n) continue;
    $pdo->beginTransaction(); $pdo->exec($sql); $pdo->exec("PRAGMA user_version=$n"); $pdo->commit();
  }
}

function setting($k, $default = '') {
  $s = db()->prepare('SELECT v FROM settings WHERE k=?'); $s->execute([$k]); $v = $s->fetchColumn();
  return $v === false ? $default : $v;
}
function set_setting($k, $v) { db()->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k, $v]); }
function audit($actor, $action, $detail = '') {
  db()->prepare('INSERT INTO audit(ts,actor,action,detail) VALUES(?,?,?,?)')->execute([now(), $actor, $action, mb_substr($detail, 0, 500)]);
}
function now() { return date('Y-m-d H:i:s'); }

// ---------------------------------------------------------------- privacy helpers
// Visitor ID: hash of IP + browser + a salt that changes every day and is never kept, so visits are counted per day
// without storing an IP address or setting a cookie, and cannot be linked across days.
function daily_salt() {
  $day = date('Y-m-d');
  if (setting('salt_day') !== $day) { set_setting('salt_day', $day); set_setting('salt', bin2hex(random_bytes(16))); }
  return setting('salt');
}
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function visitor_id() { return substr(hash('sha256', daily_salt() . client_ip() . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16); }
function ip_key() { return substr(hash('sha256', 'tmb-rl' . client_ip()), 0, 24); }
function device_class($ua) {
  if (preg_match('/iPad|Tablet/i', $ua)) return 'tablet';
  if (preg_match('/Mobi|Android|iPhone/i', $ua)) return 'mobile';
  return 'desktop';
}
function is_bot($ua) { return $ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|monitor|curl|wget|python|facebookexternalhit/i', $ua); }

// Turn UTM tags or a referrer into a source / medium pair.
function classify_source($utm_source, $utm_medium, $ref_host) {
  if ($utm_source !== '') return [strtolower($utm_source), strtolower($utm_medium ?: 'campaign')];
  $h = strtolower($ref_host);
  if ($h === '' ) return ['direct', 'none'];
  $map = ['google.' => ['google', 'organic'], 'bing.' => ['bing', 'organic'], 'yahoo.' => ['yahoo', 'organic'], 'duckduckgo.' => ['duckduckgo', 'organic'],
          'facebook.' => ['facebook', 'social'], 'fb.' => ['facebook', 'social'], 'instagram.' => ['instagram', 'social'],
          'linkedin.' => ['linkedin', 'social'], 'lnkd.in' => ['linkedin', 'social'], 't.co' => ['x', 'social'], 'twitter.' => ['x', 'social'],
          'tiktok.' => ['tiktok', 'social'], 'youtube.' => ['youtube', 'social'], 'whatsapp.' => ['whatsapp', 'social'],
          'chatgpt.' => ['chatgpt', 'ai'], 'openai.' => ['chatgpt', 'ai'], 'claude.ai' => ['claude', 'ai'], 'perplexity.' => ['perplexity', 'ai'],
          'gemini.google' => ['gemini', 'ai'], 'copilot.' => ['copilot', 'ai']];
  foreach ($map as $k => $v) if (strpos($h, $k) !== false) return $v;
  return [$h, 'referral'];
}

// Simple rate limit: at most $max actions of $kind per $window seconds for this visitor.
function rate_limited($kind, $max, $window) {
  $who = ip_key(); $since = time() - $window;
  db()->prepare('DELETE FROM attempts WHERE ts < ?')->execute([time() - 86400]);
  $s = db()->prepare('SELECT COUNT(*) FROM attempts WHERE kind=? AND who=? AND ts>=?'); $s->execute([$kind, $who, $since]);
  return (int)$s->fetchColumn() >= $max;
}
function note_attempt($kind) { db()->prepare('INSERT INTO attempts(kind,who,ts) VALUES(?,?,?)')->execute([$kind, ip_key(), time()]); }

function svc_label($slug) { $s = cfg('services'); return $s[$slug] ?? $slug; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------------------------------------------------------------- mail
// Authenticated SMTP for the practice mailbox when credentials exist outside the web root (.tmb-smtp.php),
// otherwise PHP mail() with the practice mailbox as sender (Hostinger only delivers mail from existing mailboxes).
function smtp_conf() {
  static $c = false;
  if ($c !== false) return $c;
  $f = dirname(__DIR__, 2) . '/.tmb-smtp.php';
  $c = is_readable($f) ? (include $f) : null;
  if (!is_array($c) || empty($c['host']) || empty($c['user']) || !isset($c['pass'])) $c = null;
  return $c;
}
function smtp_send(array $c, $to, $subjectEnc, $body, array $headers) {
  $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['host']];
  if (!empty($c['cafile'])) $ssl['cafile'] = $c['cafile'];
  $fp = @stream_socket_client('ssl://' . $c['host'] . ':' . (int)($c['port'] ?? 465), $en, $es, 15,
                              STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
  if (!$fp) { error_log("tmb SMTP connect failed: $es"); return false; }
  stream_set_timeout($fp, 15);
  $step = function ($line, array $ok) use ($fp) {
    if ($line !== null) fwrite($fp, $line . "\r\n");
    $resp = '';
    while (($l = fgets($fp, 1024)) !== false) { $resp .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; }
    if (!in_array((int)substr($resp, 0, 3), $ok, true)) { error_log('tmb SMTP: ' . trim($resp)); return false; }
    return true;
  };
  $msg = implode("\r\n", array_merge([
      'Date: ' . date('r'), 'To: <' . $to . '>', 'Subject: ' . $subjectEnc,
      'Message-ID: <' . bin2hex(random_bytes(12)) . '@thinkmybiz.co.za>', 'MIME-Version: 1.0',
      'Content-Transfer-Encoding: base64'], $headers)) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
  $ok = $step(null, [220]) && $step('EHLO thinkmybiz.co.za', [250]) && $step('AUTH LOGIN', [334])
     && $step(base64_encode($c['user']), [334]) && $step(base64_encode($c['pass']), [235])
     && $step('MAIL FROM:<' . $c['user'] . '>', [250]) && $step('RCPT TO:<' . $to . '>', [250, 251])
     && $step('DATA', [354]) && $step($msg . "\r\n.", [250]);
  @fwrite($fp, "QUIT\r\n"); fclose($fp);
  return $ok;
}
function send_mail_raw($to, $subjectEnc, $body, array $headers) {
  $from = cfg('email');
  if (getenv('TMB_MAIL_LOG')) { file_put_contents(getenv('TMB_MAIL_LOG'), "TO: $to\n" . implode("\n", $headers) . "\nSUBJ: " . $subjectEnc . "\n\n$body\n-----\n", FILE_APPEND); return true; }
  $c = smtp_conf();
  if ($c && smtp_send($c, $to, $subjectEnc, $body, $headers)) return true;
  return @mail($to, $subjectEnc, $body, implode("\r\n", $headers), '-f' . $from);
}
// Plain-text mail from the practice mailbox. $replyTo defaults to the practice mailbox.
function send_mail($to, $subject, $body, $replyTo = null, array $extra = []) {
  $to = preg_replace('/[\r\n]+/', '', $to);
  $headers = array_merge([
    'From: ' . cfg('short') . ' <' . cfg('email') . '>',
    'Reply-To: ' . preg_replace('/[\r\n]+/', ' ', $replyTo ?: (cfg('short') . ' <' . cfg('email') . '>')),
    'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: thinkmybiz.co.za'], $extra);
  return send_mail_raw($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function json_out($code, $arr) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr); exit; }
