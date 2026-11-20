<?php
// Think Mybiz Accountants — contact form handler (hand-edited; not generated).
// Sends website enquiries to the practice mailbox. No data is stored on the server.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$TO   = 'accounts@thinkmybiz.co.za';
$FROM = 'website@thinkmybiz.co.za';

function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Method not allowed.']);

// Spam checks: honeypot field must be empty; form must take at least 3 seconds to complete.
if (!empty($_POST['company_website'])) out(200, ['ok' => true]);
$ts = isset($_POST['ts']) ? (int)$_POST['ts'] : 0;
if ($ts > 0 && (round(microtime(true) * 1000) - $ts) < 3000) out(200, ['ok' => true]);

function clean($k, $max) {
  $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
  $v = str_replace(["\r", "\0"], '', $v);
  return mb_substr($v, 0, $max);
}
$name     = clean('name', 120);
$email    = clean('email', 160);
$phone    = clean('phone', 40);
$location = clean('location', 120);
$service  = clean('service', 120);
$message  = clean('message', 5000);
$consent  = !empty($_POST['consent']);

if ($name === '' || $message === '') out(422, ['ok' => false, 'error' => 'Please give your name and a short message.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
if (!$consent) out(422, ['ok' => false, 'error' => 'Please confirm that we may use your details to respond.']);

// Single-line header values only.
$name1 = preg_replace('/[\n]+/', ' ', $name);
$serv1 = preg_replace('/[\n]+/', ' ', $service ?: 'General enquiry');

$subject = 'Website enquiry: ' . $serv1 . ' — ' . $name1;
$body = "New enquiry from thinkmybiz.co.za\n\n"
      . "Name: $name1\nEmail: $email\nPhone: $phone\nProvince/town: $location\nService: $serv1\n"
      . "Received: " . date('Y-m-d H:i') . " (SAST)\n\nMessage:\n$message\n\n"
      . "The sender consented to their details being used to respond to this enquiry (POPIA).\n";

$headers = [
  'From: Think Mybiz Website <' . $FROM . '>',
  'Reply-To: ' . $name1 . ' <' . $email . '>',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Mailer: thinkmybiz.co.za',
];
date_default_timezone_set('Africa/Johannesburg');
$sent = @mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $FROM);

if (!$sent) out(500, ['ok' => false, 'error' => 'Your message could not be sent. Please email accounts@thinkmybiz.co.za or call 066 210 5631.']);
out(200, ['ok' => true]);
