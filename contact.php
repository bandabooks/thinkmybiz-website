<?php
// Think Mybiz Accountants — contact form handler (hand-edited; not generated).
// Sends website enquiries to the practice mailbox. No data is stored on the server.
date_default_timezone_set('Africa/Johannesburg'); // before any date() call
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$TO   = 'accounts@thinkmybiz.co.za';
$FROM = 'accounts@thinkmybiz.co.za'; // must be an existing mailbox: Hostinger drops PHP mail from addresses that do not exist

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
$SERVICE_LABELS = [
  'individual-tax' => 'Individual & provisional tax', 'business-tax' => 'Business & trust tax', 'vat' => 'VAT services',
  'payroll' => 'Payroll & employer taxes', 'sars-disputes' => 'SARS disputes & resolution', 'tax-debt' => 'Tax debt & compliance recovery',
  'tax-planning' => 'Tax planning & specialist support', 'accounting' => 'Accounting & financial statements',
  'business-compliance' => 'CIPC, licences & tenders', 'business-advisory' => 'Business advisory',
];
if (isset($SERVICE_LABELS[$service])) $service = $SERVICE_LABELS[$service];
$serv1 = preg_replace('/[\n]+/', ' ', $service ?: 'General enquiry');

$subject = 'Website enquiry: ' . $serv1 . ' — ' . $name1;
$body = "New enquiry from thinkmybiz.co.za\n\n"
      . "Name: $name1\nEmail: $email\nPhone: $phone\nProvince: $province\nTown/village: $town\nService: $serv1\n"
      . "Received: " . date('Y-m-d H:i') . " (SAST)\n\nMessage:\n$message\n\n"
      . "The sender consented to their details being used to respond to this enquiry (POPIA).\n";

$headers = [
  'From: Think Mybiz Website <' . $FROM . '>',
  'Reply-To: ' . $name1 . ' <' . $email . '>',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Mailer: thinkmybiz.co.za',
];
$sent = @mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $FROM);

if (!$sent) out(500, ['ok' => false, 'error' => 'Your message could not be sent. Please email accounts@thinkmybiz.co.za or call 066 210 5631.']);

// Automatic acknowledgement to the enquirer (one email; no marketing). Failure here does not affect the enquiry.
$ACK_ENABLED = true;
if ($ACK_ENABLED) {
  $first = trim(explode(' ', $name1)[0]);
  $ack = "Dear " . ($first !== '' ? $first : 'Sir/Madam') . ",\n\n"
       . "Thank you for contacting Think Mybiz Accountants & Tax Practitioners. We have received your enquiry about \"$serv1\". We will be in touch to arrange your free consultation.\n\n"
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
  @mail($email, '=?UTF-8?B?' . base64_encode('We received your enquiry — Think Mybiz Accountants') . '?=', $ack, implode("\r\n", $ackHeaders), '-f' . $FROM);
}
out(200, ['ok' => true]);
