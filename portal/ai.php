<?php
// AI drafting (Anthropic Claude or OpenAI). Drafts only: every text is reviewed and sent by the practitioner.
// Only non-identifying context is sent: first name, service, the enquiry text and public website content.
if (!defined('TMB_PORTAL')) { http_response_code(404); exit; }

const AI_GUARDRAILS = "You write for a South African accounting and tax practice: Think Mybiz Accountants & Tax Practitioners, led by MF Banda, "
  . "SARS registered tax practitioner PR-0034776. The practice is remote-first and serves all nine provinces; in-person consultations are by appointment in Mokopane (head office) and Midrand. "
  . "Rules: use South African English; be professional, warm and concise; never invent tax rates, thresholds, deadlines, fees, case outcomes, testimonials or statistics; "
  . "never promise a result from SARS; do not give specific tax advice in marketing text; invite the reader to a free consultation instead; "
  . "do not ask anyone to send ID numbers, tax numbers or documents by email or social media (offer a secure route instead); "
  . "write plain text only, without markdown headings or asterisks.";

function ai_configured() { return setting('ai_provider') !== '' && setting('ai_key') !== ''; }

function ai_complete($task, $maxTokens = 900) {
  $provider = setting('ai_provider'); $key = setting('ai_key'); $model = trim(setting('ai_model'));
  if ($provider === '' || $key === '') return [false, 'AI is not set up yet. Add a provider and API key under Settings.'];
  if ($provider === 'anthropic') {
    $url = 'https://api.anthropic.com/v1/messages';
    $payload = ['model' => $model ?: 'claude-sonnet-5-5', 'max_tokens' => $maxTokens, 'system' => AI_GUARDRAILS,
                'messages' => [['role' => 'user', 'content' => $task]]];
    $headers = ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
  } elseif ($provider === 'openai') {
    if ($model === '') return [false, 'Enter the OpenAI model name under Settings.'];
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = ['model' => $model, 'max_completion_tokens' => $maxTokens,
                'messages' => [['role' => 'system', 'content' => AI_GUARDRAILS], ['role' => 'user', 'content' => $task]]];
    $headers = ['content-type: application/json', 'authorization: Bearer ' . $key];
  } else return [false, 'Unknown AI provider.'];
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => $headers,
                          CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10]);
  $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  if ($res === false) return [false, 'Could not reach the AI service: ' . $err];
  $j = json_decode($res, true);
  if ($code >= 400) return [false, 'AI service error (' . $code . '): ' . ($j['error']['message'] ?? substr($res, 0, 200))];
  $text = $provider === 'anthropic'
    ? implode('', array_map(function ($b) { return $b['type'] === 'text' ? $b['text'] : ''; }, $j['content'] ?? []))
    : ($j['choices'][0]['message']['content'] ?? '');
  $text = trim($text);
  if ($text === '') return [false, 'The AI service returned no text.'];
  audit('portal', 'ai_draft', $provider . ' ' . mb_substr($task, 0, 80));
  return [true, $text];
}

function ai_lead_reply($lead, $intent) {
  $first = trim(explode(' ', (string)$lead['name'])[0]);
  $intents = ['first' => 'a first reply that thanks them, shows you understood their need, and proposes a short free consultation call (offer two time options as placeholders [DAY/TIME])',
              'docs' => 'a reply listing, in general terms, what information would help prepare for the consultation, and offering a secure route for documents',
              'quote' => 'a polite follow-up on a quotation already sent, asking whether they have questions and offering to walk through it',
              'checkin' => 'a short, friendly check-in because they have not replied yet; no pressure'];
  $task = "Draft an email body (no subject line) to a prospective client.\nFirst name: " . ($first ?: 'there') . "\nService: " . $lead['service']
        . "\nTheir enquiry: \"\"\"" . mb_substr((string)$lead['message'], 0, 1500) . "\"\"\"\nWrite " . ($intents[$intent] ?? $intents['first'])
        . ". Under 170 words. Do not add a sign-off or signature; they are added automatically.";
  return ai_complete($task, 700);
}

function ai_social($channel, $topic, $link, $tone) {
  $limits = ['facebook' => 'Facebook Page post, 60-120 words, 1-3 relevant hashtags at the end',
             'linkedin' => 'LinkedIn post for business owners, 80-150 words, short paragraphs, 2-4 hashtags at the end',
             'instagram' => 'Instagram caption, 40-90 words, 5-8 hashtags at the end',
             'whatsapp' => 'WhatsApp Status or broadcast message, under 60 words, no hashtags',
             'google' => 'Google Business Profile update, 60-120 words, no hashtags',
             'x' => 'X post under 260 characters, 1-2 hashtags',
             'tiktok' => 'TikTok caption plus a 20-second talking-head script outline'];
  $task = "Write one " . ($limits[$channel] ?? $limits['facebook']) . ".\nTopic: " . $topic . "\nTone: " . ($tone ?: 'helpful and trustworthy')
        . "\nInclude a clear call to action to book a free consultation" . ($link ? " and include this exact link once: $link" : '') . ".\nReturn only the post text.";
  return ai_complete($task, 700);
}

function ai_strategy($goal) {
  $task = "Create a practical 4-week marketing plan for this practice with a small budget. Goal: " . $goal
        . "\nCover: target audiences, the 3 strongest channels and why, a weekly posting schedule, 8 post ideas tied to SARS/CIPC dates and common problems, "
        . "one lead magnet idea, how to measure success with UTM-tagged links and the lead register, and follow-up rules for new leads. "
        . "Plain text with numbered sections. Do not invent statistics.";
  return ai_complete($task, 1800);
}

// Facebook Page publishing through the Graph API (needs a Page access token with pages_manage_posts).
function fb_publish($message, $link) {
  $page = setting('fb_page_id'); $token = setting('fb_token'); $ver = setting('fb_version', 'v23.0');
  if ($page === '' || $token === '') return [false, 'Facebook Page is not connected. Add the Page ID and Page access token under Settings.'];
  $ch = curl_init('https://graph.facebook.com/' . rawurlencode($ver) . '/' . rawurlencode($page) . '/feed');
  $fields = ['message' => $message, 'access_token' => $token]; if ($link) $fields['link'] = $link;
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
  $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $j = json_decode((string)$res, true);
  if ($code >= 400 || empty($j['id'])) return [false, 'Facebook error: ' . ($j['error']['message'] ?? 'unknown')];
  return [true, $j['id']];
}
