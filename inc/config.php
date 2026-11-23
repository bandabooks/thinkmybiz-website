<?php
// Practice settings shared by the contact form, API and portal.
// To reuse this website for another practice, change these values (and the constants at the top of _src/build.py).
return [
  'practice'   => 'Think Mybiz Accountants & Tax Practitioners',
  'short'      => 'Think Mybiz',
  'domain'     => 'https://thinkmybiz.co.za',
  'email'      => 'accounts@thinkmybiz.co.za',   // practice mailbox: receives enquiries, sends replies, owns the portal
  'phone'      => '066 210 5631',
  'whatsapp'   => '27662105631',
  'signature'  => "Kind regards\nMF Banda\nSARS registered tax practitioner PR-0034776\nThink Mybiz Accountants & Tax Practitioners\n066 210 5631 · accounts@thinkmybiz.co.za · https://thinkmybiz.co.za",
  'timezone'   => 'Africa/Johannesburg',
  // Private data folder OUTSIDE the web root (never inside public_html, never in Git).
  'data_dir'   => dirname(__DIR__, 2) . '/.tmb-data',
  'services'   => [
    'individual-tax' => 'Individual & provisional tax', 'business-tax' => 'Business & trust tax', 'vat' => 'VAT services',
    'payroll' => 'Payroll & employer taxes', 'sars-disputes' => 'SARS disputes & resolution', 'tax-debt' => 'Tax debt & compliance recovery',
    'tax-planning' => 'Tax planning & specialist support', 'accounting' => 'Accounting & financial statements',
    'business-compliance' => 'CIPC, licences & tenders', 'business-advisory' => 'Business advisory',
    'digital-finance' => 'Digital finance & dashboards', 'expat-tax' => 'Foreign income & expat tax', 'internal-controls' => 'Internal controls review',
  ],
];
