<?php
// SARS deadline alerts: double opt-in. A confirmation email is sent; nobody is added until they click it.
require dirname(__DIR__) . '/inc/core.php';
header('X-Robots-Tag: noindex, nofollow');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false]);
$email = trim((string)($_POST['email'] ?? '')); $name = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($_POST['name'] ?? ''))), 0, 80);
if (!empty($_POST['hp_check'])) json_out(422, ['ok' => false, 'error' => 'Please try again.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) json_out(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
if (empty($_POST['consent'])) json_out(422, ['ok' => false, 'error' => 'Please tick the box to agree to receive the alerts.']);
try {
  if (rate_limited('sub', 5, 3600)) json_out(429, ['ok' => false, 'error' => 'Too many requests. Please try later.']);
  note_attempt('sub');
  $s = db()->prepare('SELECT * FROM subscribers WHERE email=?'); $s->execute([strtolower($email)]); $row = $s->fetch();
  if ($row && $row['status'] === 'active') json_out(200, ['ok' => true, 'msg' => 'You are already subscribed.']);
  $token = bin2hex(random_bytes(16));
  if ($row) db()->prepare("UPDATE subscribers SET token=?, status='pending', name=? WHERE id=?")->execute([$token, $name, $row['id']]);
  else db()->prepare("INSERT INTO subscribers(email,name,status,token,source,created_at) VALUES(?,?,?,?,?,?)")
          ->execute([strtolower($email), $name, 'pending', $token, mb_substr((string)($_POST['src'] ?? ''), 0, 120), now()]);
  $link = cfg('domain') . '/api/confirm.php?t=' . $token;
  send_mail($email, 'Please confirm: SARS deadline alerts from ' . cfg('short'),
    "Hello" . ($name ? " $name" : '') . ",\n\nPlease confirm that you would like to receive SARS deadline alerts and practical tax updates from "
    . cfg('practice') . ":\n\n$link\n\nIf you did not ask for this, ignore this email and you will not be added.\n\n"
    . "You can unsubscribe at any time with the link in every alert.\n\n" . cfg('signature') . "\n");
  json_out(200, ['ok' => true, 'msg' => 'Almost done: please check your inbox and click the confirmation link.']);
} catch (Throwable $e) { error_log('subscribe: ' . $e->getMessage()); json_out(500, ['ok' => false, 'error' => 'Could not subscribe right now. Please try later.']); }
