<?php
require dirname(__DIR__) . '/inc/core.php';
header('X-Robots-Tag: noindex, nofollow');
$t = preg_replace('/[^a-f0-9]/', '', (string)($_REQUEST['t'] ?? ''));
$done = false;
if (strlen($t) === 32 && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $s = db()->prepare("UPDATE subscribers SET status='unsubscribed', unsub_at=? WHERE token=?"); $s->execute([now(), $t]); $done = $s->rowCount() > 0;
}
header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex');
echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribe</title><link rel="stylesheet" href="/assets/css/style.css">'
   . '<main class="section"><div class="container" style="max-width:640px"><div class="card">';
if ($done) echo '<h1 style="font-size:1.5rem">You have been unsubscribed.</h1><p>You will not receive further alerts.</p>';
else echo '<h1 style="font-size:1.5rem">Unsubscribe from SARS deadline alerts</h1><form method="post"><input type="hidden" name="t" value="' . h($t) . '"><button class="btn btn-primary" type="submit">Unsubscribe</button></form>';
echo '</div></div></main>';
