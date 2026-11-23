<?php
require dirname(__DIR__) . '/inc/core.php';
header('X-Robots-Tag: noindex, nofollow');
$t = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$ok = false;
if (strlen($t) === 32) {
  $s = db()->prepare("UPDATE subscribers SET status='active', confirmed_at=? WHERE token=? AND status IN ('pending','active')");
  $s->execute([now(), $t]); $ok = $s->rowCount() > 0;
}
header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex');
$msg = $ok ? 'Thank you. You are subscribed to SARS deadline alerts.' : 'This confirmation link is not valid or has already been used.';
echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Subscription</title><link rel="stylesheet" href="/assets/css/style.css">'
   . '<main class="section"><div class="container" style="max-width:640px"><div class="card"><h1 style="font-size:1.5rem">' . h($msg) . '</h1><p><a href="/insights/">Read our latest insights →</a></p></div></div></main>';
