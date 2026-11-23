<?php
// Think Mybiz practice portal: leads, follow-up, website analytics, campaigns, social posts and AI drafting.
define('TMB_PORTAL', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/ai.php';

$p = preg_replace('/[^a-z_]/', '', $_GET['p'] ?? 'dashboard');
$POST = $_SERVER['REQUEST_METHOD'] === 'POST';
$owner = strtolower(cfg('email'));
$hasUser = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

// ================================================================ signed-out pages
if (!$hasUser && !in_array($p, ['setup'], true)) $p = 'setup';
$u = user();
if (!$u && !in_array($p, ['setup', 'login', 'forgot', 'reset'], true)) $p = 'login';

if ($p === 'setup' || $p === 'reset') {
  // First-time setup and password reset both use a one-time link emailed to the practice mailbox.
  $purpose = $hasUser ? 'reset' : 'setup';
  if ($hasUser && $p === 'setup') redirect('?p=login');
  $t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
  if ($t !== '' && use_token($purpose, $t, false)) {
    if ($POST) {
      check_csrf();
      $pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
      if (strlen($pw) < 12) { flash('Use at least 12 characters.', 'err'); redirect('?p=' . $p . '&t=' . urlencode($t)); }
      if ($pw !== $pw2) { flash('The two passwords do not match.', 'err'); redirect('?p=' . $p . '&t=' . urlencode($t)); }
      use_token($purpose, $t, true);
      $hash = password_hash($pw, PASSWORD_DEFAULT);
      if ($hasUser) db()->prepare('UPDATE users SET pass_hash=? WHERE email=?')->execute([$hash, $owner]);
      else db()->prepare('INSERT INTO users(email,pass_hash,created_at) VALUES(?,?,?)')->execute([$owner, $hash, now()]);
      audit($owner, $purpose === 'setup' ? 'portal_setup' : 'password_reset');
      $s = db()->prepare('SELECT * FROM users WHERE email=?'); $s->execute([$owner]); login_user($s->fetch());
      flash($purpose === 'setup' ? 'Portal set up. Next: turn on two-step sign-in under Settings.' : 'Password changed.');
      redirect($purpose === 'setup' ? '?p=settings' : '?p=dashboard');
    }
    layout('Choose a password', '<div class="auth">' . card($purpose === 'setup' ? 'Set up your portal' : 'Choose a new password',
      '<form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="t" value="' . h($t) . '">'
      . '<p class="muted">Account: ' . h($owner) . '</p>'
      . '<label>New password (at least 12 characters)<input type="password" name="pw" minlength="12" required autocomplete="new-password"></label>'
      . '<label>Repeat password<input type="password" name="pw2" minlength="12" required autocomplete="new-password"></label>'
      . '<button class="btn">Save password</button></form>') . '</div>');
  }
  if ($POST) {
    check_csrf();
    if (rate_limited('link', 3, 900)) { flash('A link was sent recently. Check the inbox, or try again in 15 minutes.', 'err'); redirect('?p=' . $p); }
    note_attempt('link');
    $tok = make_token($purpose, 30);
    send_mail(cfg('email'), ($purpose === 'setup' ? 'Set up' : 'Reset') . ' your ' . cfg('short') . ' portal password',
      "Use this link within 30 minutes to " . ($purpose === 'setup' ? 'set up the practice portal' : 'choose a new portal password') . ":\n\n"
      . cfg('domain') . '/portal/?p=' . $p . '&t=' . $tok . "\n\nIf you did not request this, ignore this email. The link works once.\n");
    audit('portal', $purpose . '_link_sent');
    flash('A one-time link has been emailed to ' . cfg('email') . '. It expires in 30 minutes.');
    redirect('?p=' . $p);
  }
  layout($purpose === 'setup' ? 'Set up' : 'Reset password', '<div class="auth">' . card($purpose === 'setup' ? 'Set up the practice portal' : 'Reset your password',
    '<p>For security, a one-time link will be emailed to the practice mailbox <b>' . h(cfg('email')) . '</b>. Only someone with access to that mailbox can ' . ($purpose === 'setup' ? 'set up' : 'reset') . ' the portal.</p>'
    . '<form method="post">' . csrf_field() . '<button class="btn">Email me the link</button></form>'
    . ($hasUser ? '<p><a href="/portal/?p=login">Back to sign in</a></p>' : '')) . '</div>');
}
if ($p === 'forgot') redirect('?p=reset');

if ($p === 'login') {
  if ($u) redirect('?p=dashboard');
  if ($POST) {
    check_csrf();
    if (rate_limited('login', 5, 900)) { flash('Too many attempts. Wait 15 minutes, or reset your password.', 'err'); redirect('?p=login'); }
    $s = db()->prepare('SELECT * FROM users WHERE email=?'); $s->execute([strtolower(trim((string)($_POST['email'] ?? '')))]); $row = $s->fetch();
    $ok = $row && password_verify((string)($_POST['pw'] ?? ''), $row['pass_hash']);
    if ($ok && $row['totp_secret'] && !totp_ok($row['totp_secret'], $_POST['code'] ?? '')) $ok = false;
    if (!$ok) { note_attempt('login'); audit('portal', 'login_failed'); flash('Sign-in details are not correct.', 'err'); redirect('?p=login'); }
    if (password_needs_rehash($row['pass_hash'], PASSWORD_DEFAULT)) db()->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash($_POST['pw'], PASSWORD_DEFAULT), $row['id']]);
    login_user($row); redirect('?p=dashboard');
  }
  layout('Sign in', '<div class="auth">' . card('Practice portal sign-in',
    '<form method="post" class="stack">' . csrf_field()
    . '<label>Email<input type="email" name="email" required autocomplete="username" value="' . h($owner) . '"></label>'
    . '<label>Password<input type="password" name="pw" required autocomplete="current-password"></label>'
    . '<label>Authenticator code <small class="muted">(if two-step sign-in is on)</small><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"></label>'
    . '<button class="btn">Sign in</button></form><p><a href="/portal/?p=reset">Forgot password?</a></p>') . '</div>');
}

// ================================================================ signed in
if ($POST) check_csrf();
$actor = $u['email'];

if ($p === 'logout') { audit($actor, 'logout'); logout(); redirect('?p=login'); }

// ---------------------------------------------------------------- dashboard
if ($p === 'dashboard') {
  $days = in_array((int)($_GET['d'] ?? 30), [7, 30, 90, 365], true) ? (int)($_GET['d'] ?? 30) : 30;
  $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')); $today = date('Y-m-d');
  $q = function ($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s; };
  $visits = (int)$q('SELECT COUNT(*) FROM (SELECT DISTINCT day,vid FROM pageviews WHERE day>=?)', [$from])->fetchColumn();
  $views = (int)$q('SELECT COUNT(*) FROM pageviews WHERE day>=?', [$from])->fetchColumn();
  $avg = (int)$q('SELECT AVG(dur_ms) FROM pageviews WHERE day>=? AND dur_ms>0', [$from])->fetchColumn();
  $leads = (int)$q("SELECT COUNT(*) FROM leads WHERE created_at>=? AND status!='spam'", [$from])->fetchColumn();
  $won = (int)$q("SELECT COUNT(*) FROM leads WHERE created_at>=? AND status='won'", [$from])->fetchColumn();
  $ev = []; foreach ($q('SELECT name,COUNT(*) c FROM events WHERE day>=? GROUP BY name', [$from]) as $r) $ev[$r['name']] = (int)$r['c'];
  $subs = (int)$q("SELECT COUNT(*) FROM subscribers WHERE status='active'")->fetchColumn();
  $conv = $visits ? round(100 * $leads / $visits, 1) : 0;
  $kpi = function ($label, $val, $sub = '') { return '<div class="kpi"><small>' . $label . '</small><b>' . $val . '</b>' . ($sub ? '<span>' . $sub . '</span>' : '') . '</div>'; };
  $mins = $avg ? floor($avg / 60000) . 'm ' . str_pad((string)floor(($avg % 60000) / 1000), 2, '0', STR_PAD_LEFT) . 's' : '–';
  $k = '<div class="kpis">' . $kpi('Visitors', number_format($visits), 'unique per day') . $kpi('Page views', number_format($views))
     . $kpi('Avg. time on page', $mins) . $kpi('Enquiries', $leads, $conv . '% of visitors') . $kpi('Won clients', $won)
     . $kpi('Quote clicks', $ev['click_quote'] ?? 0) . $kpi('Call clicks', $ev['click_call'] ?? 0) . $kpi('WhatsApp clicks', $ev['click_whatsapp'] ?? 0)
     . $kpi('Email clicks', $ev['click_email'] ?? 0) . $kpi('Alert subscribers', $subs, 'confirmed') . '</div>';
  // daily series
  $vs = []; foreach ($q('SELECT day,COUNT(DISTINCT vid) c FROM pageviews WHERE day>=? GROUP BY day', [$from]) as $r) $vs[$r['day']] = (int)$r['c'];
  $ls = []; foreach ($q("SELECT substr(created_at,1,10) d,COUNT(*) c FROM leads WHERE created_at>=? AND status!='spam' GROUP BY d", [$from]) as $r) $ls[$r['d']] = (int)$r['c'];
  $series = []; for ($t = strtotime($from); $t <= strtotime($today); $t += 86400) { $d = date('Y-m-d', $t); $series[] = [$d, $vs[$d] ?? 0, $ls[$d] ?? 0]; }
  if ($days === 365) { $m = []; foreach ($series as $s) { $key = substr($s[0], 0, 7); $m[$key][0] = $key; $m[$key][1] = ($m[$key][1] ?? 0) + $s[1]; $m[$key][2] = ($m[$key][2] ?? 0) + $s[2]; } $series = array_values($m); }
  $max = max(1, max(array_column($series, 1))); $n = count($series); $W = 760; $H = 190; $bw = $W / $n;
  $svg = '<svg viewBox="0 0 ' . $W . ' ' . ($H + 22) . '" class="chart" role="img" aria-label="Visitors and enquiries per ' . ($days === 365 ? 'month' : 'day') . '">';
  foreach ([0.5, 1] as $g) $svg .= '<line x1="0" x2="' . $W . '" y1="' . round($H - $H * $g) . '" y2="' . round($H - $H * $g) . '" class="grid"/><text x="2" y="' . round($H - $H * $g + 12) . '" class="ax">' . round($max * $g) . '</text>';
  foreach ($series as $i => $s) {
    $bh = round($H * $s[1] / $max); $x = round($i * $bw + 1, 1);
    $svg .= '<rect x="' . $x . '" y="' . ($H - $bh) . '" width="' . max(1, round($bw - 2, 1)) . '" height="' . $bh . '" class="bar"><title>' . $s[0] . ': ' . $s[1] . ' visitors, ' . $s[2] . ' enquiries</title></rect>';
    if ($s[2]) $svg .= '<circle cx="' . round($x + $bw / 2 - 1, 1) . '" cy="' . ($H - $bh - 8) . '" r="5" class="lead"><title>' . $s[2] . ' enquiries</title></circle>';
    if ($n <= 31 ? ($i % max(1, (int)ceil($n / 8)) === 0) : true) $svg .= '<text x="' . $x . '" y="' . ($H + 16) . '" class="ax">' . ($days === 365 ? substr($s[0], 2) : date('j M', strtotime($s[0]))) . '</text>';
  }
  $svg .= '</svg><p class="legend"><i class="lb"></i>Visitors <i class="ld"></i>Enquiries</p>';
  $rows = function ($sql, $args, $fmt) use ($q) { $o = []; foreach ($q($sql, $args) as $r) $o[] = $fmt($r); return $o; };
  $pages = $rows('SELECT path,COUNT(*) c,COUNT(DISTINCT day||vid) v,AVG(NULLIF(dur_ms,0)) a FROM pageviews WHERE day>=? GROUP BY path ORDER BY c DESC LIMIT 15', [$from],
    function ($r) { return ['<a href="' . h($r['path']) . '" target="_blank" rel="noopener">' . h($r['path']) . '</a>', $r['c'], $r['v'], $r['a'] ? round($r['a'] / 1000) . 's' : '–']; });
  $sources = $rows("SELECT p.source s,p.medium m,COUNT(*) c,(SELECT COUNT(*) FROM leads l WHERE l.source=p.source AND l.created_at>=? AND l.status!='spam') ld FROM pageviews p WHERE p.day>=? AND p.is_entry=1 GROUP BY p.source,p.medium ORDER BY c DESC LIMIT 12", [$from, $from],
    function ($r) { return [h($r['s']), h($r['m']), $r['c'], $r['ld']]; });
  $entries = $rows('SELECT path,COUNT(*) c FROM pageviews WHERE day>=? AND is_entry=1 GROUP BY path ORDER BY c DESC LIMIT 10', [$from], function ($r) { return [h($r['path']), $r['c']]; });
  $devices = $rows('SELECT device,COUNT(*) c FROM pageviews WHERE day>=? AND is_entry=1 GROUP BY device ORDER BY c DESC', [$from], function ($r) { return [h($r['device']), $r['c']]; });
  $events = $rows('SELECT name,COUNT(*) c FROM events WHERE day>=? GROUP BY name ORDER BY c DESC', [$from], function ($r) { return [h($r['name']), $r['c']]; });
  $bysvc = $rows("SELECT service,COUNT(*) c,SUM(status='won') w FROM leads WHERE created_at>=? AND status!='spam' GROUP BY service ORDER BY c DESC", [$from], function ($r) { return [h($r['service']), $r['c'], (int)$r['w']]; });
  $quotes = $rows("SELECT label,COUNT(*) c FROM events WHERE day>=? AND name='click_quote' GROUP BY label ORDER BY c DESC LIMIT 10", [$from], function ($r) { return [h(preg_replace('#.*service=#', '', $r['label'])), $r['c']]; });
  $dueRows = $rows("SELECT * FROM leads WHERE status IN ('new','contacted','consultation','quoted') AND next_follow_up<=? ORDER BY next_follow_up LIMIT 10", [$today],
    function ($r) { return ['<a href="/portal/?p=lead&id=' . $r['id'] . '">' . h($r['name']) . '</a>', h($r['service']), status_pill($r['status']), h($r['next_follow_up'])]; });
  $range = ''; foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $dv => $lbl) $range .= '<a href="/portal/?p=dashboard&d=' . $dv . '"' . ($dv === $days ? ' class="on"' : '') . '>' . $lbl . '</a>';
  layout('Dashboard', '<div class="head"><h1>Dashboard</h1><div class="seg">' . $range . '</div></div>' . $k
    . card('Follow-ups due today', table(['Lead', 'Service', 'Status', 'Due'], $dueRows, 'No follow-ups due. 👍'))
    . card('Visitors and enquiries', $svg)
    . '<div class="cols">' . card('Where visitors come from', table(['Source', 'Medium', 'Visits', 'Enquiries'], $sources))
    . card('Enquiries by service', table(['Service', 'Enquiries', 'Won'], $bysvc)) . '</div>'
    . card('Most visited pages', table(['Page', 'Views', 'Visitors', 'Avg. time'], $pages))
    . '<div class="cols">' . card('Landing pages', table(['First page of visit', 'Visits'], $entries)) . card('Quote requests by service', table(['Service', 'Clicks'], $quotes)) . '</div>'
    . '<div class="cols">' . card('Actions', table(['Event', 'Count'], $events)) . card('Devices', table(['Device', 'Visits'], $devices)) . '</div>'
    . '<p class="muted small">Visitor statistics are cookieless and anonymous; visits from browsers with Do Not Track or Global Privacy Control are not counted, so true traffic is somewhat higher.</p>', 'dashboard');
}

// ---------------------------------------------------------------- leads
if ($p === 'leads') {
  $st = $_GET['s'] ?? 'open'; $term = trim((string)($_GET['q'] ?? ''));
  $where = []; $args = [];
  if ($st === 'open') $where[] = "status IN ('new','contacted','consultation','quoted')";
  elseif ($st === 'due') { $where[] = "status IN ('new','contacted','consultation','quoted') AND next_follow_up<=?"; $args[] = date('Y-m-d'); }
  elseif (isset(STATUSES[$st])) { $where[] = 'status=?'; $args[] = $st; }
  if ($term !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR ref LIKE ? OR message LIKE ?)'; for ($i = 0; $i < 5; $i++) $args[] = '%' . $term . '%'; }
  $sql = 'SELECT * FROM leads' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 500';
  $s = db()->prepare($sql); $s->execute($args); $rows = [];
  foreach ($s as $r) $rows[] = ['<a href="/portal/?p=lead&id=' . $r['id'] . '"><b>' . h($r['name']) . '</b></a><br><small class="muted">' . h($r['ref']) . '</small>',
    h(substr($r['created_at'], 0, 16)), h($r['service']), h($r['source'] . ($r['campaign'] ? ' · ' . $r['campaign'] : '')), status_pill($r['status']),
    h($r['next_follow_up']) . ($r['next_follow_up'] && $r['next_follow_up'] <= date('Y-m-d') && in_array($r['status'], ['new', 'contacted', 'consultation', 'quoted'], true) ? ' <b class="badge">due</b>' : '')];
  $tabs = ''; foreach (['open' => 'Open', 'due' => 'Due', 'new' => 'New', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost', 'all' => 'All'] as $kk => $lbl)
    $tabs .= '<a href="/portal/?p=leads&s=' . $kk . '"' . ($kk === $st ? ' class="on"' : '') . '>' . $lbl . '</a>';
  layout('Leads', '<div class="head"><h1>Leads</h1><div class="seg">' . $tabs . '</div></div>'
    . '<form class="search" method="get"><input type="hidden" name="p" value="leads"><input type="hidden" name="s" value="' . h($st) . '"><input name="q" value="' . h($term) . '" placeholder="Search name, email, phone, reference or message"><button class="btn sm">Search</button>'
    . '<a class="btn sm ghost" href="/portal/?p=export&what=leads">Export CSV</a><a class="btn sm ghost" href="/portal/?p=newlead">+ Add lead</a></form>'
    . card(count($rows) . ' lead' . (count($rows) === 1 ? '' : 's'), table(['Lead', 'Received', 'Service', 'Source', 'Status', 'Follow up'], $rows, 'No leads in this view.')), 'leads');
}

if ($p === 'newlead') {
  // Record an enquiry that came in by phone, WhatsApp, walk-in or referral, so every lead is in one register.
  if ($POST) {
    $name = trim((string)($_POST['name'] ?? '')); if ($name === '') { flash('Name is required.', 'err'); redirect('?p=newlead'); }
    $ref = 'TMB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    db()->prepare('INSERT INTO leads(ref,created_at,updated_at,name,email,phone,province,town,service,message,status,next_follow_up,source,medium,campaign,device,consent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$ref, now(), now(), $name, trim((string)$_POST['email']), trim((string)$_POST['phone']), '', trim((string)$_POST['town']),
                 svc_label((string)$_POST['service']), trim((string)$_POST['message']), 'new', date('Y-m-d'), (string)$_POST['source'], 'offline', '', '', null]);
    $id = (int)db()->lastInsertId(); audit($actor, 'lead_added', $ref); redirect('?p=lead&id=' . $id);
  }
  $opts = ''; foreach (cfg('services') as $sl => $lb) $opts .= '<option value="' . $sl . '">' . h($lb) . '</option>';
  layout('Add lead', '<h1>Add a lead</h1>' . card('Phone, WhatsApp, referral or walk-in enquiry', '<form method="post" class="grid2">' . csrf_field()
    . '<label>Name *<input name="name" required></label><label>Phone<input name="phone"></label><label>Email<input type="email" name="email"></label><label>Town<input name="town"></label>'
    . '<label>Service<select name="service">' . $opts . '</select></label><label>Came from<select name="source"><option value="phone">Phone call</option><option value="whatsapp">WhatsApp</option><option value="referral">Referral</option><option value="walk-in">In person</option><option value="facebook">Facebook message</option><option value="linkedin">LinkedIn message</option><option value="other">Other</option></select></label>'
    . '<label class="full">Notes<textarea name="message" rows="4"></textarea></label><p class="full muted small">Record only what the person agreed to share, for the purpose of responding to their enquiry (POPIA).</p><div class="full"><button class="btn">Save lead</button></div></form>'), 'leads');
}

if ($p === 'lead') {
  $id = (int)($_GET['id'] ?? 0);
  $s = db()->prepare('SELECT * FROM leads WHERE id=?'); $s->execute([$id]); $L = $s->fetch();
  if (!$L) { flash('Lead not found.', 'err'); redirect('?p=leads'); }
  if ($POST) {
    $act = $_POST['act'] ?? '';
    if ($act === 'update') {
      $ns = isset(STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : $L['status'];
      $fu = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['next'] ?? '')) ? $_POST['next'] : null;
      if (in_array($ns, ['won', 'lost', 'spam'], true)) $fu = null;
      db()->prepare('UPDATE leads SET status=?, next_follow_up=?, updated_at=? WHERE id=?')->execute([$ns, $fu, now(), $id]);
      if ($ns !== $L['status']) db()->prepare('INSERT INTO lead_notes(lead_id,created_at,kind,body) VALUES(?,?,?,?)')->execute([$id, now(), 'status', STATUSES[$L['status']] . ' → ' . STATUSES[$ns]]);
      $note = trim((string)($_POST['note'] ?? ''));
      if ($note !== '') db()->prepare('INSERT INTO lead_notes(lead_id,created_at,kind,body) VALUES(?,?,?,?)')->execute([$id, now(), 'note', $note]);
      audit($actor, 'lead_update', $L['ref'] . ' ' . $ns); flash('Saved.');
    } elseif ($act === 'email') {
      $subj = trim(str_replace(["\r", "\n"], ' ', (string)($_POST['subject'] ?? ''))); $body = trim((string)($_POST['body'] ?? ''));
      if (!filter_var($L['email'], FILTER_VALIDATE_EMAIL) || $subj === '' || $body === '') { flash('Subject, message and a valid email address are needed.', 'err'); redirect('?p=lead&id=' . $id); }
      $full = $body . "\n\n" . cfg('signature') . "\n\nReference: " . $L['ref'];
      if (send_mail($L['email'], $subj, $full)) {
        db()->prepare('INSERT INTO lead_notes(lead_id,created_at,kind,body) VALUES(?,?,?,?)')->execute([$id, now(), 'email', "Sent: $subj\n\n$body"]);
        $next = $L['status'] === 'new' ? 'contacted' : $L['status'];
        db()->prepare('UPDATE leads SET status=?, next_follow_up=?, updated_at=? WHERE id=?')->execute([$next, date('Y-m-d', strtotime('+3 days')), now(), $id]);
        audit($actor, 'lead_email', $L['ref'] . ' ' . $subj); flash('Email sent to ' . $L['email'] . '. Next follow-up set for 3 days from today.');
      } else flash('The email could not be sent. Try again or reply from your mailbox.', 'err');
    } elseif ($act === 'delete' && ($_POST['confirm'] ?? '') === $L['ref']) {
      db()->prepare('DELETE FROM lead_notes WHERE lead_id=?')->execute([$id]); db()->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
      audit($actor, 'lead_deleted', $L['ref']); flash('Lead ' . $L['ref'] . ' deleted.'); redirect('?p=leads');
    }
    redirect('?p=lead&id=' . $id);
  }
  $notes = db()->prepare('SELECT * FROM lead_notes WHERE lead_id=? ORDER BY id DESC'); $notes->execute([$id]);
  $tl = ''; foreach ($notes as $n) $tl .= '<li class="k-' . h($n['kind']) . '"><small>' . h(substr($n['created_at'], 0, 16)) . ' · ' . h($n['kind']) . '</small><div>' . nl2br(h($n['body'])) . '</div></li>';
  $first = trim(explode(' ', $L['name'])[0]);
  $tpl = [
    'first' => ['Your enquiry about ' . $L['service'], "Dear $first,\n\nThank you for contacting Think Mybiz about " . $L['service'] . ". I have read your message and would like to arrange a short free consultation to understand your position and agree the next steps.\n\nWould [DAY/TIME] or [DAY/TIME] suit you for a 15-minute call or WhatsApp video call?"],
    'docs' => ['Preparing for your consultation', "Dear $first,\n\nTo make our consultation as useful as possible, it would help to have the following ready:\n\n- [ITEM]\n- [ITEM]\n\nPlease do not email identity numbers or sensitive documents; I will send you a secure upload route."],
    'quote' => ['Your quotation from Think Mybiz', "Dear $first,\n\nI am following up on the quotation I sent for " . $L['service'] . ". Do you have any questions, or would it help if I walked you through it on a short call?"],
    'checkin' => ['Following up on your enquiry', "Dear $first,\n\nI am following up on your enquiry about " . $L['service'] . ". If it is still useful, I am happy to arrange a short call at a time that suits you. If you have already sorted it out, no reply is needed."],
  ];
  $tplJson = h(json_encode($tpl));
  $statusOpts = ''; foreach (STATUSES as $kk => $lbl) $statusOpts .= '<option value="' . $kk . '"' . ($kk === $L['status'] ? ' selected' : '') . '>' . $lbl . '</option>';
  $wa = wa_number($L['phone']);
  $waText = rawurlencode("Good day $first, this is MF Banda from Think Mybiz Accountants regarding your enquiry about " . $L['service'] . " (ref " . $L['ref'] . ").");
  $info = '<dl class="dl"><dt>Reference</dt><dd>' . h($L['ref']) . '</dd><dt>Received</dt><dd>' . h($L['created_at']) . '</dd>'
    . '<dt>Email</dt><dd>' . ($L['email'] ? '<a href="mailto:' . h($L['email']) . '">' . h($L['email']) . '</a>' : '–') . '</dd>'
    . '<dt>Phone</dt><dd>' . ($L['phone'] ? '<a href="tel:' . h($L['phone']) . '">' . h($L['phone']) . '</a>' : '–') . '</dd>'
    . '<dt>Location</dt><dd>' . h(trim($L['town'] . ', ' . $L['province'], ', ')) . '</dd><dt>Service</dt><dd>' . h($L['service']) . '</dd>'
    . '<dt>Source</dt><dd>' . h($L['source'] . ' / ' . $L['medium']) . ($L['campaign'] ? ' · campaign <b>' . h($L['campaign']) . '</b>' : '') . '</dd>'
    . '<dt>Landing page</dt><dd>' . h($L['landing'] ?: '–') . '</dd><dt>Device</dt><dd>' . h($L['device'] ?: '–') . '</dd>'
    . '<dt>Email delivered</dt><dd>' . ($L['email_sent'] ? 'Yes' : '<b class="badge">Check</b> not confirmed') . '</dd></dl>'
    . '<h3>Message</h3><blockquote>' . nl2br(h($L['message'])) . '</blockquote>'
    . '<p class="actions">' . ($L['phone'] ? '<a class="btn sm ghost" href="tel:' . h($L['phone']) . '">Call</a>' : '')
    . ($wa ? '<a class="btn sm ghost" href="https://wa.me/' . $wa . '?text=' . $waText . '" target="_blank" rel="noopener">WhatsApp</a>' : '') . '</p>';
  $upd = '<form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="act" value="update">'
    . '<label>Status<select name="status">' . $statusOpts . '</select></label>'
    . '<label>Next follow-up<input type="date" name="next" value="' . h($L['next_follow_up']) . '"></label>'
    . '<label>Add a note<textarea name="note" rows="3" placeholder="Call outcome, quote amount, next step…"></textarea></label><button class="btn">Save</button></form>';
  $mail = $L['email'] ? '<form method="post" class="stack" id="mailform">' . csrf_field() . '<input type="hidden" name="act" value="email">'
    . '<div class="chips" data-tpl="' . $tplJson . '"><span class="muted small">Templates:</span><button type="button" data-t="first">First reply</button><button type="button" data-t="docs">What to prepare</button><button type="button" data-t="quote">Quote follow-up</button><button type="button" data-t="checkin">Check-in</button></div>'
    . (ai_configured() ? '<div class="chips"><span class="muted small">AI draft:</span><button type="button" data-ai="first" data-id="' . $id . '">First reply</button><button type="button" data-ai="docs" data-id="' . $id . '">Prepare</button><button type="button" data-ai="quote" data-id="' . $id . '">Quote follow-up</button><button type="button" data-ai="checkin" data-id="' . $id . '">Check-in</button><span class="ai-status small muted"></span></div>' : '<p class="muted small">Connect Claude or OpenAI under Settings to get AI drafts.</p>')
    . '<label>Subject<input name="subject" required></label><label>Message<textarea name="body" rows="10" required></textarea></label>'
    . '<p class="muted small">Sent from ' . h(cfg('email')) . ' with your signature and the reference added. Replace any [PLACEHOLDERS] before sending.</p>'
    . '<button class="btn" data-confirm="Send this email to ' . h($L['email']) . '?">Send email</button></form>' : '<p class="muted">No email address on this lead.</p>';
  $del = '<details class="danger"><summary>Delete this lead</summary><form method="post">' . csrf_field() . '<input type="hidden" name="act" value="delete"><p class="small">Use for spam or on a person\'s request to delete their information (POPIA). Type the reference <b>' . h($L['ref']) . '</b> to confirm.</p><input name="confirm" required> <button class="btn sm red">Delete permanently</button></form></details>';
  layout($L['name'], '<p><a href="/portal/?p=leads">← Leads</a></p><div class="head"><h1>' . h($L['name']) . '</h1>' . status_pill($L['status']) . '</div>'
    . '<div class="cols">' . card('Enquiry', $info) . card('Status and follow-up', $upd) . '</div>'
    . card('Reply by email', $mail) . card('History', $tl ? '<ul class="tl">' . $tl . '</ul>' : '<p class="muted">No history yet.</p>') . $del, 'leads');
}

// ---------------------------------------------------------------- AI endpoint (JSON)
if ($p === 'ai') {
  if (!$POST) json_out(405, ['ok' => false]);
  $kind = $_POST['kind'] ?? '';
  if ($kind === 'reply') {
    $s = db()->prepare('SELECT * FROM leads WHERE id=?'); $s->execute([(int)$_POST['id']]); $L = $s->fetch();
    if (!$L) json_out(404, ['ok' => false, 'error' => 'Lead not found']);
    list($ok, $text) = ai_lead_reply($L, (string)($_POST['intent'] ?? 'first'));
    if ($ok) db()->prepare('INSERT INTO lead_notes(lead_id,created_at,kind,body) VALUES(?,?,?,?)')->execute([$L['id'], now(), 'ai', 'AI draft created (' . setting('ai_provider') . ')']);
  } elseif ($kind === 'social') list($ok, $text) = ai_social((string)$_POST['channel'], mb_substr((string)$_POST['topic'], 0, 600), (string)($_POST['link'] ?? ''), mb_substr((string)($_POST['tone'] ?? ''), 0, 80));
  elseif ($kind === 'strategy') list($ok, $text) = ai_strategy(mb_substr((string)$_POST['goal'], 0, 600));
  else json_out(400, ['ok' => false, 'error' => 'Unknown request']);
  json_out(200, $ok ? ['ok' => true, 'text' => $text] : ['ok' => false, 'error' => $text]);
}

// ---------------------------------------------------------------- campaigns (tracked links)
$CHANNELS = ['facebook' => ['Facebook', 'social'], 'instagram' => ['Instagram', 'social'], 'linkedin' => ['LinkedIn', 'social'], 'whatsapp' => ['WhatsApp', 'social'],
             'google_business' => ['Google Business Profile', 'local'], 'google_ads' => ['Google Ads', 'cpc'], 'meta_ads' => ['Meta Ads (Facebook/Instagram)', 'paid_social'],
             'tiktok' => ['TikTok', 'social'], 'x' => ['X', 'social'], 'youtube' => ['YouTube', 'video'], 'email' => ['Email', 'email'], 'print' => ['Print / QR code', 'offline'], 'partner' => ['Partner / referral', 'referral']];
if ($p === 'campaigns') {
  if ($POST && ($_POST['act'] ?? '') === 'add') {
    $ch = isset($CHANNELS[$_POST['channel'] ?? '']) ? $_POST['channel'] : 'facebook';
    $name = trim((string)($_POST['name'] ?? '')); $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    $land = '/' . ltrim(preg_replace('/[^A-Za-z0-9\/\-_?=&]/', '', (string)($_POST['landing'] ?? '/')), '/');
    if ($slug === '') { flash('Give the campaign a name.', 'err'); redirect('?p=campaigns'); }
    $src = $ch === 'google_ads' ? 'google' : ($ch === 'meta_ads' ? 'facebook' : $ch);
    $url = cfg('domain') . $land . (strpos($land, '?') === false ? '?' : '&') . http_build_query(['utm_source' => $src, 'utm_medium' => $CHANNELS[$ch][1], 'utm_campaign' => $slug]);
    db()->prepare('INSERT INTO campaigns(created_at,name,channel,slug,landing,url) VALUES(?,?,?,?,?,?)')->execute([now(), $name, $ch, $slug, $land, $url]);
    audit($actor, 'campaign_added', $slug); flash('Tracked link created. Use it in your post or ad.'); redirect('?p=campaigns');
  }
  if ($POST && ($_POST['act'] ?? '') === 'del') { db()->prepare('DELETE FROM campaigns WHERE id=?')->execute([(int)$_POST['id']]); redirect('?p=campaigns'); }
  $rows = [];
  foreach (db()->query('SELECT * FROM campaigns ORDER BY id DESC') as $c) {
    $v = db()->prepare('SELECT COUNT(*) FROM pageviews WHERE is_entry=1 AND campaign=?'); $v->execute([$c['slug']]);
    $l = db()->prepare("SELECT COUNT(*), SUM(status='won') FROM leads WHERE campaign=? AND status!='spam'"); $l->execute([$c['slug']]); $lr = $l->fetch(PDO::FETCH_NUM);
    $rows[] = ['<b>' . h($c['name']) . '</b><br><small class="muted">' . h($CHANNELS[$c['channel']][0] ?? $c['channel']) . ' · ' . h(substr($c['created_at'], 0, 10)) . '</small>',
      '<input class="copy" readonly value="' . h($c['url']) . '"><button type="button" class="btn xs ghost" data-copy="' . h($c['url']) . '">Copy</button> <button type="button" class="btn xs ghost" data-qr="' . h($c['url']) . '">QR</button>',
      (int)$v->fetchColumn(), (int)$lr[0], (int)$lr[1],
      '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="act" value="del"><input type="hidden" name="id" value="' . $c['id'] . '"><button class="link small" data-confirm="Remove this campaign link? Visits already recorded stay.">Remove</button></form>'];
  }
  $chOpts = ''; foreach ($CHANNELS as $kk => $v) $chOpts .= '<option value="' . $kk . '">' . $v[0] . '</option>';
  $pages = ['/' => 'Home', '/services/' => 'Services', '/contact/' => 'Contact form', '/sars-letter/' => 'Received a SARS letter'];
  foreach (cfg('services') as $sl => $lb) $pages['/services/' . $sl . '/'] = 'Service: ' . $lb;
  $pgOpts = ''; foreach ($pages as $kk => $v) $pgOpts .= '<option value="' . h($kk) . '">' . h($v) . '</option>';
  layout('Campaigns', '<h1>Campaigns and tracked links</h1><p class="muted">Every post, ad, flyer or WhatsApp broadcast should use its own tracked link, so the dashboard shows which ones bring visitors and enquiries.</p>'
    . card('Create a tracked link', '<form method="post" class="grid2">' . csrf_field() . '<input type="hidden" name="act" value="add">'
      . '<label>Campaign name<input name="name" required placeholder="e.g. Filing season Nov 2026"></label><label>Channel<select name="channel">' . $chOpts . '</select></label>'
      . '<label class="full">Landing page<select name="landing">' . $pgOpts . '</select></label><div class="full"><button class="btn">Create link</button></div></form>')
    . card('Your campaigns', table(['Campaign', 'Tracked link', 'Visits', 'Enquiries', 'Won', ''], $rows, 'No campaigns yet.'))
    . '<div id="qrbox" class="qrbox" hidden><div class="pcard"><div id="qr"></div><p class="small muted">Right-click or long-press to save the QR code for flyers.</p><button class="btn sm" data-close>Close</button></div></div>'
    . '<script src="/portal/assets/qrcode.min.js"></script>', 'campaigns');
}

// ---------------------------------------------------------------- social posts
if ($p === 'social') {
  if ($POST) {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
      $body = trim((string)$_POST['body']); if ($body === '') { flash('Write or generate the post first.', 'err'); redirect('?p=social'); }
      db()->prepare('INSERT INTO posts(created_at,channel,body,link,status) VALUES(?,?,?,?,?)')->execute([now(), (string)$_POST['channel'], $body, trim((string)$_POST['link']), 'draft']);
      flash('Draft saved.');
    } elseif ($act === 'fb') {
      $s = db()->prepare('SELECT * FROM posts WHERE id=?'); $s->execute([(int)$_POST['id']]); $P = $s->fetch();
      if ($P) { list($ok, $res) = fb_publish($P['body'], $P['link']);
        if ($ok) { db()->prepare("UPDATE posts SET status='published', published_at=?, external_id=? WHERE id=?")->execute([now(), $res, $P['id']]); audit($actor, 'fb_publish', $res); flash('Published to your Facebook Page.'); }
        else flash($res, 'err'); }
    } elseif ($act === 'mark') { db()->prepare("UPDATE posts SET status='published', published_at=? WHERE id=?")->execute([now(), (int)$_POST['id']]); flash('Marked as posted.'); }
    elseif ($act === 'del') { db()->prepare('DELETE FROM posts WHERE id=?')->execute([(int)$_POST['id']]); }
    redirect('?p=social');
  }
  $camp = ''; foreach (db()->query('SELECT * FROM campaigns ORDER BY id DESC LIMIT 50') as $c) $camp .= '<option value="' . h($c['url']) . '">' . h($c['name'] . ' (' . $c['channel'] . ')') . '</option>';
  $chs = ['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'google' => 'Google Business Profile', 'whatsapp' => 'WhatsApp', 'x' => 'X', 'tiktok' => 'TikTok'];
  $chOpts = ''; foreach ($chs as $kk => $v) $chOpts .= '<option value="' . $kk . '">' . $v . '</option>';
  $fbOn = setting('fb_page_id') !== '' && setting('fb_token') !== '';
  $rows = [];
  foreach (db()->query('SELECT * FROM posts ORDER BY id DESC LIMIT 100') as $P) {
    $share = $P['channel'] === 'linkedin' && $P['link'] ? '<a class="btn xs ghost" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($P['link']) . '">Open LinkedIn</a>' : '';
    $fb = $P['channel'] === 'facebook' && $fbOn && $P['status'] === 'draft' ? '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="act" value="fb"><input type="hidden" name="id" value="' . $P['id'] . '"><button class="btn xs" data-confirm="Publish this post to your Facebook Page now?">Publish to Facebook</button></form>' : '';
    $rows[] = [h($chs[$P['channel']] ?? $P['channel']) . '<br><small class="muted">' . h(substr($P['created_at'], 0, 10)) . '</small>',
      '<div class="post">' . nl2br(h($P['body'])) . '</div>',
      $P['status'] === 'published' ? '<span class="pill s-won">Posted ' . h(substr((string)$P['published_at'], 0, 10)) . '</span>' : '<span class="pill s-new">Draft</span>',
      '<button type="button" class="btn xs ghost" data-copy="' . h($P['body']) . '">Copy</button> ' . $share . ' ' . $fb
      . ($P['status'] === 'draft' ? '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="act" value="mark"><input type="hidden" name="id" value="' . $P['id'] . '"><button class="btn xs ghost">Mark posted</button></form>' : '')
      . '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="act" value="del"><input type="hidden" name="id" value="' . $P['id'] . '"><button class="link small" data-confirm="Delete this draft?">Delete</button></form>'];
  }
  layout('Social posts', '<h1>Social posts</h1>'
    . card('Write a post', '<form method="post" class="grid2" id="postform">' . csrf_field() . '<input type="hidden" name="act" value="save">'
      . '<label>Channel<select name="channel">' . $chOpts . '</select></label><label>Tracked link<select name="link"><option value="">(none)</option>' . $camp . '</select></label>'
      . '<label class="full">Topic or idea<input name="topic" placeholder="e.g. Provisional tax second payment due end of February; what happens if you underestimate"></label>'
      . '<label class="full">Tone<input name="tone" placeholder="helpful and trustworthy"></label>'
      . '<div class="full chips">' . (ai_configured() ? '<button type="button" class="btn sm" data-aipost>✨ Draft with AI</button><span class="ai-status small muted"></span>' : '<span class="muted small">Connect Claude or OpenAI under Settings to draft posts with AI.</span>') . '</div>'
      . '<label class="full">Post text<textarea name="body" rows="8"></textarea></label><p class="full muted small">Check every fact against the SARS or CIPC source before posting. Create the tracked link first under Campaigns.</p>'
      . '<div class="full"><button class="btn">Save draft</button></div></form>')
    . card('Drafts and posted', table(['Channel', 'Post', 'Status', ''], $rows, 'No posts yet.'))
    . card('Marketing plan assistant', ai_configured() ? '<form class="stack" id="stratform"><label>Goal<input name="goal" value="Get 10 qualified enquiries a month from small businesses in Gauteng and Limpopo for bookkeeping, VAT and SARS disputes"></label><button type="button" class="btn sm" data-aistrat>Draft a 4-week plan</button><span class="ai-status small muted"></span><textarea name="out" rows="14" readonly placeholder="The plan appears here."></textarea></form>' : '<p class="muted">Connect Claude or OpenAI under Settings.</p>'), 'social');
}

// ---------------------------------------------------------------- subscribers
if ($p === 'subscribers') {
  $rows = []; foreach (db()->query('SELECT * FROM subscribers ORDER BY id DESC LIMIT 1000') as $r)
    $rows[] = [h($r['email']), h($r['name']), '<span class="pill s-' . ($r['status'] === 'active' ? 'won' : ($r['status'] === 'pending' ? 'new' : 'lost')) . '">' . h($r['status']) . '</span>', h(substr((string)($r['confirmed_at'] ?: $r['created_at']), 0, 10)), h($r['source'])];
  layout('Subscribers', '<div class="head"><h1>SARS deadline alert subscribers</h1><a class="btn sm ghost" href="/portal/?p=export&what=subscribers">Export CSV</a></div>'
    . '<p class="muted">People are added only after confirming by email (double opt-in). Send alerts from your mailbox using BCC or a mailing service, always with the unsubscribe link: ' . h(cfg('domain')) . '/api/unsubscribe.php?t=… (each subscriber\'s link is in the export).</p>'
    . card('Subscribers', table(['Email', 'Name', 'Status', 'Since', 'Signed up on'], $rows, 'No subscribers yet.')), 'subscribers');
}

// ---------------------------------------------------------------- exports and backup
if ($p === 'export') {
  $what = $_GET['what'] ?? 'leads';
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $what . '-' . date('Ymd') . '.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  if ($what === 'subscribers') { fputcsv($o, ['email', 'name', 'status', 'created', 'confirmed', 'unsubscribe_link']);
    foreach (db()->query('SELECT * FROM subscribers') as $r) fputcsv($o, [$r['email'], $r['name'], $r['status'], $r['created_at'], $r['confirmed_at'], cfg('domain') . '/api/unsubscribe.php?t=' . $r['token']]); }
  else { fputcsv($o, ['ref', 'received', 'name', 'email', 'phone', 'province', 'town', 'service', 'status', 'next_follow_up', 'source', 'medium', 'campaign', 'landing', 'message']);
    foreach (db()->query('SELECT * FROM leads ORDER BY id') as $r) fputcsv($o, [$r['ref'], $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['province'], $r['town'], $r['service'], $r['status'], $r['next_follow_up'], $r['source'], $r['medium'], $r['campaign'], $r['landing'], $r['message']]); }
  audit($actor, 'export', $what); exit;
}
if ($p === 'backup') {
  $tmp = tempnam(sys_get_temp_dir(), 'tmb');
  try { db()->exec('VACUUM INTO ' . db()->quote($tmp . '.sqlite')); } catch (Throwable $e) { @unlink($tmp); flash('Backup failed: ' . $e->getMessage(), 'err'); redirect('?p=settings'); }
  audit($actor, 'backup_download');
  header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="thinkmybiz-portal-' . date('Ymd-His') . '.sqlite"');
  readfile($tmp . '.sqlite'); @unlink($tmp . '.sqlite'); @unlink($tmp); exit;
}

// ---------------------------------------------------------------- settings
if ($p === 'settings') {
  if ($POST) {
    $act = $_POST['act'] ?? '';
    if ($act === 'pw') {
      if (!password_verify((string)$_POST['cur'], $u['pass_hash'])) flash('Current password is not correct.', 'err');
      elseif (strlen((string)$_POST['pw']) < 12) flash('Use at least 12 characters.', 'err');
      else { db()->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash($_POST['pw'], PASSWORD_DEFAULT), $u['id']]); audit($actor, 'password_changed'); flash('Password changed.'); }
    } elseif ($act === 'totp_on') {
      $sec = $_SESSION['totp_pending'] ?? '';
      if ($sec && totp_ok($sec, $_POST['code'] ?? '')) { db()->prepare('UPDATE users SET totp_secret=? WHERE id=?')->execute([$sec, $u['id']]); unset($_SESSION['totp_pending']); audit($actor, 'totp_enabled'); flash('Two-step sign-in is on.'); }
      else flash('That code did not match. Check the time on your phone and try again.', 'err');
    } elseif ($act === 'totp_off') {
      if (totp_ok($u['totp_secret'], $_POST['code'] ?? '')) { db()->prepare('UPDATE users SET totp_secret=NULL WHERE id=?')->execute([$u['id']]); audit($actor, 'totp_disabled'); flash('Two-step sign-in is off.'); }
      else flash('Code not correct.', 'err');
    } elseif ($act === 'ai') {
      set_setting('ai_provider', in_array($_POST['provider'] ?? '', ['anthropic', 'openai'], true) ? $_POST['provider'] : '');
      set_setting('ai_model', trim((string)$_POST['model']));
      if (trim((string)$_POST['key']) !== '') set_setting('ai_key', trim((string)$_POST['key']));
      if (!empty($_POST['clear'])) set_setting('ai_key', '');
      audit($actor, 'ai_settings', setting('ai_provider')); flash('AI settings saved.');
    } elseif ($act === 'aitest') {
      list($ok, $t) = ai_complete('Reply with exactly: Connection works.', 20); flash($ok ? 'AI test: ' . $t : $t, $ok ? 'ok' : 'err');
    } elseif ($act === 'fb') {
      set_setting('fb_page_id', preg_replace('/\D/', '', (string)$_POST['page']));
      set_setting('fb_version', preg_match('/^v\d+\.\d+$/', (string)$_POST['ver']) ? $_POST['ver'] : 'v23.0');
      if (trim((string)$_POST['token']) !== '') set_setting('fb_token', trim((string)$_POST['token']));
      if (!empty($_POST['clear'])) set_setting('fb_token', '');
      audit($actor, 'fb_settings'); flash('Facebook settings saved.');
    } elseif ($act === 'digest') {
      set_setting('digest_on', !empty($_POST['on']) ? '1' : '0'); flash('Daily summary setting saved.');
    }
    redirect('?p=settings');
  }
  if (!$u['totp_secret'] && empty($_SESSION['totp_pending'])) $_SESSION['totp_pending'] = b32_encode(random_bytes(20));
  $otpUri = 'otpauth://totp/' . rawurlencode(cfg('short') . ':' . $u['email']) . '?secret=' . ($_SESSION['totp_pending'] ?? '') . '&issuer=' . rawurlencode(cfg('short'));
  $totp = $u['totp_secret']
    ? '<p>✅ Two-step sign-in is <b>on</b>.</p><form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="act" value="totp_off"><input name="code" placeholder="Current 6-digit code" inputmode="numeric" maxlength="6" required> <button class="btn sm ghost">Turn off</button></form>'
    : '<p>Scan this with Google Authenticator or Microsoft Authenticator, then enter the 6-digit code.</p><div class="qrsmall" data-qrfill="' . h($otpUri) . '"></div><p class="small muted">Or enter this key manually: <code>' . h(chunk_split($_SESSION['totp_pending'], 4, ' ')) . '</code></p>'
      . '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="act" value="totp_on"><input name="code" placeholder="6-digit code" inputmode="numeric" maxlength="6" required> <button class="btn sm">Turn on</button></form>';
  $prov = setting('ai_provider'); $key = setting('ai_key');
  $ai = '<form method="post" class="grid2">' . csrf_field() . '<input type="hidden" name="act" value="ai">'
    . '<label>Provider<select name="provider"><option value="">Off</option><option value="anthropic"' . ($prov === 'anthropic' ? ' selected' : '') . '>Anthropic (Claude)</option><option value="openai"' . ($prov === 'openai' ? ' selected' : '') . '>OpenAI</option></select></label>'
    . '<label>Model <small class="muted">(blank = claude-sonnet-5-5 for Anthropic)</small><input name="model" value="' . h(setting('ai_model')) . '" placeholder="e.g. claude-sonnet-5-5"></label>'
    . '<label class="full">API key ' . ($key ? '<small class="muted">(saved: …' . h(substr($key, -4)) . '; leave blank to keep)</small>' : '') . '<input type="password" name="key" autocomplete="off" placeholder="Paste a new key"></label>'
    . '<label class="full check"><input type="checkbox" name="clear" value="1"> Remove the saved key</label><div class="full"><button class="btn sm">Save</button></div></form>'
    . ($prov && $key ? '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="aitest"><button class="btn sm ghost">Test connection</button></form>' : '')
    . '<p class="small muted">Get a key at console.anthropic.com (or platform.openai.com) and set a monthly spend limit there. The key is stored only in the private portal database, outside the website.</p>';
  $fbt = setting('fb_token');
  $fb = '<form method="post" class="grid2">' . csrf_field() . '<input type="hidden" name="act" value="fb">'
    . '<label>Facebook Page ID<input name="page" value="' . h(setting('fb_page_id')) . '" inputmode="numeric"></label><label>Graph API version<input name="ver" value="' . h(setting('fb_version', 'v23.0')) . '"></label>'
    . '<label class="full">Page access token ' . ($fbt ? '<small class="muted">(saved: …' . h(substr($fbt, -4)) . ')</small>' : '') . '<input type="password" name="token" autocomplete="off"></label>'
    . '<label class="full check"><input type="checkbox" name="clear" value="1"> Remove the saved token</label><div class="full"><button class="btn sm">Save</button></div></form>'
    . '<p class="small muted">Create an app at developers.facebook.com, add yourself as admin, and generate a long-lived Page access token with the pages_manage_posts permission. Posts are only published when you click “Publish to Facebook”.</p>';
  $dg = '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="digest"><label class="check"><input type="checkbox" name="on" value="1"' . (setting('digest_on', '1') === '1' ? ' checked' : '') . '> Email a daily 07:00 summary to ' . h(cfg('email')) . ' (new leads, follow-ups due, yesterday\'s visits)</label> <button class="btn sm">Save</button></form>'
    . '<p class="small muted">Last summary sent: ' . h(setting('digest_last', 'never')) . '</p>';
  $aud = []; foreach (db()->query('SELECT * FROM audit ORDER BY id DESC LIMIT 25') as $a) $aud[] = [h(substr($a['ts'], 0, 16)), h($a['actor']), h($a['action']), h($a['detail'])];
  layout('Settings', '<h1>Settings</h1><div class="cols">'
    . card('Change password', '<form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="act" value="pw"><label>Current password<input type="password" name="cur" required autocomplete="current-password"></label><label>New password (12+ characters)<input type="password" name="pw" minlength="12" required autocomplete="new-password"></label><button class="btn sm">Change</button></form>')
    . card('Two-step sign-in', $totp) . '</div>'
    . card('AI assistant (Claude or OpenAI)', $ai) . card('Facebook Page publishing', $fb) . card('Daily summary email', $dg)
    . card('Backup', '<p>Download a full copy of the portal database (leads, notes, statistics, settings). Keep it somewhere safe; it contains personal information.</p><a class="btn sm ghost" href="/portal/?p=backup">Download backup</a>')
    . card('Activity log', table(['When', 'Who', 'Action', 'Detail'], $aud)) . '<script src="/portal/assets/qrcode.min.js"></script>', 'settings');
}

redirect('?p=dashboard');
