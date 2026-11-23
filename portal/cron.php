<?php
// Daily job (run from the hosting cron at 07:00 SAST): emails the practice a summary of new leads, follow-ups due and yesterday's visits.
// Command-line only; returns 404 if opened in a browser.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/inc/core.php';
if (in_array('--check', $argv, true)) {   // health check: prints environment facts, sends nothing
  echo 'PHP ' . PHP_VERSION . ' | sqlite ' . (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true) ? 'yes' : 'NO') . ' | curl ' . (function_exists('curl_init') ? 'yes' : 'NO') . "\n";
  echo 'data dir ' . cfg('data_dir') . ' writable: ' . (is_writable(data_dir()) ? 'yes' : 'NO') . "\n";
  echo 'db tables: ' . db()->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn() . ' | sqlite ' . db()->query('select sqlite_version()')->fetchColumn() . "\n";
  echo 'leads ' . db()->query('SELECT COUNT(*) FROM leads')->fetchColumn() . ' | pageviews ' . db()->query('SELECT COUNT(*) FROM pageviews')->fetchColumn() . ' | users ' . db()->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";
  echo 'timezone ' . date('Y-m-d H:i T') . "\n";
  exit(0);
}
if (setting('digest_on', '1') !== '1') exit(0);
$today = date('Y-m-d'); $y = date('Y-m-d', strtotime('-1 day'));
if (setting('digest_last') === $today && !in_array('--force', $argv, true)) exit(0);
$q = function ($sql, $a = []) { $s = db()->prepare($sql); $s->execute($a); return $s; };
$new = $q("SELECT * FROM leads WHERE created_at>=? AND status!='spam' ORDER BY id", [$y . ' 00:00:00'])->fetchAll();
$due = $q("SELECT * FROM leads WHERE status IN ('new','contacted','consultation','quoted') AND next_follow_up<=? ORDER BY next_follow_up", [$today])->fetchAll();
$unsent = (int)$q("SELECT COUNT(*) FROM leads WHERE email_sent=0 AND created_at>=?", [$y . ' 00:00:00'])->fetchColumn();
$vis = (int)$q('SELECT COUNT(DISTINCT vid) FROM pageviews WHERE day=?', [$y])->fetchColumn();
$pv = (int)$q('SELECT COUNT(*) FROM pageviews WHERE day=?', [$y])->fetchColumn();
$top = $q('SELECT path,COUNT(*) c FROM pageviews WHERE day=? GROUP BY path ORDER BY c DESC LIMIT 5', [$y])->fetchAll();
$src = $q('SELECT source,COUNT(*) c FROM pageviews WHERE day=? AND is_entry=1 GROUP BY source ORDER BY c DESC LIMIT 5', [$y])->fetchAll();
$ev = $q('SELECT name,COUNT(*) c FROM events WHERE day=? GROUP BY name ORDER BY c DESC', [$y])->fetchAll();
if (!$new && !$due && !$vis) { set_setting('digest_last', $today); exit(0); }
$P = cfg('domain') . '/portal/';
$b = "Good morning,\n\nYour " . cfg('short') . " summary for " . date('l j F Y') . ".\n\n";
$b .= "NEW ENQUIRIES SINCE YESTERDAY: " . count($new) . "\n";
foreach ($new as $l) $b .= "- {$l['ref']}  {$l['name']}  ·  {$l['service']}  ·  from {$l['source']}\n  {$P}?p=lead&id={$l['id']}\n";
if ($unsent) $b .= "\n! $unsent enquiry email(s) may not have been delivered; they are in the portal.\n";
$b .= "\nFOLLOW-UPS DUE: " . count($due) . "\n";
foreach (array_slice($due, 0, 15) as $l) $b .= "- {$l['name']}  ·  {$l['service']}  ·  {$l['status']}  ·  due {$l['next_follow_up']}\n  {$P}?p=lead&id={$l['id']}\n";
$b .= "\nWEBSITE YESTERDAY: $vis visitors, $pv page views\n";
if ($src) $b .= "Sources: " . implode(', ', array_map(function ($r) { return $r['source'] . ' ' . $r['c']; }, $src)) . "\n";
if ($top) $b .= "Top pages: " . implode(', ', array_map(function ($r) { return $r['path'] . ' ' . $r['c']; }, $top)) . "\n";
if ($ev) $b .= "Actions: " . implode(', ', array_map(function ($r) { return $r['name'] . ' ' . $r['c']; }, $ev)) . "\n";
$b .= "\nDashboard: {$P}\n\n(Automatic summary. Turn it off under Portal → Settings.)\n";
if (send_mail(cfg('email'), cfg('short') . ' daily summary: ' . count($new) . ' new, ' . count($due) . ' due', $b, null, ['Auto-Submitted: auto-generated'])) set_setting('digest_last', $today);
