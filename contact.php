<?php
// Think Mybiz Accountants — contact form handler (hand-edited; not generated).
// Emails each enquiry to the practice mailbox and records it, with its reference and source, in the private
// lead register (outside the web root) for follow-up in the portal. Email delivery never depends on the register.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/inc/core.php';
$TO   = cfg('email');
$FROM = cfg('email'); // must be an existing mailbox: Hostinger drops PHP mail from addresses that do not exist

function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Method not allowed.']);


function clean($k, $max) {
  $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
  $v = str_replace(["\r", "\0"], '', $v);
  return mb_substr($v, 0, $max);
}
$name     = clean('name', 120);
$email    = clean('email', 160);
$phone    = clean('phone', 40);
$province = clean('province', 40);
$town     = clean('town', 80);
$town_oth = clean('town_other', 80);
$PROVINCES = ['Gauteng', 'Limpopo', 'Mpumalanga', 'North West', 'KwaZulu-Natal', 'Western Cape', 'Eastern Cape',
              'Free State', 'Northern Cape', 'Outside South Africa'];
// Province is typed into a searchable field: accept any letter case, store the canonical name.
foreach ($PROVINCES as $pv) { if (strcasecmp($pv, $province) === 0) { $province = $pv; break; } }
if ($town === 'Other town or village' || $province === 'Outside South Africa' || ($town === '' && $town_oth !== '')) $town = $town_oth;
$town = preg_replace('/[\n]+/', ' ', $town);
$service  = clean('service', 120);
$message  = clean('message', 5000);
$consent  = !empty($_POST['consent']);

if ($name === '' || $message === '') out(422, ['ok' => false, 'error' => 'Please give your name and a short message.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
if (!in_array($province, $PROVINCES, true)) out(422, ['ok' => false, 'error' => 'Please select your province.']);
if (!$consent) out(422, ['ok' => false, 'error' => 'Please confirm that we may use your details to respond.']);

// Spam checks run only after the real validation, so a person is never shown a false "sent".
// Honeypot must be empty; a submission under 2 seconds after page load is treated as automated.
if (!empty($_POST['hp_check'])) out(422, ['ok' => false, 'error' => 'Your message could not be sent. Please email accounts@thinkmybiz.co.za or call 066 210 5631.']);
$ts = isset($_POST['ts']) ? (int)$_POST['ts'] : 0;
if ($ts > 0 && (round(microtime(true) * 1000) - $ts) < 2000) out(422, ['ok' => false, 'error' => 'Please take a moment to check your details, then send again.']);

// Single-line header values only.
$name1 = preg_replace('/[\n]+/', ' ', $name);
$SERVICE_LABELS = cfg('services');
if (isset($SERVICE_LABELS[$service])) $service = $SERVICE_LABELS[$service];
$serv1 = preg_replace('/[\n]+/', ' ', $service ?: 'General enquiry');

// Where the visitor came from (first page of their visit), captured by the site script.
$a_src = clean('attr_source', 60); $a_med = clean('attr_medium', 60); $a_cmp = clean('attr_campaign', 100);
$a_ref = clean('attr_ref', 200);   $a_land = clean('attr_landing', 200);
$refHost = $a_ref !== '' ? (string)parse_url($a_ref, PHP_URL_HOST) : '';
list($srcName, $srcMed) = classify_source($a_src, $a_med, $refHost);
if ($refHost !== '' && stripos($refHost, 'thinkmybiz.co.za') !== false) { $srcName = 'direct'; $srcMed = 'none'; }

$ref = 'TMB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$leadId = null;
try {
  if (rate_limited('enquiry', 8, 3600)) out(429, ['ok' => false, 'error' => 'Too many enquiries from this connection. Please call or WhatsApp 066 210 5631.']);
  note_attempt('enquiry');
  db()->prepare('INSERT INTO leads(ref,created_at,updated_at,name,email,phone,province,town,service,message,status,next_follow_up,source,medium,campaign,referrer,landing,device,consent_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$ref, now(), now(), $name, $email, $phone, $province, $town, $service ?: 'General enquiry', $message, 'new',
                 date('Y-m-d'), $srcName, $srcMed, $a_cmp, $refHost, $a_land, device_class($_SERVER['HTTP_USER_AGENT'] ?? ''), now()]);
  $leadId = (int)db()->lastInsertId();
} catch (Throwable $e) { error_log('contact.php lead register: ' . $e->getMessage()); }

$subject = 'Website enquiry ' . $ref . ': ' . $serv1 . ' — ' . $name1;
$body = "New enquiry from thinkmybiz.co.za\n\n"
      . "Name: $name1\nEmail: $email\nPhone: $phone\nProvince: $province\nTown/village: $town\nService: $serv1\n"
      . "Received: " . date('Y-m-d H:i') . " (SAST)\n\nMessage:\n$message\n\n"
      . "Source: $srcName / $srcMed" . ($a_cmp !== '' ? " (campaign: $a_cmp)" : '') . "\nLanding page: $a_land\n"
      . "Reference: $ref\n" . ($leadId ? "Open in portal: " . cfg('domain') . "/portal/?p=lead&id=$leadId\n" : "Note: the lead register was unavailable; this email is the only record.\n")
      . "\nThe sender consented to their details being used to respond to this enquiry (POPIA).\n";

$headers = [
  'From: Think Mybiz Website <' . $FROM . '>',
  'Reply-To: ' . $name1 . ' <' . $email . '>',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Mailer: thinkmybiz.co.za',
];
$sent = send_mail_raw($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
if ($leadId) { try { db()->prepare('UPDATE leads SET email_sent=? WHERE id=?')->execute([$sent ? 1 : 0, $leadId]); } catch (Throwable $e) {} }

if (!$sent && !$leadId) out(500, ['ok' => false, 'error' => 'Your message could not be sent. Please email accounts@thinkmybiz.co.za or call 066 210 5631.']);

// Automatic acknowledgement to the enquirer (one email; no marketing). Failure here does not affect the enquiry.
$ACK_ENABLED = true;
if ($ACK_ENABLED) {
  $first = trim(explode(' ', $name1)[0]);
  $ack = "Dear " . ($first !== '' ? $first : 'Sir/Madam') . ",\n\n"
       . "Thank you for contacting Think Mybiz Accountants & Tax Practitioners. We have received your enquiry about \"$serv1\" (reference $ref). We will be in touch to arrange your free consultation.\n\n"
       . "If your matter concerns a SARS letter or assessment, please reply to this email with the date you received it and the tax type and period. "
       . "Ask us for a secure route before sending identity numbers or sensitive documents.\n\n"
       . "Telephone / WhatsApp: 066 210 5631\nEmail: accounts@thinkmybiz.co.za\nWebsite: https://thinkmybiz.co.za\n\n"
       . "Kind regards\nThink Mybiz Accountants & Tax Practitioners\nMF Banda, SARS registered tax practitioner PR-0034776\n\n"
       . "You received this one-off email because you submitted an enquiry on thinkmybiz.co.za. It is not a marketing message.\n";
  $ackHeaders = [
    'From: Think Mybiz Accountants <' . $FROM . '>',
    'Reply-To: Think Mybiz Accountants <' . $TO . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'Auto-Submitted: auto-replied',
    'X-Mailer: thinkmybiz.co.za',
  ];
  send_mail_raw($email, '=?UTF-8?B?' . base64_encode('We received your enquiry (' . $ref . ') — Think Mybiz Accountants') . '?=', $ack, $ackHeaders);
  if ($leadId) { try { db()->prepare('INSERT INTO lead_notes(lead_id,created_at,kind,body) VALUES(?,?,?,?)')->execute([$leadId, now(), 'email', 'Automatic acknowledgement sent to ' . $email]); } catch (Throwable $e) {} }
}
out(200, ['ok' => true, 'ref' => $ref]);
