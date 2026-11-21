#!/usr/bin/env python3
"""Generates the Think Mybiz Accountants static site at the repo root from shared templates.

Content source: Think Mybiz Accountants and Tax Practitioners - Business Profile 2026.
Do not add clients, figures, prices or claims that are not in the profile or confirmed by MF Banda.
"""
import os, json, html, hashlib

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")  # repo root = public_html
DOMAIN = "https://thinkmybiz.co.za"
BRAND = "Think Mybiz Accountants &amp; Tax Practitioners"
BRAND_TXT = "Think Mybiz Accountants & Tax Practitioners"
LEGAL = "Think Mybiz Accountants and Tax Practioners (Pty) Ltd"
EMAIL = "accounts@thinkmybiz.co.za"
PHONE, TEL = "066 210 5631", "+27662105631"
MOBILE, MTEL = "072 104 1051", "+27721041051"
ADDRESS = "No 48 Rabe Street, Havenga Building, Mokopane, Limpopo, 0600"
PR = "PR-0034776"
# WhatsApp number (confirmed by MF Banda 20/11/2026: 066 210 5631). Empty = no WhatsApp buttons.
WHATSAPP, WA_DISPLAY = "27662105631", "066 210 5631"

# ---- Settings MF Banda can fill in; empty values switch the feature off (nothing invented is published).
# Social media profiles: paste the full profile URL. Shown as footer icons and as "sameAs" for Google.
SOCIAL = {"facebook": "", "linkedin": "", "instagram": "", "tiktok": "", "x": "", "youtube": ""}
# Office hours for Google, e.g. ["Mo-Fr 08:00-17:00"]. Empty until confirmed.
OPENING_HOURS = []
# Google Analytics 4 measurement ID, e.g. "G-XXXXXXXXXX". Empty = no analytics and no cookie banner.
GA_ID = ""
# Google Search Console HTML-tag verification code (the content="..." value). Empty = no tag.
GSC_VERIFY = ""
# IndexNow key (Bing, Yandex and others): the key file is published at /<key>.txt so new pages can be submitted instantly.
INDEXNOW_KEY = "1b3bcb6effaa658108b8dde33d57d2f6"

E = html.escape
I = {
 "user": '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
 "building": '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
 "receipt": '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
 "users": '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0114 0M16 4.5a3.5 3.5 0 010 7M22 20a7 7 0 00-5-6.7"/>',
 "scale": '<path d="M12 3v18M5 21h14M6 7h12M6 7l-3 7a3 3 0 006 0zM18 7l-3 7a3 3 0 006 0z"/>',
 "alert": '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
 "compass": '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
 "book": '<path d="M4 4h7a3 3 0 013 3v14a2 2 0 00-2-2H4zM20 4h-5a3 3 0 00-3 3"/><path d="M20 4v15h-6"/>',
 "clipboard": '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
 "chart": '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
 "shield": '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
 "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 "phone": '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 006 6L16 13l5 2v4a2 2 0 01-2 2A16 16 0 013 5a2 2 0 012-2z"/>',
 "pin": '<path d="M12 22s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
 "globe": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
 "check": '<path d="M5 12l4 4 10-10"/>',
 "clock": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 "lock": '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
 "cloud": '<path d="M7 18h10a4 4 0 00.5-8A6 6 0 006 9.5 4.3 4.3 0 007 18z"/>',
}
def svg(n, cls=""): return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{I[n]}</svg>'
SOCIAL_ICONS = {
 "facebook": ("Facebook", '<path d="M14 8h3V4h-3a4 4 0 00-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8z"/>'),
 "linkedin": ("LinkedIn", '<rect x="3" y="9" width="4" height="12"/><circle cx="5" cy="5" r="2"/><path d="M10 9h4v2a4 4 0 017 3v7h-4v-6a2 2 0 00-4 0v6h-3z"/>'),
 "instagram": ("Instagram", '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>'),
 "tiktok": ("TikTok", '<path d="M15 3c.4 2.6 2 4.2 5 4.5v3.7c-1.9 0-3.6-.6-5-1.6V16a6 6 0 11-6-6v3.7a2.3 2.3 0 102.3 2.3V3z"/>'),
 "x": ("X", '<path d="M4 4l16 16M20 4L4 20"/>'),
 "youtube": ("YouTube", '<rect x="2" y="6" width="20" height="12" rx="4"/><path d="M10 9l5 3-5 3z"/>'),
}
def social_links():
    out = "".join(f'<a href="{E(u)}" rel="noopener" target="_blank" aria-label="{SOCIAL_ICONS[k][0]}" data-track="social_{k}">'
                  f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{SOCIAL_ICONS[k][1]}</svg></a>'
                  for k, u in SOCIAL.items() if u)
    return f'<div class="social">{out}</div>' if out else ""
def icon(n): return f'<div class="icon">{svg(n)}</div>'
def ul(items, cls="checks"): return f'<ul class="{cls}">' + "".join(f"<li>{i}</li>" for i in items) + "</ul>"
def rows(pairs): return '<div class="rows">' + "".join(f'<div class="row"><div class="k">{k}</div><div class="v">{v}</div></div>' for k, v in pairs) + "</div>"
def note(h, t): return f'<div class="note"><h3>{h}</h3><p>{t}</p></div>'

# ------------------------------------------------------------------ services (from the business profile)
SERVICES = [
 dict(slug="individual-tax", send=['Your SARS tax number and ID (sent through a secure route)', 'IRP5/IT3(a) certificates from every employer', 'IT3(b)/IT3(c) investment certificates', 'Retirement annuity and medical aid tax certificates', 'Rental, freelance or business income and expense records', 'Travel logbook, if you receive a travel allowance'], icon="user", short="Individual & provisional tax",
  title="Individual and provisional tax",
  seo="ITR12 Personal Tax Returns &amp; Provisional Tax | Think Mybiz",
  desc="Personal tax returns (ITR12), provisional tax, rental and freelance income, travel logbooks and assessment checks, anywhere in South Africa. Free consultation.",
  card="ITR12 returns, provisional tax, rental and sole-proprietor income, travel logbooks and assessment reviews.",
  lead="We assist employees, directors, independent contractors, sole proprietors, landlords and other individual taxpayers with their South African tax affairs, from registration through to assessment review.",
  body=lambda: "".join([
   "<h2>Personal income tax</h2>", ul([
    "Income tax registration, registered-detail updates and authorised eFiling administration.",
    "Preparation and submission of individual income tax returns (ITR12), including review of prepopulated information and auto-assessments.",
    "Reconciliation of IRP5 and IT3 certificates, investment income, retirement contributions and medical tax information.",
    "Rental income and expenses, sole-proprietor income, commission income and other taxable receipts.",
    "Travel allowance and business-travel schedules, including conversion and review of electronic logbooks against SARS record requirements.",
    "Assessment checks, refund follow-ups, supporting-document submissions and corrections where the appropriate process permits."]),
   "<h2>Provisional tax</h2><p>We prepare provisional tax calculations and IRP6 submissions for qualifying individuals, companies and trusts. This includes first and second estimates, consideration of additional payments, reconciliation to annual tax and reminders of the relevant payment dates.</p>",
   "<h2>Sole proprietors and independent earners</h2><p>We compile income and expense schedules from business records, separate private expenditure, review allowable deductions and connect the business results to the owner's personal return. We also help identify VAT and employer-registration obligations as the business grows.</p>",
   note("What you receive", "An agreed return or tax calculation, a supporting-document request, the amount and timing of any payment, and submission evidence. Final liability and refund processing remain subject to SARS assessment and review.")]),
  faq=[("Can you check an auto-assessment before I accept it?", "Yes. We review the prepopulated information and auto-assessment against your IRP5, IT3 and other certificates, and add income or deductions that were left out where the process permits."),
       ("I earn rental or freelance income. Can you include it?", "Yes. We prepare schedules for rental income and expenses, sole-proprietor, commission and other taxable receipts, and connect them to your personal return."),
       ("Do I need to come to the office?", "No. Documents are sent electronically and we meet by phone or video call, wherever you are in South Africa.")]),
 dict(slug="business-tax", send=['Company or trust registration documents', 'Latest financial statements or trial balance', 'Prior-year returns and SARS correspondence', 'Shareholder or beneficiary loan account details', 'Dividend declarations and trustee resolutions, where relevant'], icon="building", short="Business & trust tax",
  title="Business and trust tax",
  seo="Company Tax ITR14, Trusts &amp; Tax Clearance | Think Mybiz",
  desc="ITR14 company and CC tax returns, trust tax (ITR12T), dividends tax, tax clearance PINs and nonprofit tax support across South Africa. Free consultation.",
  card="ITR14 for companies and CCs, trusts, dividends tax, tax clearance PINs and nonprofit tax support.",
  lead="We align taxable income with the underlying financial records and the requirements of the relevant taxpayer. Company, trust and owner obligations are considered separately.",
  body=lambda: "".join([
   "<h2>Companies and close corporations</h2>", ul([
    "Corporate income tax registration and eFiling administration, including representative-taxpayer updates and tax-type transfers.",
    "Preparation and submission of ITR14 returns and supporting tax computations.",
    "Reconciliation of accounting profit to taxable income, with schedules for relevant deductions, allowances and assessed losses.",
    "Review of small business corporation eligibility and turnover tax suitability where applicable.",
    "Review of shareholder and director transactions, loan accounts, remuneration and distributions.",
    "Dividends tax declarations, withholding administration and exemption documentation where applicable.",
    "Tax Compliance Status (TCS) reviews and good-standing PIN requests, with assistance to address non-compliance.",
    "Dormant-company filing reviews, historic-return catch-up and tax deregistration support when appropriate."]),
   "<h2>Trusts and beneficiaries</h2><p>We assist with SARS trust registration, ITR12T returns, financial and tax schedules, beneficiary allocations and the supporting records for trustee decisions. Work can include IT3(t) reporting and reconciliation of distributions to the affected beneficiaries' tax information, where required.</p><p>Trust tax is considered alongside the trust deed, letters of authority and trustee resolutions. Legal establishment, deed amendments and complex trust structures require the appropriate legal input.</p>",
   "<h2>Nonprofit and exempt organisations</h2><p>We provide tax-registration and compliance support for nonprofit organisations, and assistance with public benefit organisation and section 18A applications where the organisation qualifies. We distinguish CIPC or nonprofit registration from SARS tax-exempt approval and assess ongoing filing and record requirements.</p>",
   note("A scope suited to the entity", "We confirm the entity type, tax registrations, reporting period and applicable requirements before preparation. Exemptions, incentives and special tax regimes depend on statutory eligibility.")]),
  faq=[("My company has years of outstanding returns. Can you help?", "Yes. We review the company's filing history, catch up historic returns and help address non-compliance so that a tax compliance status PIN can be obtained."),
       ("Do you handle trusts?", "Yes: SARS trust registration, ITR12T returns, beneficiary allocations and IT3(t) reporting, considered alongside the trust deed and trustee resolutions.")]),
 dict(slug="vat", send=['VAT registration notice (or turnover figures, if not yet registered)', 'Sales and purchase invoices for the period', 'Bank statements for the period', 'SARS VAT statements and any verification letters'], icon="receipt", short="VAT services",
  title="VAT services",
  seo="VAT Registration, VAT201 Returns &amp; Audits | Think Mybiz",
  desc="VAT registration, VAT201 returns, reconciliations, SARS VAT verifications and audits, and deregistration for South African businesses. Free consultation.",
  card="VAT registration, VAT201 returns, reconciliations, SARS verifications and deregistration.",
  lead="We support VAT vendors through registration, periodic returns, reconciliations, SARS queries and changes to the business. Good source records are central to a defensible VAT position.",
  body=lambda: "".join([
   "<h2>Registration and administration</h2>", ul([
    "Compulsory and voluntary VAT registration assessments and applications.",
    "Supporting-document packs, SARS registration queries and follow-up.",
    "Registered-detail and banking-detail updates through the applicable verification process.",
    "Tax-period reviews and VAT deregistration applications, including consideration of closing adjustments."]),
   "<h2>Returns and VAT reconciliations</h2>", ul([
    "Preparation and submission of VAT201 returns from accounting records.",
    "Reconciliation of output tax to sales, and input tax to qualifying purchases and supporting tax invoices.",
    "Review of standard-rated, zero-rated, exempt and out-of-scope transactions, as applicable.",
    "Consideration of credit notes, debit notes, bad-debt adjustments, capital assets, mixed-use expenditure and apportionment.",
    "Reconciliation of VAT control accounts and SARS statements, including payment-allocation and refund queries."]),
   "<h2>Verification and dispute support</h2><p>We assemble transaction schedules and invoices, respond to VAT verification and audit requests, review findings and assist with the applicable correction, objection or appeal process. Where SARS has issued an estimated assessment, we assess the prescribed route for supplying the missing information and seeking a revised assessment.</p>",
   note("Practical VAT advice", "We explain how VAT affects quotations, invoicing, cash flow and recordkeeping. VAT charged to customers must be accounted for correctly, and input tax claims require the relevant legal and documentary support.")]),
  faq=[("SARS is verifying my VAT return. What should I do?", "Send us the SARS letter as soon as you receive it. We assemble the transaction schedules and invoices SARS asks for and respond within the stated deadline."),
       ("Should my business register for VAT?", "We assess whether registration is compulsory or whether voluntary registration makes sense for your business, and prepare the application and supporting documents.")]),
 dict(slug="payroll", send=['Employee list with contracts and start dates', 'Monthly salary inputs: overtime, allowances, deductions', 'Previous EMP201s and EMP501s', 'IRP5s already issued, and any SARS employer letters'], icon="users", short="Payroll & employer taxes",
  title="Payroll and employer taxes",
  seo="Payroll, EMP201, EMP501 &amp; UIF Compliance | Think Mybiz",
  desc="Monthly payroll and payslips, EMP201 and EMP501, IRP5s, ETI, UIF and Compensation Fund returns for South African employers. Free consultation.",
  card="Payroll and payslips, EMP201, EMP501, IRP5s, ETI, UIF and Compensation Fund returns.",
  lead="We help employers connect payroll calculations, employee certificates and SARS declarations, with reconciliation across the monthly and employer-reconciliation cycles.",
  body=lambda: "".join([
   "<h2>Payroll processing</h2>", ul([
    "Payroll setup and employee-data maintenance, with agreed controls over changes.",
    "Salary and wage calculations, payslips and payroll reporting.",
    "Treatment of overtime, allowances, benefits, deductions and leave-related payments using employer-approved inputs.",
    "Payroll journals and reconciliation of payroll balances to the accounting records."]),
   "<h2>Employer registrations and returns</h2>", ul([
    "PAYE, UIF and Skills Development Levy registration and administration where required.",
    "EMP201 declarations and payment reconciliations.",
    "Interim and annual EMP501 employer reconciliations.",
    "Preparation, reconciliation and issue of IRP5 and IT3(a) employee certificates.",
    "Employment Tax Incentive eligibility reviews, calculations and supporting records where applicable.",
    "Tax-directive application support for qualifying payments, with specialist input for complex cases."]),
   "<h2>Employer compliance recovery</h2><p>We investigate mismatches between payroll, submitted declarations, payments and employee certificates. We assist with outstanding reconciliations, correction of certificates and responses to SARS employer-tax queries, penalties and assessments.</p>",
   "<h2>Related labour administration</h2><p>Related support includes UIF employer administration and declarations, Compensation Fund registration and returns of earnings, and assistance with letters of good standing. These processes are managed through the relevant authorities and are distinct from SARS tax filings.</p>",
   note("Employer approval and records", "The employer confirms employee details, remuneration and payroll changes. We agree cut-off dates, filing responsibilities and payment instructions in the engagement.")]),
  faq=[("Our EMP501 does not balance. Can you fix it?", "Yes. We investigate mismatches between payroll, EMP201 declarations, payments and IRP5 certificates, and prepare the corrections and outstanding reconciliations."),
       ("Do you help with UIF and the Compensation Fund?", "Yes: UIF employer administration and declarations, Compensation Fund registration and returns of earnings, and letters of good standing.")]),
 dict(slug="sars-disputes", send=['The full SARS letter or assessment, and the date you received it', 'The return SARS is questioning', 'Supporting documents for the items in dispute', 'Any case number and previous correspondence with SARS'], icon="scale", short="SARS disputes & resolution",
  title="SARS disputes and resolution",
  seo="SARS Objections, Appeals &amp; Audits Help | Think Mybiz",
  desc="Help with SARS audits, verifications, requests for correction, objections, appeals, ADR and penalty remission, from a SARS registered tax practitioner.",
  card="Audits and verifications, objections, appeals, ADR, late submissions and penalty remission.",
  lead="A disputed assessment requires the correct procedure, supporting evidence and careful deadline management. We review the facts and the available remedy before preparing a response.",
  body=lambda: "".join([
   "<h2>How we handle a dispute</h2>", rows([
    ("Assessment and case review", "Review SARS notices, returns, accounts and supporting records; identify the disputed issues and the appropriate procedural route."),
    ("Verification and audit responses", "Prepare evidence packs, reconcile figures and respond to SARS information requests and proposed findings."),
    ("Corrections and reasons", "Use a Request for Correction where available, or request reasons needed to understand and challenge an assessment or qualifying decision."),
    ("Objections", "Prepare and lodge a Notice of Objection with grounds, relevant calculations and supporting evidence for an objectionable assessment or decision."),
    ("Appeals", "Review an objection outcome and prepare a Notice of Appeal where there is a basis to pursue the matter."),
    ("Alternative dispute resolution", "Assist with ADR preparation and participation where the dispute qualifies and SARS agrees to the process."),
    ("Late submissions", "Assess whether an extension or condonation is legally available and prepare the motivation and evidence."),
    ("Outcome implementation", "Review the decision, revised assessment, account adjustments and any remaining steps or payment obligations.")]),
   "<h2>Penalties and interest</h2><p>We prepare requests for remission of administrative or late-payment penalties and interest where legislation permits, and assess further remedies following the outcome. Understatement penalties are reviewed with the facts, disclosure history and applicable grounds. The available remedy differs by tax type and decision.</p>",
   note("Act as soon as a notice arrives", "Send the complete notice and the date you received it. Each case has its own filing and escalation deadlines. An objection or appeal does not by itself suspend payment; suspension must be considered separately. Court proceedings are coordinated with legal practitioners where required.")]),
  faq=[("SARS has issued an assessment I disagree with. What now?", "Send us the full notice and the date you received it. We review the facts and choose the right route: a request for correction, a request for reasons, or a notice of objection, followed by an appeal or ADR where there is a basis."),
       ("Does lodging an objection stop SARS collecting the debt?", "No. An objection or appeal does not by itself suspend payment. A separate suspension of payment request must be considered, and we can prepare it where it is available."),
       ("Can you help if I missed the deadline to object?", "We assess whether an extension or condonation is legally available and, if so, prepare the motivation and evidence.")]),
 dict(slug="tax-debt", send=['Your latest SARS statement of account', 'Final demand or collection letters, with dates', 'A list of returns you have not yet submitted', 'Recent bank statements and a cash-flow summary'], icon="alert", short="Tax debt & compliance recovery",
  title="Tax debt and compliance recovery",
  seo="SARS Tax Debt Help: Payment Arrangements | Think Mybiz",
  desc="Owe SARS? Account reconciliation, payment arrangements, suspension of payment, debt compromise, VDP and Tax Ombud help from a registered practitioner.",
  card="SARS account reconciliation, payment arrangements, suspension, compromise, VDP and Tax Ombud.",
  lead="We first establish whether the amount is correct, whether returns are outstanding and whether the taxpayer disputes the liability or needs assistance with payment. These questions determine the next step.",
  body=lambda: "".join([
   "<h2>SARS account and debt reconciliation</h2><p>We reconcile assessments, penalties, interest, payments and credits; investigate unallocated payments and incorrect balances; and prepare account queries with supporting proof. Outstanding returns and reconciliations are identified as part of the recovery plan.</p>",
   rows([
    ("Payment arrangements", "Prepare instalment or deferral requests, cash-flow information and supporting motivations; review terms and assist with monitoring agreed payments."),
    ("Suspension of payment", "Assess and prepare a section 164 application for disputed tax where available, with evidence supporting the request and follow-up on the outcome."),
    ("Debt compromise", "Assess eligibility and prepare an application and financial disclosure for SARS consideration where the statutory criteria can be met."),
    ("Demand and collection notices", "Review notices, reconcile the debt and assist with the appropriate administrative response. Urgent enforcement or court matters are referred for legal assistance.")]),
   "<h2>Voluntary disclosure and historic defaults</h2><p>We assess whether a historic default may qualify for the permanent Voluntary Disclosure Programme, help assemble a complete disclosure and coordinate specialist input where required. Routine corrections, VDP relief and debt compromise are separate procedures with different requirements.</p>",
   "<h2>Service complaints and the Tax Ombud</h2><p>We assist with delayed refunds, unresolved account queries and administrative or procedural complaints. This can include SARS complaints escalation and a Tax Ombud submission where the mandate and access requirements are met. Complaints do not replace objections or appeals against the merits of an assessment.</p>",
   note("Decisions rest with SARS", "Payment terms, suspension, remission, compromise and VDP relief are subject to eligibility and SARS approval. Interest and recovery consequences must be considered while an application is pending. We do not promise debt write-offs or a particular outcome.")]),
  faq=[("I owe SARS money I cannot pay at once. Can you help?", "Yes. We first check that the balance is correct, then prepare an instalment or deferral request with cash-flow information and supporting motivation for SARS to consider."),
       ("Can you get my tax debt written off?", "We do not promise write-offs. Where the statutory criteria may be met, we assess eligibility for a compromise and prepare the application; the decision rests with SARS."),
       ("My refund has been delayed for months. What can be done?", "We follow up the account query and, where the requirements are met, escalate through SARS complaints and the Tax Ombud.")]),
 dict(slug="tax-planning", send=['A short description of the planned transaction or decision', 'Agreements, valuations or offers, if available', 'Recent financial statements and tax returns'], icon="compass", short="Tax planning & specialist support",
  title="Tax planning and specialist support",
  seo="Tax Planning: CGT, Property &amp; Estates | Think Mybiz",
  desc="Tax advice before you act: capital gains tax, property, dividends, donations, estates, cross-border tax and transaction planning in South Africa.",
  card="Capital gains, property, dividends, donations and estates, cross-border and transaction planning.",
  lead="We help clients understand the tax consequences of business and personal decisions before transactions are concluded. Advice is based on the facts and the applicable law, with specialist coordination where the matter requires it.",
  body=lambda: "".join([
   "<h2>Areas we advise on</h2>", rows([
    ("Capital gains tax", "Calculate and report relevant gains and losses on disposals, including property, business assets and investments, with applicable exclusions and relief considered."),
    ("Property and rental tax", "Review rental schedules, property disposals and the interaction of VAT, income tax and capital gains tax; coordinate transfer-duty matters with the conveyancer."),
    ("Dividends and withholding taxes", "Review distributions and relevant withholding obligations, exemption declarations and treaty documentation where applicable."),
    ("Donations and estate matters", "Assist with donations tax calculations and reporting, and deceased-estate tax compliance and estate-duty support in coordination with the executor and legal advisers."),
    ("Cross-border tax", "Initial assessment of foreign income, residency changes, foreign tax credits, non-resident obligations and international-transfer tax documentation, with specialist support as needed."),
    ("Transaction planning", "Assess owner remuneration, asset acquisitions, business structures, disposals and succession proposals before implementation."),
    ("Incentives and exemptions", "Review qualifying allowances, small-business regimes and available exemptions with the required eligibility evidence.")]),
   "<h2>Additional specialist tax coordination</h2><p>For customs and excise, transfer pricing, securities transfer tax, mining royalties, carbon tax, global minimum tax and other sector-specific taxes or levies, we assess the requirement and coordinate appropriately qualified specialists. Acceptance of a specialist mandate depends on the expertise, authorisation and scope required.</p>",
   note("Advice before implementation", "Legal drafting, conveyancing, regulated investment advice and court representation require the appropriate professional mandate. We agree responsibilities and any external specialist fees before proceeding.")]),
  faq=[("I am selling a property. Should I speak to you first?", "Yes. We review the disposal and the interaction of income tax, capital gains tax and VAT before the sale, and coordinate transfer-duty matters with the conveyancer."),
       ("How should I pay myself from my company?", "We assess owner remuneration, dividends and loan accounts against the company's position and your personal tax before you implement a structure.")]),
 dict(slug="accounting", send=['Bank statements for the period (PDF or CSV)', 'Sales and purchase invoices', 'Payroll reports, if you have staff', 'Previous financial statements and trial balance'], icon="book", short="Accounting & financial statements",
  title="Accounting and financial reporting",
  seo="Bookkeeping &amp; Annual Financial Statements | Think Mybiz",
  desc="Monthly bookkeeping, reconciliations, management accounts, catch-up work and compiled annual financial statements for SA businesses. Free consultation.",
  card="Bookkeeping, reconciliations, management accounts, catch-up work and annual financial statements.",
  lead="Reliable accounting gives business owners a usable view of performance and supports tax filings, finance applications and year-end reporting. We offer ongoing services and once-off catch-up work.",
  body=lambda: "".join([
   "<h2>Bookkeeping and monthly accounts</h2>", ul([
    "Capture and classification of transactions from bank statements, invoices and supporting records.",
    "Bank, cash and control-account reconciliations, with queries raised for missing or unclear transactions.",
    "Debtor and creditor schedules, collection information and supplier reconciliations.",
    "Fixed-asset registers, inventory-accounting support and payroll integration where applicable.",
    "Monthly or periodic management accounts, with explanations of material movements.",
    "Historic bookkeeping recovery and year-end adjustment schedules."]),
   "<h2>Annual financial statements</h2><p>We compile annual financial statements and related working papers for companies, close corporations, sole proprietors, trusts and other entities within the agreed mandate. The reporting framework is determined by the entity and applicable requirements, including IFRS for SMEs where appropriate.</p><p>Services may include trial-balance review, reconciliations, disclosure schedules, tax computations and close corporation accounting officer work where the appointment and professional requirements are met. Statutory audit or independent review requirements are assessed separately and referred where necessary.</p>",
   "<h2>Management information</h2><p>We explain profitability, cash requirements and outstanding balances so that owners can make informed decisions. Client books are kept on Taxnify, our accounting and compliance platform, with a full audit trail.</p>",
   note("Resolving uncertain transactions", "We maintain a clear list of missing evidence and transactions needing client explanation. Queries are resolved through supporting documents and agreed treatment before the affected reports or returns are finalised.")]),
  faq=[("My books are a year or more behind. Can you catch up?", "Yes. We offer once-off historic bookkeeping recovery from bank statements and supporting records, followed by year-end adjustments, financial statements and tax returns."),
       ("Do you prepare financial statements for close corporations?", "Yes, including close corporation accounting officer work where the appointment and professional requirements are met.")]),
 dict(slug="business-compliance", send=['Company registration documents (CoR14.3)', "Directors' and shareholders' ID copies (through a secure route)", 'Tax compliance status PIN, if you have one', 'The tender document or licence requirements you are working with'], icon="clipboard", short="CIPC, licences & tenders",
  title="CIPC, registrations, licences and tenders",
  seo="CIPC, CSD, Tenders &amp; Business Licences | Think Mybiz",
  desc="Company registration, CIPC annual returns, beneficial ownership, CSD registration, tender packs, CIDB, PSIRA, licences and B-BBEE affidavits.",
  card="Company registration, CIPC annual returns, CSD, tender packs, CIDB, PSIRA, licences and B-BBEE affidavits.",
  lead="We support the administrative requirements that sit alongside the accounts and tax returns, helping owners maintain consistent information across the relevant authorities.",
  body=lambda: "".join([
   "<h2>CIPC and company administration</h2>", ul([
    "Company registration and company-information maintenance.",
    "Annual returns and the associated financial information or accountability submissions where required.",
    "Beneficial ownership filings and supporting ownership information.",
    "Director, registered-office and other company-detail changes.",
    "Maintenance of agreed company records and assistance with deregistration or reinstatement processes where appropriate."]),
   "<h2>Registrations, licences and application support</h2><p>We assist with Central Supplier Database registration and profile maintenance, tender compliance packs and financial documents, and relevant business applications. Support can include CIDB, PSIRA and licence-related documentation within an agreed administrative scope. Approvals and licensing decisions remain with the relevant authority.</p>",
   "<h2>B-BBEE affidavits</h2><p>We assist with B-BBEE affidavit documentation where the entity qualifies. Where a verification certificate is required, the work is referred to an appropriately accredited verification provider.</p>",
   note("A defined engagement", "The quotation identifies deliverables, exclusions, responsibilities and fees; external approval and third-party charges are addressed separately.")]),
  faq=[("Can you register a new company for me?", "Yes. We handle company registration with CIPC, and can then register the company for the taxes it needs and set up its books."),
       ("We want to tender for government work. Where do we start?", "We assist with CSD registration and profile maintenance, tax compliance status, and the financial and compliance documents in a tender pack, including CIDB or PSIRA documentation where relevant.")]),
 dict(slug="business-advisory", send=['What you want to decide or achieve', 'Recent financial statements or management accounts', 'Bank statements and known upcoming costs'], icon="chart", short="Business advisory",
  title="Business advisory and funding support",
  seo="Business Advisory, Budgets &amp; Funding Packs | Think Mybiz",
  desc="Business structure, start-up advice, budgets, cash-flow forecasts, business plans and financial information for funding and tenders in South Africa.",
  card="Business structure, start-up advice, budgets, cash-flow forecasts, business plans and funding packs.",
  lead="Advisory engagements connect recommendations to the owner's financial information and compliance obligations, so decisions are made on reliable numbers.",
  body=lambda: "".join([
   "<h2>What advisory work covers</h2>", ul([
    "Business structure and start-up registration needs: which entity, which registrations, and in what order.",
    "Pricing and budgets that reflect your real costs, VAT position and tax.",
    "Cash-flow planning and forecasts, including provision for SARS payments.",
    "Internal recordkeeping that makes monthly accounting and tax filing straightforward.",
    "Preparation for finance or tender opportunities."]),
   "<h2>Management information and funding support</h2><p>We prepare budgets, cash-flow forecasts, business plans and financial information for funding applications. We explain profitability, cash requirements and outstanding balances so that owners can make informed decisions.</p>",
   note("Advice tied to your numbers", "Recommendations are based on your financial information and compliance obligations. Legal drafting and regulated investment advice require the appropriate professional mandate.")]),
  faq=[("I am starting a business. Should it be a company or a sole proprietorship?", "We look at your plans, expected income, risks and tax position, and explain the registration and compliance obligations of each option before you decide."),
       ("Can you prepare financials for a funding application?", "Yes. We prepare budgets, cash-flow forecasts, business plans and the financial information lenders and funders ask for.")]),
 dict(slug="digital-finance", send=["Access to your bank statements (PDF or CSV) for the last 3-6 months", "Your current spreadsheets or accounting system export", "A list of the numbers you want to see each month"], icon="cloud", short="Digital finance & dashboards",
  title="Digital finance and management dashboards",
  seo="Cloud Bookkeeping &amp; Management Dashboards | Think Mybiz",
  desc="Move your books online with Taxnify, get monthly management dashboards and know your cash, debtors and SARS amounts due. Free consultation.",
  card="Cloud bookkeeping on Taxnify, monthly dashboards, and cash, debtor and SARS positions at a glance.",
  lead="We move your books from spreadsheets and paper to a cloud system, and give you a monthly picture of the numbers that matter: cash, what customers owe, what you owe, and what is due to SARS.",
  body=lambda: "".join([
   "<h2>What the service covers</h2>", ul([
    "Setting up your bookkeeping on Taxnify, our accounting and compliance platform, or tidying an existing system.",
    "Migrating opening balances and history from spreadsheets or your previous system, reconciled to the bank.",
    "A monthly management pack: profit and loss, cash position, debtors and creditors, and VAT and PAYE amounts coming due.",
    "Simple dashboards that show trends month by month, so you can see problems early.",
    "Digital document capture, so invoices and slips are stored with the transactions they support.",
    "Custom reports or integrations with other business systems, built with our sister company Think Mybiz Tech where needed."]),
   "<h2>Why it matters</h2><p>Most tax problems start as bookkeeping problems: missing invoices, unreconciled bank accounts, or VAT and PAYE amounts nobody saw coming. Up-to-date digital books make tax returns faster, support any SARS verification, and give you numbers you can actually manage the business with.</p>",
   note("Your data stays yours", "Access is controlled per user, every change is recorded, and client information is handled with regard to POPIA.")]),
  faq=[("Do I have to use Taxnify?", "Taxnify is our recommended platform, but we can also work in a system you already use. We agree this in the engagement."),
       ("Can you take over books that are months behind?", "Yes. We first catch up and reconcile the history, then move to monthly processing and reporting.")]),
 dict(slug="expat-tax", send=["Your passport travel dates for the relevant tax years", "Foreign employment contracts and payslips", "Foreign tax returns or certificates of tax paid", "Your SARS tax number and recent assessments"], icon="globe", short="Foreign income & expat tax",
  title="Foreign income, expat and tax residency",
  seo="Expat Tax, Foreign Income &amp; Tax Residency SA | Think Mybiz",
  desc="South Africans working abroad: tax residency assessments, foreign employment income, foreign tax credits and ceasing residency with SARS. Free consultation.",
  card="Tax residency, foreign employment income, foreign tax credits and ceasing SA tax residency.",
  lead="South Africans who work or live abroad, and foreign nationals earning South African income, often face different tax rules. We assess your position and handle the SARS side.",
  body=lambda: "".join([
   "<h2>What we help with</h2>", ul([
    "Assessing whether you are a South African tax resident in a given year, based on your facts and the tests in the Income Tax Act.",
    "Declaring foreign employment income on your ITR12, and claiming the foreign employment income exemption where the requirements are met.",
    "Claiming credits for foreign tax paid, with the supporting certificates SARS requires.",
    "Ceasing South African tax residency: assessing the date, the tax consequences, and the notification to SARS.",
    "Non-residents with South African income, such as rental income, and their filing obligations.",
    "Coordination with specialists in the other country, or for complex treaty questions."]),
   "<h2>Why early advice helps</h2><p>Residency and foreign income rules turn on dates, days present and documents. Getting the position right before a return is filed, or before you leave, avoids estimated assessments, double taxation and penalties later.</p>",
   note("Specialist input where needed", "Foreign tax returns, treaty disputes and complex structures are coordinated with appropriately qualified specialists in the relevant country; responsibilities and any external fees are agreed first.")]),
  faq=[("I work overseas. Do I still need to file in South Africa?", "It depends on whether you are still a South African tax resident and on your income. We assess your residency and filing obligations from your travel dates and employment details."),
       ("Can I stop being a South African tax resident?", "Yes, ceasing residency is possible, but it has tax consequences on the date it happens. We assess the date and the consequences and handle the SARS notification.")]),
 dict(slug="internal-controls", send=["Your bank statements and access list for banking and eFiling", "Payroll reports for the last 2-3 months", "Your latest VAT201 and EMP201 submissions", "A short description of who does what in the finance function"], icon="shield", short="Internal controls review",
  title="SME internal controls and financial health review",
  seo="SME Internal Controls &amp; Financial Review | Think Mybiz",
  desc="A fixed-scope review of cash, banking, payroll, VAT and SARS controls for small businesses, with a prioritised list of fixes. Not an audit.",
  card="Fixed-scope check of cash, banking, payroll, VAT and SARS controls, with a prioritised list of fixes.",
  lead="A practical, fixed-scope review of how money and compliance are controlled in your business, followed by a short report of what to fix first.",
  body=lambda: "".join([
   "<h2>What we review</h2>", rows([
    ("Cash and banking", "Who can pay, approve and change bank details; reconciliation of bank accounts; petty cash."),
    ("Sales and debtors", "Invoicing, credit notes and follow-up of overdue accounts."),
    ("Purchases and creditors", "Supplier set-up, approvals and duplicate-payment risk."),
    ("Payroll", "Changes to employees and pay, approvals, and reconciliation to EMP201 and EMP501."),
    ("VAT and SARS", "VAT201 workings, eFiling access, outstanding returns and SARS account status."),
    ("Records", "Whether documents can be found quickly for a SARS verification or audit.")]),
   "<h2>What you receive</h2><p>A short written report with findings ranked by risk, practical fixes that suit the size of the business, and an offer to help implement them.</p>",
   note("Not an audit", "This review is an advisory engagement and does not result in an audit or review opinion. Statutory audits are performed by registered auditors and are referred where required.")]),
  faq=[("Is this the same as an audit?", "No. It is a practical advisory review of controls; it does not give an audit opinion. Where a statutory audit is required, we refer you to a registered auditor."),
       ("How long does it take?", "The scope and timing are agreed upfront in the quotation, based on the size of the business.")]),
]
SVC = {s["slug"]: s for s in SERVICES}

# ------------------------------------------------------------------ provinces and towns (contact form drop-downs)
# Main towns per province; every province also offers "Other town or village" with a free-text box.
TOWNS = {
 "Gauteng": ["Johannesburg", "Soweto", "Sandton", "Randburg", "Roodepoort", "Midrand", "Alexandra", "Diepsloot",
   "Pretoria", "Centurion", "Soshanguve", "Mabopane", "Ga-Rankuwa", "Mamelodi", "Atteridgeville", "Hammanskraal",
   "Cullinan", "Bronkhorstspruit", "Germiston", "Kempton Park", "Tembisa", "Benoni", "Boksburg", "Brakpan", "Springs",
   "Alberton", "Edenvale", "Krugersdorp", "Randfontein", "Vereeniging", "Vanderbijlpark", "Meyerton", "Heidelberg"],
 "Limpopo": ["Mokopane", "Mahwelereng", "Polokwane", "Seshego", "Mankweng", "Lebowakgomo", "Modimolle", "Mookgophong",
   "Bela-Bela", "Thabazimbi", "Northam", "Lephalale", "Senwabarwana", "Alldays", "Makhado (Louis Trichardt)", "Musina",
   "Thohoyandou", "Malamulele", "Elim", "Giyani", "Tzaneen", "Modjadjiskloof", "Phalaborwa", "Hoedspruit",
   "Burgersfort", "Jane Furse", "Groblersdal", "Marble Hall"],
 "Mpumalanga": ["Mbombela (Nelspruit)", "White River", "Hazyview", "KaNyamazane", "Barberton", "Malelane", "Komatipoort",
   "Bushbuckridge", "Sabie", "Mashishing (Lydenburg)", "eMalahleni (Witbank)", "Middelburg", "Secunda", "Ermelo",
   "Standerton", "Bethal", "eMkhondo (Piet Retief)", "Delmas", "KwaMhlanga", "Siyabuswa", "eMakhazeni (Belfast)", "Volksrust"],
 "North West": ["Rustenburg", "Phokeng", "Mogwase", "Brits", "Hartbeespoort", "Mahikeng", "Lichtenburg", "Zeerust",
   "Klerksdorp", "Potchefstroom", "Wolmaransstad", "Koster", "Vryburg", "Taung", "Christiana", "Bloemhof", "Schweizer-Reneke"],
 "KwaZulu-Natal": ["Durban", "Umhlanga", "Pinetown", "Umlazi", "Chatsworth", "Ballito", "KwaDukuza (Stanger)",
   "Pietermaritzburg", "Howick", "Richards Bay", "Empangeni", "Mtubatuba", "Eshowe", "Ulundi", "Newcastle", "Ladysmith",
   "Dundee", "Vryheid", "Estcourt", "Port Shepstone", "Margate", "Kokstad"],
 "Western Cape": ["Cape Town", "Bellville", "Khayelitsha", "Mitchells Plain", "Somerset West", "Stellenbosch", "Paarl",
   "Wellington", "Malmesbury", "Worcester", "Hermanus", "Swellendam", "George", "Mossel Bay", "Knysna", "Oudtshoorn",
   "Saldanha", "Vredenburg", "Beaufort West"],
 "Eastern Cape": ["Gqeberha (Port Elizabeth)", "Kariega (Uitenhage)", "Jeffreys Bay", "East London", "Qonce (King William's Town)",
   "Mthatha", "Butterworth", "Komani (Queenstown)", "Makhanda (Grahamstown)", "Port Alfred", "Graaff-Reinet", "Cradock",
   "Aliwal North", "Mount Frere", "Lusikisiki"],
 "Free State": ["Bloemfontein", "Botshabelo", "Thaba Nchu", "Welkom", "Virginia", "Odendaalsrus", "Kroonstad", "Bothaville",
   "Sasolburg", "Parys", "Bethlehem", "Harrismith", "Phuthaditjhaba (QwaQwa)", "Ficksburg"],
 "Northern Cape": ["Kimberley", "Upington", "Kakamas", "Kathu", "Kuruman", "Postmasburg", "Springbok", "De Aar",
   "Colesberg", "Calvinia"],
}
OTHER_TOWN = "Other town or village"
OUTSIDE_SA = "Outside South Africa"

AREAS = [("Gauteng", "Johannesburg, Pretoria (Tshwane), Midrand, Centurion, Sandton, Ekurhuleni and the West Rand"),
         ("Limpopo", "Mokopane (head office), Polokwane, Mookgophong, Modimolle, Bela-Bela, Lephalale, Thohoyandou and Tzaneen"),
         ("Mpumalanga", "Mbombela, eMalahleni, Middelburg, Secunda"),
         ("North West", "Rustenburg, Mahikeng, Brits, Klerksdorp, Potchefstroom"),
         ("KwaZulu-Natal", "Durban, Pietermaritzburg, Richards Bay, Newcastle"),
         ("Western Cape", "Cape Town, Stellenbosch, George, Paarl"),
         ("Eastern Cape", "Gqeberha, East London, Mthatha"),
         ("Free State", "Bloemfontein, Welkom, Bethlehem, Sasolburg"),
         ("Northern Cape", "Kimberley, Upington, Kathu")]

# ------------------------------------------------------------------ city pages (genuine, not copy-paste doorway pages)
CITIES = [
 dict(path="/accountants-pretoria/", short="Pretoria & Centurion", city="Pretoria", province="Gauteng",
  title="Accountants &amp; Tax Practitioners in Pretoria | Think Mybiz",
  desc="Remote accounting, tax returns, VAT, payroll, SARS disputes and tax debt help for Pretoria and Centurion businesses and individuals. Free consultation.",
  lead="Remote accounting and tax services for individuals and businesses in Pretoria, Centurion and the rest of Tshwane, from a SARS registered tax practitioner.",
  areas="Pretoria CBD, Centurion, Hatfield, Menlyn, Montana, Soshanguve, Mabopane, Ga-Rankuwa, Mamelodi, Atteridgeville, Hammanskraal and Bronkhorstspruit",
  focus=["sars-disputes", "tax-debt", "business-compliance"],
  body="Many Pretoria clients come to us for SARS matters: verification letters, disputed assessments and tax debt, as well as tax clearance and CSD requirements for government and tender work. Because the work is done remotely, you do not need to travel; documents are sent electronically and meetings happen by phone or video call."),
 dict(path="/accountants-johannesburg-midrand/", short="Johannesburg & Midrand", city="Johannesburg and Midrand", province="Gauteng",
  title="Accountants in Johannesburg &amp; Midrand | Think Mybiz",
  desc="Remote bookkeeping, tax returns, VAT, payroll and SARS dispute help for Johannesburg, Sandton and Midrand clients. SARS registered practitioner.",
  lead="Remote accounting, tax and SARS compliance for businesses and individuals in Johannesburg, Sandton, Midrand and the East and West Rand.",
  areas="Johannesburg CBD, Sandton, Randburg, Roodepoort, Midrand, Soweto, Alexandra, Kempton Park, Germiston, Benoni, Boksburg, Alberton and Krugersdorp",
  focus=["accounting", "vat", "payroll"],
  body="Johannesburg and Midrand businesses typically use us for monthly bookkeeping, VAT and payroll, so that the books, VAT201s and EMP201s stay reconciled every month and year-end work is quick. Individuals use us for ITR12 returns, rental income and SARS queries."),
 dict(path="/accountants-polokwane/", short="Polokwane", city="Polokwane", province="Limpopo",
  title="Accountants &amp; Tax Practitioners in Polokwane | Think Mybiz",
  desc="Accounting, tax returns, VAT, payroll, CIPC, CSD and SARS help for Polokwane, Seshego and Mankweng clients, from a Limpopo-based tax practitioner.",
  lead="Accounting, tax and compliance services for Polokwane, Seshego, Mankweng and the Capricorn district, from our Limpopo practice.",
  areas="Polokwane CBD, Seshego, Mankweng, Lebowakgomo, Moletji, Ga-Maja and the wider Capricorn district",
  focus=["business-compliance", "accounting", "business-tax"],
  body="Being based in Limpopo, we understand the businesses that work here: contractors, transport and security companies, retailers and suppliers to government and mining. We help them keep CIPC, SARS, CSD and tender compliance in order alongside their monthly books and tax returns."),
 dict(path="/accountants-mokopane/", short="Mokopane (head office)", city="Mokopane", province="Limpopo",
  title="Accountants &amp; Tax Practitioners in Mokopane | Think Mybiz",
  desc="Our head office: accounting, tax returns, VAT, payroll, SARS disputes, CIPC and tender compliance in Mokopane and Mahwelereng. Free consultation.",
  lead="Our head office is at No 48 Rabe Street, Havenga Building, Mokopane. We serve Mokopane, Mahwelereng and the Waterberg, in person by arrangement or remotely.",
  areas="Mokopane, Mahwelereng, Ga-Mashashane, Bakenberg, Mookgophong, Modimolle, Bela-Bela, Lephalale and the Mogalakwena villages",
  focus=["individual-tax", "business-compliance", "tax-debt"],
  body="As our home town, Mokopane is where clients can also meet us in person, by arrangement. Local clients use us for personal and company tax returns, CIPC registrations and annual returns, tax clearance PINs for tenders, and help with SARS debt and disputes."),
]

# ------------------------------------------------------------------ Insights articles (_src/articles/*.md)
# Front matter: title, seo_title (<=60), description (<=160), date (YYYY-MM-DD), updated (optional),
# service (slug of the related service page), sources (lines "- Title | URL"). Body: Markdown.
import glob, markdown as _md
import re as _re
def mobile_tables(h):
    """Wrap each article table and label every cell with its column heading, so phones can show rows as stacked cards."""
    def one(m):
        t = m.group(0)
        heads = [_re.sub(r"<[^>]+>", "", x).strip() for x in _re.findall(r"<th[^>]*>(.*?)</th>", t, _re.S)]
        def row(rm):
            i = [0]
            def cell(cm):
                lab = heads[i[0]] if i[0] < len(heads) else ""
                i[0] += 1
                return f'<td data-label="{html.escape(lab)}"' + cm.group(1) + ">"
            return _re.sub(r"<td([^>]*)>", cell, rm.group(0))
        t = _re.sub(r"<tr>.*?</tr>", row, t, flags=_re.S)
        return '<div class="table-wrap">' + t.replace("<table>", '<table class="stack">') + "</div>"
    return _re.sub(r"<table>.*?</table>", one, h, flags=_re.S)
def load_articles():
    arts = []
    for fp in sorted(f for f in glob.glob(os.path.join(OUT, "_src", "articles", "*.md")) if os.path.basename(f) not in ("README.md", "CALENDAR.md")):
        raw = open(fp, encoding="utf-8").read()
        assert raw.startswith("---"), fp
        _, fm, body = raw.split("---", 2)
        meta, sources, key = {}, [], None
        for line in fm.strip().splitlines():
            if line.startswith("  - ") or line.startswith("- "):
                t, u = [x.strip() for x in line.strip()[2:].split("|", 1)]
                sources.append((t, u))
            elif ":" in line:
                key, val = line.split(":", 1); meta[key.strip()] = val.strip().strip('"')
        meta["slug"] = os.path.splitext(os.path.basename(fp))[0]
        meta["path"] = f'/insights/{meta["slug"]}/'
        meta["sources"] = sources
        meta["html"] = mobile_tables(_md.markdown(body, extensions=["tables", "sane_lists"]))
        meta["words"] = len(body.split())
        assert meta.get("service") in SVC, f'{fp}: unknown service {meta.get("service")}'
        assert sources, f"{fp}: an article must list its official sources"
        if meta.get("status", "published") == "published": arts.append(meta)
    return sorted(arts, key=lambda a: a["date"], reverse=True)
ARTICLES = load_articles()
ARTICLE_DATES = {a["path"]: a.get("updated") or a["date"] for a in ARTICLES}
def nice_date(d):
    import datetime as _dt
    return _dt.date.fromisoformat(d).strftime("%-d %B %Y")
def article_cards(items):
    return "".join(f'<a class="card" href="{a["path"]}"><span class="num">{nice_date(a["date"])}</span><h3 style="margin-top:6px">{E(a["title"])}</h3>'
                   f'<p>{E(a["description"])}</p><span class="more">Read article →</span></a>' for a in items)
def related_articles(slug):
    rel = [a for a in ARTICLES if a["service"] == slug][:3]
    if not rel: return ""
    return "<h2>Related articles</h2><ul class=\"checks\">" + "".join(f'<li><a href="{a["path"]}">{E(a["title"])}</a></li>' for a in rel) + "</ul>"


# ------------------------------------------------------------------ industry pages
INDUSTRIES = [
 dict(slug="construction", icon="building", name="Construction &amp; property", seo="Accountants for Construction Companies | Think Mybiz",
  desc="Tax, VAT, payroll, CIDB and tender compliance for South African construction and property businesses. Remote service nationwide. Free consultation.",
  lead="Contractors and property businesses carry heavy compliance loads: tenders, CIDB, VAT on progress payments, site payroll and Compensation Fund returns. We keep it in order so you can keep building.",
  challenges=["Tax compliance status (TCS) PINs and CSD profiles that must be current for every tender",
              "CIDB registration and grading documentation that depends on up-to-date financial statements",
              "VAT on deposits, progress payments and retentions",
              "Weekly or fortnightly site payroll, with PAYE, UIF and SDL declared correctly",
              "Compensation Fund registration and annual returns of earnings",
              "Rental income and property disposals for property owners"],
  focus=["business-compliance", "payroll", "vat", "accounting", "tax-planning", "internal-controls"]),
 dict(slug="security", icon="shield", name="Security services", seo="Accountants for Security Companies | Think Mybiz",
  desc="Payroll, EMP201/EMP501, UIF, Compensation Fund, PSIRA and tender compliance for security companies in South Africa. Free consultation.",
  lead="Security companies are payroll-heavy and tender-driven. Errors in PAYE, UIF or EMP501 reconciliations, or an expired tax clearance PIN, can cost contracts. We keep both sides compliant.",
  challenges=["Large payrolls with shifts, overtime and allowances, declared correctly on EMP201",
              "Bi-annual EMP501 reconciliations that must match payroll, payments and IRP5s",
              "UIF declarations and Compensation Fund returns of earnings",
              "PSIRA-related documentation and letters of good standing for contracts",
              "Tax clearance PINs and CSD registration for government and corporate tenders",
              "Cash flow between contract payments and monthly wage runs"],
  focus=["payroll", "business-compliance", "tax-debt", "accounting", "internal-controls", "business-advisory"]),
 dict(slug="transport-logistics", icon="compass", name="Transport &amp; logistics", seo="Accountants for Transport &amp; Logistics | Think Mybiz",
  desc="Bookkeeping, VAT, fleet asset registers, driver payroll and tax for South African transport and logistics businesses. Free consultation.",
  lead="Transport businesses run on thin margins and large fleets. We keep fleet costs, finance agreements, VAT and driver payroll accurate so the numbers show which routes and vehicles actually make money.",
  challenges=["Fixed-asset registers and wear-and-tear allowances for vehicles and trailers",
              "VAT on vehicles and fuel, where the rules differ by vehicle type and use",
              "Instalment sale and lease agreements recorded correctly",
              "Driver payroll, allowances and subsistence, declared correctly",
              "Tax clearance and CSD requirements for contract work",
              "Cash-flow planning around fuel, maintenance and finance payments"],
  focus=["accounting", "vat", "payroll", "business-tax", "digital-finance", "business-advisory"]),
 dict(slug="nonprofits", icon="users", name="Nonprofits &amp; schools", seo="Accountants for NPOs, NPCs &amp; Schools | Think Mybiz",
  desc="Financial statements, payroll, PBO and section 18A applications and tax compliance for nonprofits, NPCs and independent schools. Free consultation.",
  lead="Nonprofit organisations, nonprofit companies and independent schools must account to funders, members and regulators. We prepare reliable financial statements and handle the tax side.",
  challenges=["Annual financial statements that funders, boards and members can rely on",
              "The difference between nonprofit or CIPC registration and SARS tax-exempt (PBO) approval",
              "Public benefit organisation and section 18A applications where the organisation qualifies",
              "Payroll for staff and educators, with PAYE, UIF and SDL obligations",
              "Ongoing filing and record requirements after exemption is granted",
              "Grant and project tracking alongside the general ledger"],
  focus=["accounting", "business-tax", "payroll", "digital-finance", "internal-controls", "business-compliance"]),
]
# ------------------------------------------------------------------ chrome
NAV = [("Services", "/services/"), ("SARS disputes", "/services/sars-disputes/"), ("Tax debt", "/services/tax-debt/"),
       ("About", "/about/"), ("How we work", "/how-we-work/"), ("Service areas", "/service-areas/")]
if ARTICLES: NAV.insert(3, ("Insights", "/insights/"))
LOGO = '<picture><source srcset="/assets/img/logo.webp" type="image/webp"><img src="/assets/img/logo.png" alt="Think Mybiz Accountants &amp; Tax Practitioners" width="640" height="190"></picture>'

def wa_link(text="Hi Think Mybiz, I would like assistance with "):
    return f"https://wa.me/{WHATSAPP}?text=" + text.replace(" ", "%20") if WHATSAPP else ""

def header(active):
    items = "".join(f'<li><a href="{u}"{" class=active" if u == active else ""}>{t}</a></li>' for t, u in NAV)
    return f"""<a class="skip" href="#main">Skip to content</a>
<div class="topbar"><div class="container"><div class="tb-l"><a href="tel:{TEL}">{svg_inline("phone")} {PHONE}</a><a href="mailto:{EMAIL}">{EMAIL}</a></div>
<div class="tb-r">Remote accounting &amp; tax services in all nine provinces · SARS practitioner {PR}</div></div></div>
<header class="site-header"><div class="container nav">
<a class="brand" href="/" aria-label="{BRAND} home">{LOGO}</a>
<button class="menu-toggle" aria-label="Open menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
<ul class="nav-links">{items}<li class="nav-cta"><a class="btn btn-sm" href="/contact/">Free consultation</a></li></ul>
</div></header>"""

def svg_inline(n): return f'<svg style="width:14px;height:14px;display:inline;vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{I[n]}</svg>'

def footer():
    svc = "".join(f'<li><a href="/services/{s["slug"]}/">{s["short"]}</a></li>' for s in SERVICES[:6])
    svc2 = "".join(f'<li><a href="/services/{s["slug"]}/">{s["short"]}</a></li>' for s in SERVICES[6:])
    wa = (f'<li><a href="{wa_link()}">WhatsApp {WA_DISPLAY}</a></li>' if WHATSAPP else "") + f'<li><a href="tel:{MTEL}">Cell {MOBILE}</a></li>'
    return f"""<footer class="site-footer"><div class="container"><div class="footer-grid">
<div><a class="footer-logo" href="/">{LOGO}</a>
<p style="margin-top:16px;max-width:330px">Accounting, tax, payroll and SARS compliance for businesses and individuals anywhere in South Africa. Accountable. Reliable. Compliant.</p>
<p style="margin-top:10px">SARS registered tax practitioner {PR}</p>{social_links()}</div>
<div><h4>Tax services</h4><ul>{svc}</ul></div>
<div><h4>Business services</h4><ul>{svc2}<li><a href="/sars-letter/">Received a SARS letter?</a></li><li><a href="/industries/">Industries we serve</a></li></ul></div>
<div><h4>Contact</h4><ul><li><a href="tel:{TEL}">{PHONE}</a></li>{wa}<li><a href="mailto:{EMAIL}">{EMAIL}</a></li><li>{ADDRESS}</li><li><a href="/service-areas/">Serving all nine provinces</a></li>{"".join(f'<li><a href="{c["path"]}">{c["short"]}</a></li>' for c in CITIES)}</ul></div>
</div>
<div class="footer-bottom"><span>© <span id="year">2026</span> {BRAND}. {E(LEGAL)}, reg. no. 2021/819201/07.</span>
<span><a href="/privacy/">Privacy &amp; POPIA</a> · <a href="/contact/">Contact</a></span></div></div></footer>
<div class="mbar"><a href="tel:{TEL}">Call {PHONE}</a>{f'<a href="{wa_link()}">WhatsApp us</a>' if WHATSAPP else '<a href="/contact/">Free consultation</a>'}</div>
<script src="/assets/js/main.js?v={ver("assets/js/main.js")}" defer></script>"""

ORG = {
 "@context": "https://schema.org", "@type": "AccountingService", "@id": DOMAIN + "/#practice",
 "name": BRAND_TXT, "legalName": LEGAL, "url": DOMAIN + "/", "logo": DOMAIN + "/assets/img/logo.png",
 "image": DOMAIN + "/assets/img/og.png", "email": EMAIL, "telephone": TEL,
 "address": {"@type": "PostalAddress", "streetAddress": "No 48 Rabe Street, Havenga Building", "addressLocality": "Mokopane",
             "addressRegion": "Limpopo", "postalCode": "0600", "addressCountry": "ZA"},
 "areaServed": {"@type": "Country", "name": "South Africa"},
 "founder": {"@id": DOMAIN + "/about/#mf-banda"}, "foundingDate": "2021-08-18", "vatID": "4010320630",
 "slogan": "Accountable. Reliable. Compliant. Focused on your growth.",
 "priceRange": "Quoted per engagement",
 "knowsAbout": ["Income tax", "VAT", "Payroll", "SARS dispute resolution", "Tax debt", "Annual financial statements", "CIPC compliance"],
}

if any(SOCIAL.values()): ORG["sameAs"] = [u for u in SOCIAL.values() if u]
if OPENING_HOURS: ORG["openingHours"] = OPENING_HOURS
PERSON = {"@context": "https://schema.org", "@type": "Person", "@id": DOMAIN + "/about/#mf-banda", "name": "MF Banda",
  "jobTitle": "Director and SARS registered tax practitioner", "worksFor": {"@id": DOMAIN + "/#practice"},
  "hasCredential": [
    {"@type": "EducationalOccupationalCredential", "credentialCategory": "Registered tax practitioner", "name": "SARS registered tax practitioner " + PR,
     "recognizedBy": {"@type": "GovernmentOrganization", "name": "South African Revenue Service", "url": "https://www.sars.gov.za/"}},
    {"@type": "EducationalOccupationalCredential", "credentialCategory": "Professional designation", "name": "Tax Technician (SA)",
     "recognizedBy": {"@type": "Organization", "name": "South African Institute of Taxation", "url": "https://www.thesait.org.za/"}},
    {"@type": "EducationalOccupationalCredential", "credentialCategory": "Professional designation", "name": "Chartered Business Accountant in Commerce",
     "recognizedBy": {"@type": "Organization", "name": "CIBA", "url": "https://www.myciba.org/"}},
    {"@type": "EducationalOccupationalCredential", "credentialCategory": "Professional designation", "name": "Certified Financial Accountant",
     "recognizedBy": {"@type": "Organization", "name": "ICBA"}}],
  "knowsAbout": ["South African income tax", "VAT", "SARS dispute resolution", "Tax debt", "Payroll taxes"]}

def analytics_head():
    if not GA_ID: return ""
    return (f'<script>window.dataLayer=window.dataLayer||[];function gtag(){{dataLayer.push(arguments)}}'
            f'gtag("consent","default",{{analytics_storage:"denied",ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied"}});'
            f'try{{if(localStorage.getItem("tmb_consent")==="yes")gtag("consent","update",{{analytics_storage:"granted"}})}}catch(e){{}}'
            f'gtag("js",new Date());gtag("config","{GA_ID}");</script>'
            f'<script async src="https://www.googletagmanager.com/gtag/js?id={GA_ID}"></script>')
def consent_banner():
    if not GA_ID: return ""
    return ('<div class="consent-bar" hidden><p>We use Google Analytics cookies to understand how visitors use this site, only if you agree. '
            '<a href="/privacy/">Privacy notice</a></p><div><button class="btn btn-sm btn-outline" data-consent="no">Decline</button>'
            '<button class="btn btn-sm btn-primary" data-consent="yes">Accept</button></div></div>')

PAGES_HASH = {}
def page(path, title, desc, body, active="", schema=None, noindex=False, og_type="website", head_extra=""):
    tl, dl = len(html.unescape(title)), len(html.unescape(desc))
    assert tl <= 60 and (dl <= 160 or noindex), f"{path}: title {tl} chars / description {dl} chars (max 60 / 160)"
    canon = DOMAIN + path
    ld = [ORG] + (schema or [])
    lds = "".join(f'<script type="application/ld+json">{json.dumps(s, ensure_ascii=False)}</script>' for s in ld)
    out = f"""<!DOCTYPE html><html lang="en-ZA"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title}</title><meta name="description" content="{desc}">
<link rel="canonical" href="{canon}">{'<meta name="robots" content="noindex">' if noindex else ''}
<meta property="og:type" content="{og_type}"><meta property="og:title" content="{title}"><meta property="og:description" content="{desc}">
<meta property="og:url" content="{canon}"><meta property="og:site_name" content="{BRAND}"><meta property="og:image" content="{DOMAIN}/assets/img/og.png">
<meta property="og:locale" content="en_ZA"><meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#001a40">{f'<meta name="google-site-verification" content="{E(GSC_VERIFY)}">' if GSC_VERIFY else ""}{head_extra}
<link rel="icon" href="/assets/img/favicon.png" type="image/png"><link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v={ver("assets/css/style.css")}">{lds}{analytics_head()}
<link rel="alternate" type="application/rss+xml" title="Think Mybiz Insights" href="/insights/feed.xml">
</head><body>{header(active)}<main id="main">{body}</main>{footer()}{consent_banner()}</body></html>"""
    fp = os.path.join(OUT, path.strip("/"), "index.html") if path.endswith("/") else os.path.join(OUT, path.strip("/"))
    os.makedirs(os.path.dirname(fp), exist_ok=True)
    open(fp, "w", encoding="utf-8").write(out)
    if not noindex:
        SITEMAP.append(path); PAGES_HASH[path] = hashlib.md5((title + desc + body).encode()).hexdigest()

SITEMAP = []
def ver(rel):  # cache-busting version from file content, so CDN/browser caches pick up CSS/JS changes at once
    return hashlib.md5(open(os.path.join(OUT, rel), "rb").read()).hexdigest()[:8]

def hero(eyebrow, h1, lead, crumbs=None, actions=True, cls="hero page-hero"):
    c = ""
    if crumbs: c = '<div class="crumbs">' + " / ".join(f'<a href="{u}">{t}</a>' if u else t for t, u in crumbs) + "</div>"
    a = (f'<div class="hero-actions"><a class="btn btn-primary" href="/contact/">Book a free consultation</a>'
         f'<a class="btn btn-ghost" href="tel:{TEL}">{svg("phone")} Call {PHONE}</a></div>') if actions else ""
    return f'<section class="{cls}"><div class="container">{c}<span class="eyebrow light">{eyebrow}</span><h1>{h1}</h1><p class="lead">{lead}</p>{a}</div></section>'

def cta(h="Let's get your tax and compliance in order", p="Tell us what you need, or send us the SARS notice you have received. We will arrange a free consultation and reply with an agreed scope and quotation."):
    wa = f'<a class="btn btn-ghost" href="{wa_link()}">WhatsApp us</a>' if WHATSAPP else f'<a class="btn btn-ghost" href="mailto:{EMAIL}">Email {EMAIL}</a>'
    return f"""<section class="section"><div class="container"><div class="cta"><h2>{h}</h2><p>{p}</p>
<div class="hero-actions"><a class="btn btn-primary" href="/contact/">Book a free consultation</a><a class="btn btn-ghost" href="tel:{TEL}">Call {PHONE}</a>{wa}</div></div></div></section>"""

EXTRA = ('<a class="card" href="/sars-letter/" style="background:var(--green-50);border-color:#bfe5cb">'+'{i}<h3>Received a SARS letter?</h3><p>Verification, audit, assessment, penalty or final demand: what to send us and what happens next.</p><span class="more">What to do now →</span></a>'
         '<a class="card" href="/contact/" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec"><h3 style="color:#fff">Not sure what you need?</h3><p>Book a free consultation. We review your position, registrations and urgent dates, then agree a written scope.</p><span class="more" style="color:#5fd486">Book a free consultation →</span></a>')
def svc_cards(items=SERVICES, extra=False):
    return svc_cards_(items) + (EXTRA.replace("{i}", icon("alert")) if extra else "")
def svc_cards_(items):
    return "".join(f'<a class="card" href="/services/{s["slug"]}/">{icon(s["icon"])}<h3>{s["short"]}</h3><p>{s["card"]}</p><span class="more">Learn more →</span></a>' for s in items)

def faq_html(faq):
    return '<div class="faq">' + "".join(f"<details><summary>{q}</summary><p>{a}</p></details>" for q, a in faq) + "</div>"
def faq_schema(faq):
    return {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
        {"@type": "Question", "name": html.unescape(q), "acceptedAnswer": {"@type": "Answer", "text": html.unescape(a)}} for q, a in faq]}
def crumbs_schema(items):
    return {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "name": html.unescape(t), "item": DOMAIN + u} for i, (t, u) in enumerate(items)]}

TRUST = "".join(f'<span class="pill">{t}</span>' for t in [f"SARS registered tax practitioner {PR}", "SAIT Tax Technician (SA)", "CIBA · ICBA", "POPIA-aware", "All nine provinces"])

# ------------------------------------------------------------------ HOME
GEN_FAQ = [
 ("Do you only work with clients in Limpopo?", "No. Our head office is in Mokopane, Limpopo, but we serve individuals and businesses in all nine provinces, including Johannesburg, Pretoria and Midrand, through remote service. Office consultations are available by arrangement."),
 ("How do I send you my documents?", "Electronically. We agree a secure route for sensitive documents, file them in a dedicated client folder per financial year, and meet by phone or video call."),
 ("How much do your services cost?", "Fees depend on the work. After a free consultation we send a written quotation that identifies the deliverables, exclusions, responsibilities and fees before any work starts."),
 ("Who will do my work?", "Work is prepared and reviewed under MF Banda, director and SARS registered tax practitioner (PR-0034776). Nothing is submitted without your approval."),
 ("I received a letter from SARS. What should I send you?", "Send the full notice, the assessment date, the relevant tax type and period, and any existing case reference. Ask us for a secure route before sending sensitive supporting documents."),
]
home = f"""<section class="hero"><div class="container hero-grid"><div>
<span class="eyebrow light">Accountants &amp; tax practitioners · South Africa</span>
<h1>Accounting, tax and SARS compliance you can rely on, <em>anywhere in South Africa.</em></h1>
<p class="lead">We support South African businesses and individuals with accounting, taxation, payroll and statutory compliance, and assist taxpayers facing SARS queries, disputed assessments and outstanding tax debt.</p>
<div class="hero-actions"><a class="btn btn-primary" href="/contact/">Book a free consultation</a><a class="btn btn-ghost" href="/sars-letter/">Received a SARS letter?</a></div>
<div class="trust">{TRUST}</div></div>
<div class="hero-card"><h3>How we can help</h3><ul>
<li>{svg("check")}<span>Tax returns for individuals, companies and trusts</span></li>
<li>{svg("check")}<span>VAT, payroll and employer tax compliance</span></li>
<li>{svg("check")}<span>SARS audits, objections, appeals and ADR</span></li>
<li>{svg("check")}<span>Tax debt: payment arrangements, suspension and compromise</span></li>
<li>{svg("check")}<span>Bookkeeping and annual financial statements</span></li>
<li>{svg("check")}<span>CIPC, CSD, tenders, licences and business advice</span></li></ul>
<a class="btn btn-primary" style="margin-top:16px;width:100%;justify-content:center" href="tel:{TEL}">{svg("phone")} Call {PHONE}</a>{f'<a class="btn btn-ghost" style="margin-top:10px;width:100%;justify-content:center" href="{wa_link()}">WhatsApp {WA_DISPLAY}</a>' if WHATSAPP else ''}</div>
</div></section>

<section class="section"><div class="container"><div class="section-head"><span class="eyebrow">What we do</span><h2>Accounting and tax services for every stage</h2>
<p class="lead">From your first tax registration to a dispute with SARS: one practice that connects your books, tax returns and supporting evidence, so each submission has a clear basis.</p></div>
<div class="grid g3">{svc_cards(extra=True)}</div></div></section>

<section class="section alt"><div class="container"><div class="band"><div><span class="eyebrow light">SARS disputes &amp; tax debt</span>
<h2 style="margin:.35em 0 .45em">Received an assessment, audit letter or final demand?</h2>
<p>A disputed assessment requires the correct procedure, supporting evidence and careful deadline management. We review the facts and the available remedy before preparing a response, from a request for correction or an objection to payment arrangements and suspension of payment.</p></div>
<div><ul class="checks dark-list" style="color:#e2e9f8"><li>Verification and audit responses</li><li>Notices of objection and appeal, ADR</li><li>Penalty and interest remission</li><li>Payment arrangements and compromise</li><li>Voluntary disclosure (VDP) and Tax Ombud</li></ul>
<a class="btn btn-primary" href="/sars-letter/" style="margin-top:14px">What to do now</a></div></div></div></section>

<section class="section"><div class="container"><div class="grid g2" style="gap:48px;align-items:center">
<div><span class="eyebrow">Why Think Mybiz</span><h2 style="margin:.35em 0 .5em">Practitioner-led, professionally accountable</h2>
<p>The practice is led by MF Banda, director and SARS registered tax practitioner. His professional background includes Tax Technician (SA), Chartered Business Accountant in Commerce and Certified Financial Accountant designations, and an Occupational Certificate in Tax Technician at NQF Level 6.</p>
<p>Our purpose is to make accounting and tax compliance understandable and manageable. We explain what is required, agree the scope and fees, and keep records of the work completed.</p>
<a class="btn btn-outline" href="/about/" style="margin-top:18px">About the practice</a></div>
<div class="grid g2">
<div class="card">{icon("shield")}<h3>Registered practitioner</h3><p>Work prepared and reviewed under SARS practitioner {PR}.</p></div>
<div class="card">{icon("book")}<h3>Books and tax connected</h3><p>Every submission tied to reconciled records and evidence.</p></div>
<div class="card">{icon("clipboard")}<h3>Clear scope and fees</h3><p>A written quotation before any work starts.</p></div>
<div class="card">{icon("lock")}<h3>Confidential</h3><p>Information handled with regard to POPIA.</p></div></div></div></div></section>

<section class="section alt"><div class="container"><div class="section-head center"><span class="eyebrow">How we work</span><h2>From first call to proof of submission</h2></div>
<div class="grid g5">{"".join(f'<div class="card step"><span class="num">{i:02d}</span><h3>{a}</h3><p>{b}</p></div>' for i,(a,b) in enumerate([("Understand the position","Initial consultation, taxpayer and entity checks, registrations and urgent dates."),("Agree the engagement","Written scope, deliverables, fees, responsibilities and timing."),("Collect the evidence","Identity and entity records, tax notices and accounting documents."),("Prepare and review","Reconcile, prepare, resolve queries and obtain your approval."),("Submit and follow through","Submit, keep acknowledgements, communicate outcomes.")],1))}</div></div></section>

<section class="section"><div class="container"><div class="grid g2" style="gap:48px;align-items:start">
<div><span class="eyebrow">Anywhere in South Africa</span><h2 style="margin:.35em 0 .5em">Remote service in all nine provinces</h2>
<p>Based in Mokopane, Limpopo, we work with clients throughout South Africa, including Johannesburg, Pretoria and Midrand. Documents are sent electronically, meetings happen by phone or video call, and office consultations are available by arrangement.</p>
<div class="chips" style="margin-top:18px">{"".join(f'<span class="chip">{a}</span>' for a,_ in AREAS)}</div>
<a class="btn btn-outline" href="/service-areas/" style="margin-top:20px">Service areas</a></div>
<div><span class="eyebrow">Common questions</span><h2 style="margin:.35em 0 .6em">Frequently asked</h2>{faq_html(GEN_FAQ)}</div></div></div></section>
{('<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Insights</span><h2>Latest guidance</h2></div><div class="grid g3">' + article_cards(ARTICLES[:3]) + '</div><p style="margin-top:20px"><a href="/insights/">All insights →</a></p></div></section>') if ARTICLES else ""}
{cta()}"""
page("/", "Accountants &amp; Tax Practitioners South Africa | Think Mybiz",
     "SARS registered tax practitioner: tax returns, VAT, payroll, SARS disputes, tax debt and CIPC for clients anywhere in South Africa. Free consultation.",
     home, "/", [faq_schema(GEN_FAQ), {"@context": "https://schema.org", "@type": "WebSite", "name": BRAND_TXT, "url": DOMAIN + "/"}])

# ------------------------------------------------------------------ SERVICES HUB
hub = hero("Our services", "Accounting, tax and compliance services",
  "Services are available through monthly retainers, once-off assignments or separate dispute and recovery engagements, for individuals, start-ups and established businesses anywhere in South Africa.",
  [("Home", "/"), ("Services", None)]) + f"""
<section class="section"><div class="container"><div class="grid g3">{svc_cards(extra=True)}</div></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Ways to engage us</span><h2>An engagement that fits the work</h2></div>
<div class="grid g3">
<div class="card"><h3>Monthly retainer</h3><p>Ongoing bookkeeping, tax, VAT, payroll and compliance for an agreed monthly fee.</p></div>
<div class="card"><h3>Once-off assignment</h3><p>A return, a set of financial statements, a registration or a catch-up project.</p></div>
<div class="card"><h3>Dispute and recovery engagement</h3><p>A defined mandate for a SARS dispute, tax debt or compliance recovery matter.</p></div></div>
{note("A defined engagement", "The quotation identifies deliverables, exclusions, responsibilities and fees; external approval and third-party charges are addressed separately.")}</div></section>
<section class="section"><div class="container"><div class="section-head"><span class="eyebrow">Who we serve</span><h2>Clients and sectors</h2>
<p class="lead">We serve individuals, start-ups and established small and medium businesses, including companies, close corporations, sole proprietors and trusts.</p></div>
<div class="grid g4">{"".join(f'<a class="card" href="/industries/{i["slug"]}/">{icon(i["icon"])}<h3>{i["name"]}</h3><span class="more">View →</span></a>' for i in INDUSTRIES)}</div>
<div class="chips" style="margin-top:20px">{"".join(f'<span class="chip">{s}</span>' for s in ["Retail","Hospitality","Professional services","Employees &amp; directors","Landlords","Independent contractors","South Africans working abroad"])}</div></div></section>
{cta()}"""
page("/services/", "Accounting &amp; Tax Services in South Africa | Think Mybiz",
     "Tax returns, VAT, payroll, SARS disputes, tax debt, accounting, CIPC, licences, tenders and business advice for clients anywhere in South Africa.",
     hub, "/services/", [crumbs_schema([("Home", "/"), ("Services", "/services/")])])

# ------------------------------------------------------------------ SERVICE PAGES
for s in SERVICES:
    path = f'/services/{s["slug"]}/'
    side = "".join(f'<li><a href="/services/{x["slug"]}/"{" class=active" if x is s else ""}>{x["short"]}</a></li>' for x in SERVICES)
    body = hero("Services", s["title"], s["lead"], [("Home", "/"), ("Services", "/services/"), (s["short"], None)]) + f"""
<section class="section"><div class="container two-col"><div class="content">{s["body"]()}
<h2>What to send us</h2><p>To get started, these are usually enough. We will tell you if anything else is needed.</p>{ul([E(x) for x in s["send"]])}
{related_articles(s["slug"])}<h2>Frequently asked questions</h2>{faq_html(s["faq"])}</div>
<aside class="aside"><div class="card" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec">
<h3 style="color:#fff">Talk to a tax practitioner</h3><p style="font-size:.93rem">Free initial consultation. Remote service anywhere in South Africa.</p>
<a class="btn btn-primary" href="/contact/?service={s["slug"]}" style="margin-top:14px;width:100%;justify-content:center">Book a consultation</a>
<a class="btn btn-ghost" href="tel:{TEL}" style="margin-top:10px;width:100%;justify-content:center">Call {PHONE}</a></div>
<div class="card"><h3>All services</h3><ul>{side}</ul></div></aside></div></section>{cta()}"""
    page(path, s["seo"], s["desc"], body, "/services/" if s["slug"] not in ("sars-disputes", "tax-debt") else path,
         [faq_schema(s["faq"]), crumbs_schema([("Home", "/"), ("Services", "/services/"), (s["short"], path)]),
          {"@context": "https://schema.org", "@type": "Service", "name": html.unescape(s["title"]), "description": html.unescape(s["desc"]),
           "provider": {"@id": DOMAIN + "/#practice"}, "areaServed": {"@type": "Country", "name": "South Africa"}}])

# ------------------------------------------------------------------ SARS LETTER (landing page)
letter = hero("SARS notices", "Received a letter from SARS? Here is what to do.",
  "Verification requests, audit letters, assessments, penalties and final demands all have their own procedures and deadlines. Acting early keeps your options open.",
  [("Home", "/"), ("Received a SARS letter?", None)]) + f"""
<section class="section"><div class="container two-col"><div class="content">
<h2>1. Send us the full notice straight away</h2>
<p>Send the complete notice, the date you received it, the relevant tax type and period, and any existing case reference. Ask us for a secure route before sending sensitive supporting documents.</p>
<h2>2. We identify what SARS is asking and the deadline</h2>
<p>We review SARS notices, returns, accounts and supporting records, identify the disputed issues and the appropriate procedural route. Each case has its own filing and escalation deadlines.</p>
<h2>3. We prepare the right response</h2>
{rows([("Verification or audit letter", "We prepare evidence packs, reconcile figures and respond to SARS information requests and proposed findings."),
       ("Assessment you disagree with", "A Request for Correction where available, a request for reasons, or a Notice of Objection with grounds and evidence; an appeal or ADR where there is a basis."),
       ("Penalties and interest", "Requests for remission where legislation permits."),
       ("Debt or final demand", "We check the balance, then prepare a payment arrangement, a section 164 suspension request or a compromise application where the criteria may be met."),
       ("Delayed refund or unresolved query", "Account queries, SARS complaints escalation and Tax Ombud submissions where the requirements are met.")])}
{note("Important", "An objection or appeal does not by itself suspend payment; suspension must be considered separately. Payment terms, suspension, remission, compromise and VDP relief are subject to eligibility and SARS approval. We do not promise debt write-offs or a particular outcome.")}
</div><aside class="aside"><div class="card" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec">
<h3 style="color:#fff">Send us your SARS notice</h3><p style="font-size:.93rem">We will review it and tell you the route and the deadline.</p>
<a class="btn btn-primary" href="/contact/?service=sars-disputes" style="margin-top:14px;width:100%;justify-content:center">Get help with a SARS notice</a>
<a class="btn btn-ghost" href="tel:{TEL}" style="margin-top:10px;width:100%;justify-content:center">Call {PHONE}</a></div>
<div class="card"><h3>Related services</h3><ul><li><a href="/services/sars-disputes/">SARS disputes &amp; resolution</a></li><li><a href="/services/tax-debt/">Tax debt &amp; compliance recovery</a></li><li><a href="/services/vat/">VAT verifications</a></li><li><a href="/services/payroll/">Employer tax queries</a></li></ul></div></aside></div></section>{cta("Don't let a SARS deadline pass", "Send us the notice today. We review the facts and the available remedy before preparing a response.")}"""
page("/sars-letter/", "Received a SARS Letter or Final Demand? | Think Mybiz",
     "SARS verification, audit, assessment, penalty or final demand? What to send us, the deadlines that matter and how a registered practitioner responds.",
     letter, "", [crumbs_schema([("Home", "/"), ("Received a SARS letter?", "/sars-letter/")])])

# ------------------------------------------------------------------ ABOUT
about = hero("About us", "Our practice",
  "We help clients maintain reliable financial records, understand their tax obligations and respond properly when compliance problems arise.",
  [("Home", "/"), ("About", None)]) + f"""
<section class="section"><div class="container two-col"><div class="content">
<h2>Who we are</h2><p>{BRAND} supports South African businesses and individuals with accounting, taxation, payroll and statutory compliance. We also assist taxpayers facing SARS queries, disputed assessments and outstanding tax debt. Our engagements connect the books, tax returns and supporting evidence so that each submission has a clear basis.</p>
<h2>Leadership and professional background</h2><p>The practice is led by MF Banda, director and registered tax practitioner. His professional background includes Tax Technician (SA), Chartered Business Accountant in Commerce and Certified Financial Accountant designations, and an Occupational Certificate in Tax Technician at NQF Level 6.</p>
{rows([("SARS practitioner number", PR), ("SAIT membership number", "55086416"), ("Professional bodies", "South African Institute of Taxation; CIBA; ICBA"),
       ("Registered company", E(LEGAL)), ("Company registration", "2021/819201/07"), ("Incorporation date", "18 August 2021"), ("VAT registration", "4010320630")])}
<p style="font-size:.88rem;color:var(--muted);margin-top:10px">The registered company name above retains the CIPC spelling. Our client-facing brand is Think Mybiz Accountants and Tax Practitioners.</p>
<h2>Our purpose and approach</h2><p>Our purpose is to make accounting and tax compliance understandable and manageable. We explain what is required, agree the scope and fees, and keep records of the work completed. We aim to build lasting client relationships through accurate work, confidentiality and direct communication.</p>
<h2>Digital service with practitioner oversight</h2><p>Taxnify is our base accounting and compliance software platform. Electronic records and remote meetings support our service, while professional judgement, review and client authorisation remain central to each engagement.</p>
<h2>Serving clients throughout South Africa</h2><p>Based in Mokopane, Limpopo, with remote services available in all nine provinces, including <a href="/accountants-johannesburg-midrand/">Johannesburg and Midrand</a>, <a href="/accountants-pretoria/">Pretoria</a> and <a href="/accountants-polokwane/">Polokwane</a>. See <a href="/how-we-work/">how we work</a>, our <a href="/services/">services</a>, or what to do if you have <a href="/sars-letter/">received a SARS letter</a>.</p>
</div><aside class="aside"><div class="card"><h3>At a glance</h3><ul class="checks" style="margin-top:8px">
<li>SARS registered tax practitioner {PR}</li><li>SAIT Tax Technician (SA)</li><li>Chartered Business Accountant in Commerce (CIBA)</li><li>Certified Financial Accountant (ICBA)</li><li>Company incorporated 2021</li><li>Remote service in all nine provinces</li></ul>
<a class="btn btn-primary" href="/contact/" style="margin-top:12px;width:100%;justify-content:center">Book a free consultation</a></div></aside></div></section>{cta()}"""
page("/about/", "About Think Mybiz | MF Banda, SARS Tax Practitioner",
     "Led by MF Banda, SARS registered tax practitioner (PR-0034776) and SAIT Tax Technician (SA), serving clients across South Africa from Mokopane.",
     about, "/about/", [PERSON, crumbs_schema([("Home", "/"), ("About", "/about/")])])

# ------------------------------------------------------------------ HOW WE WORK
how = hero("How we work", "Working with us",
  "Start with your current needs, your latest SARS or compliance notices and a clear view of what is outstanding. We will assess the work and provide an agreed scope.",
  [("Home", "/"), ("How we work", None)]) + f"""
<section class="section"><div class="container"><div class="grid g5">{"".join(f'<div class="card step"><span class="num">{i:02d}</span><h3>{a}</h3><p>{b}</p></div>' for i,(a,b) in enumerate([
 ("Understand your needs","Free consultation, taxpayer and entity checks, review of registrations, records available and any urgent dates."),
 ("Receive a quotation","A written quotation setting out the scope, deliverables, fee, deposit and how long the quotation remains valid."),
 ("Engagement and deposit","You accept the engagement letter and pay a 50% deposit. Work begins once the deposit has been received."),
 ("Prepare and review","We collect the evidence, reconcile the records and prepare the work. The practitioner reviews it and obtains your approval."),
 ("Settle and deliver","The balance is invoiced on completion. Final statements and reports are released, and returns submitted, once paid in full.")],1))}</div></div></section>
<section class="section alt"><div class="container grid g2" style="gap:40px">
<div><span class="eyebrow">Ways to engage us</span><h2 style="margin:.35em 0 .6em">Retainer, once-off or dispute matter</h2>
<p>Services are available through monthly retainers, once-off assignments or separate dispute and recovery engagements. The quotation identifies deliverables, exclusions, responsibilities and fees; external approval and third-party charges are addressed separately.</p>
<p><strong>Fees and payment.</strong> Once-off work starts after a 50% deposit, and the balance is payable on completion before final delivery. Monthly retainers are payable in advance for each month. Where a SARS or CIPC deadline applies, we confirm in writing the date by which payment is needed to meet it.</p></div>
<div><span class="eyebrow">Confidentiality and client responsibilities</span><h2 style="margin:.35em 0 .6em">Working together properly</h2>
<p>We handle personal and financial information with regard to POPIA and professional confidentiality. Clients provide complete and accurate records, approve submissions as agreed and make payments by the required dates. Access and authority are arranged through the applicable taxpayer and practitioner processes.</p></div></div></section>
<section class="section"><div class="container"><div class="band"><div><span class="eyebrow light">Remote by design</span><h2 style="margin:.35em 0 .45em">No office visit needed</h2>
<p>Documents are sent electronically and filed in a dedicated client folder per financial year. Work is prepared on Taxnify and reviewed by the practitioner before anything is submitted, and only with your approval. See our <a href="/services/" style="color:#7fe0a0">services</a> or <a href="/service-areas/" style="color:#7fe0a0">service areas</a>.</p></div>
<div><a class="btn btn-primary" href="/contact/">Book a free consultation</a></div></div></div></section>{cta()}"""
page("/how-we-work/", "How We Work: Remote Accounting &amp; Tax | Think Mybiz",
     "Free consultation, written quotation, 50% deposit to start, practitioner review and delivery on final payment. Remote accounting and tax across SA.",
     how, "/how-we-work/", [crumbs_schema([("Home", "/"), ("How we work", "/how-we-work/")])])

# ------------------------------------------------------------------ SERVICE AREAS
areas = hero("Service areas", "Accountants and tax practitioners serving all of South Africa",
  "Based in Mokopane, Limpopo, with remote services available in all nine provinces. Documents are sent electronically, meetings happen by phone or video call, and office consultations are available by arrangement.",
  [("Home", "/"), ("Service areas", None)]) + f"""
<section class="section"><div class="container"><div class="section-head"><span class="eyebrow">City pages</span><h2>Where many of our clients are</h2></div>
<div class="grid g4" style="margin-bottom:34px">{"".join(f'<a class="card" href="{c["path"]}">{icon("pin")}<h3>{c["short"]}</h3><p>{c["province"]}</p><span class="more">View →</span></a>' for c in CITIES)}</div>
<div class="grid g3">{"".join(f'<div class="card">{icon("pin")}<h3>{p}</h3><p>{t}</p></div>' for p,t in AREAS)}</div>
{note("Wherever you are", "Every service on this website, from tax returns to SARS disputes, is available remotely. Distance does not change the scope, the review or the practitioner responsible for your work.")}</div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Popular services</span><h2>What clients across South Africa ask us for</h2></div>
<div class="grid g3">{svc_cards([SVC[k] for k in ("individual-tax","sars-disputes","tax-debt","vat","accounting","business-compliance")])}</div></div></section>{cta()}"""
page("/service-areas/", "Accountants Serving All of South Africa | Think Mybiz",
     "Remote accounting, tax and SARS services in Gauteng (Johannesburg, Pretoria, Midrand), Limpopo (Mokopane, Polokwane) and every other province.",
     areas, "/service-areas/", [crumbs_schema([("Home", "/"), ("Service areas", "/service-areas/")])])

# ------------------------------------------------------------------ CONTACT
opts = "".join(f'<option value="{s["slug"]}">{s["short"]}</option>' for s in SERVICES)
prov_opts = "".join(f'<option>{p}</option>' for p in TOWNS) + f'<option>{OUTSIDE_SA}</option>'
wa_item = (f'<div class="contact-item">{icon("phone")}<div><small>WhatsApp</small><a href="{wa_link()}">{WA_DISPLAY}</a></div></div>' if WHATSAPP else "") + f'<div class="contact-item">{icon("phone")}<div><small>Cell</small><a href="tel:{MTEL}">{MOBILE}</a></div></div>'
contact = hero("Contact", "Book a free consultation",
  "Tell us what you need, or send us the SARS or compliance notice you have received. We will assess the work and reply with an agreed scope.",
  [("Home", "/"), ("Contact", None)], actions=False) + f"""
<section class="section"><div class="container grid contact-grid">
<div class="card"><form id="enquiry" class="form" action="/contact.php" method="post" novalidate>
<div class="two"><div><label for="f-name">Full name *</label><input id="f-name" name="name" required autocomplete="name"></div>
<div><label for="f-phone">Phone</label><input id="f-phone" name="phone" type="tel" autocomplete="tel"></div></div>
<div class="two"><div><label for="f-email">Email *</label><input id="f-email" name="email" type="email" required autocomplete="email"></div>
<div><label for="f-prov">Province *</label><select id="f-prov" name="province" required data-towns='{E(json.dumps(TOWNS))}' data-other="{OTHER_TOWN}" data-outside="{OUTSIDE_SA}"><option value="">Select your province</option>{prov_opts}</select></div></div>
<div class="two"><div id="town-wrap"><label for="f-town">Town or village</label><select id="f-town" name="town" disabled><option value="">Select a province first</option></select></div>
<div id="other-wrap" hidden><label for="f-other" id="f-other-label">Your town or village</label><input id="f-other" name="town_other" maxlength="80" placeholder="Type your town or village"></div></div>
<div><label for="f-svc">How can we help?</label><select id="f-svc" name="service"><option value="General enquiry">General enquiry</option>{opts}</select></div>
<div><label for="f-msg">Your message *</label><textarea id="f-msg" name="message" required placeholder="Briefly describe what you need. For a SARS matter, include the type of notice, the tax type and period, and the date you received it. Please do not attach ID numbers or sensitive documents here."></textarea></div>
<label class="consent"><input type="checkbox" name="consent" value="1" required><span>I agree that Think Mybiz may use these details to respond to my enquiry, as described in the <a href="/privacy/">privacy notice</a>. *</span></label>
<div class="hp" aria-hidden="true"><label for="f-hp">Leave this field empty</label><input id="f-hp" name="hp_check" tabindex="-1" autocomplete="new-password"></div><input type="hidden" name="ts" value="">
<button class="btn btn-primary" type="submit" style="justify-content:center">Send enquiry</button>
<p id="enquiry-ok" class="notice" style="display:none">Thank you. Your enquiry has been sent to {EMAIL}. We will be in touch.</p>
<p id="enquiry-err" class="notice err" style="display:none"></p>
</form></div>
<div><div class="card">
<div class="contact-item">{icon("phone")}<div><small>Telephone</small><a href="tel:{TEL}">{PHONE}</a></div></div>
{wa_item}
<div class="contact-item">{icon("mail")}<div><small>Email</small><a href="mailto:{EMAIL}">{EMAIL}</a></div></div>
<div class="contact-item">{icon("pin")}<div><small>Office</small><span>{ADDRESS}</span></div></div>
<div class="contact-item">{icon("globe")}<div><small>Service area</small><span>All nine provinces through remote service; office consultations by arrangement.</span></div></div>
</div>
{note("Urgent SARS matter?", "Send the full notice, assessment date, relevant tax type and period, and any existing case reference. Request a secure route before sending sensitive supporting documents.")}
</div></div></section>"""
page("/contact/", "Contact Think Mybiz | Free Tax Consultation",
     "Call or WhatsApp 066 210 5631, email accounts@thinkmybiz.co.za or send an enquiry. Free consultation; remote service anywhere in South Africa.",
     contact, "/contact/", [crumbs_schema([("Home", "/"), ("Contact", "/contact/")])])

# ------------------------------------------------------------------ PRIVACY
priv = hero("Privacy", "Privacy &amp; POPIA notice", "How this website handles personal information.", [("Home", "/"), ("Privacy", None)], actions=False) + f"""
<section class="section"><div class="container content" style="max-width:820px">
<p>{BRAND} ({E(LEGAL)}) respects your privacy and processes personal information in line with the Protection of Personal Information Act, 2013 (POPIA).</p>
<h2>What we collect through this website</h2><p>When you send an enquiry, we receive the details you choose to give us: your name, email address, phone number, province and town, the service you are interested in and your message. The enquiry is sent by email to {EMAIL}; the website does not store it in a database.</p>
<h2>How we use it</h2><p>We use your information only to respond to your enquiry, prepare a quotation and, if you engage us, deliver the agreed services and meet our legal and professional obligations. We do not sell your personal information.</p>
<h2>Sensitive information</h2><p>Please do not send identity numbers, tax reference numbers or supporting documents through the website form. Ask us for a secure route for sensitive documents.</p>
<h2>Client information</h2><p>Client information is handled with professional confidentiality. Access and authority to act with SARS and other authorities are arranged through the applicable taxpayer and practitioner processes.</p>
<h2>Your rights</h2><p>You may ask what personal information we hold about you, ask us to correct or delete it, or object to its processing, subject to our legal record-keeping obligations. Contact us at <a href="mailto:{EMAIL}">{EMAIL}</a>. You may also lodge a complaint with the Information Regulator of South Africa.</p>
<h2>Acknowledgement email</h2><p>When you send an enquiry, we send one automatic email to the address you gave, confirming that we received it.</p>
<h2>Cookies and analytics</h2>{('<p>With your consent, this website uses Google Analytics to count visits and enquiries (for example, which pages lead to an enquiry). Analytics cookies are only set if you click Accept on the cookie notice; you can withdraw consent by clearing this site&#39;s data in your browser. We do not use advertising cookies.</p>' if GA_ID else '<p>This website does not use advertising or tracking cookies.</p>')}<p>Fonts are loaded from Google Fonts.</p>
</div></section>"""
page("/privacy/", "Privacy &amp; POPIA Notice | Think Mybiz", "How Think Mybiz Accountants and Tax Practitioners handles personal information submitted through this website, in line with POPIA.", priv, "")

# ------------------------------------------------------------------ INDUSTRY PAGES
for ind in INDUSTRIES:
    path = f'/industries/{ind["slug"]}/'
    body = hero("Industries", f'Accounting and tax for {ind["name"].lower()}', ind["lead"],
                [("Home", "/"), ("Industries", "/industries/"), (ind["name"], None)]) + f"""
<section class="section"><div class="container two-col"><div class="content">
<h2>What we see in {ind["name"].lower()}</h2>{ul(ind["challenges"])}
<p>All work is prepared and reviewed under MF Banda, SARS registered tax practitioner ({PR}), and submitted only with your approval. We serve clients anywhere in South Africa remotely.</p></div>
<aside class="aside"><div class="card" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec">
<h3 style="color:#fff">Talk to us</h3><p style="font-size:.93rem">Free initial consultation for {ind["name"].lower()} businesses.</p>
<a class="btn btn-primary" href="/contact/" style="margin-top:14px;width:100%;justify-content:center">Book a consultation</a>
<a class="btn btn-ghost" href="tel:{TEL}" style="margin-top:10px;width:100%;justify-content:center">Call {PHONE}</a></div>
<div class="card"><h3>Other industries</h3><ul>{"".join(f'<li><a href="/industries/{o["slug"]}/">{o["name"]}</a></li>' for o in INDUSTRIES if o is not ind)}</ul></div></aside></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">How we help</span><h2>Services for {ind["name"].lower()}</h2></div>
<div class="grid g3">{svc_cards([SVC[k] for k in ind["focus"]])}</div></div></section>{cta()}"""
    page(path, ind["seo"], ind["desc"], body, "/services/",
         [crumbs_schema([("Home", "/"), ("Industries", "/industries/"), (ind["name"], path)])])
indhub = hero("Industries", "Industries we serve",
  "We work with individuals, start-ups and established small and medium businesses across sectors. These are the industries where we see the most specific compliance needs.",
  [("Home", "/"), ("Industries", None)]) + f"""
<section class="section"><div class="container"><div class="grid g4">{"".join(f'<a class="card" href="/industries/{i["slug"]}/">{icon(i["icon"])}<h3>{i["name"]}</h3><p>{E(html.unescape(i["lead"]))[:120]}…</p><span class="more">View →</span></a>' for i in INDUSTRIES)}</div>
<p style="margin-top:24px">Also: retail, hospitality, professional services and individuals. <a href="/services/">See all services →</a></p></div></section>{cta()}"""
page("/industries/", "Industries We Serve | Think Mybiz Accountants",
     "Accounting, tax and compliance for construction, security, transport and logistics, nonprofits and schools, anywhere in South Africa.",
     indhub, "/services/", [crumbs_schema([("Home", "/"), ("Industries", "/industries/")])])

# ------------------------------------------------------------------ CITY PAGES
for c in CITIES:
    towns_line = c["areas"]
    body = hero(f'{c["province"]} · {c["city"]}', f'Accountants and tax practitioners for {c["city"]}', c["lead"],
                [("Home", "/"), ("Service areas", "/service-areas/"), (c["short"], None)]) + f"""
<section class="section"><div class="container two-col"><div class="content">
<h2>Accounting and tax help in {c["city"]}</h2><p>{c["body"]}</p>
<p>All work is prepared and reviewed under MF Banda, SARS registered tax practitioner ({PR}), and nothing is submitted to SARS or CIPC without your approval.</p>
<h2>Areas we serve around {c["city"]}</h2><p>{towns_line}. Not listed? We work remotely with clients anywhere in South Africa.</p>
<h2>Services {c["city"]} clients use most</h2></div>
<aside class="aside"><div class="card" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec">
<h3 style="color:#fff">Free consultation</h3><p style="font-size:.93rem">Remote service for {c["city"]} by phone, WhatsApp or video call.</p>
<a class="btn btn-primary" href="/contact/" style="margin-top:14px;width:100%;justify-content:center">Book a consultation</a>
<a class="btn btn-ghost" href="tel:{TEL}" data-track="call" style="margin-top:10px;width:100%;justify-content:center">Call {PHONE}</a></div></aside></div>
<div class="container" style="margin-top:6px"><div class="grid g3">{svc_cards([SVC[k] for k in c["focus"]])}</div>
<p style="margin-top:22px"><a href="/services/">See all services →</a></p></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">How remote service works</span><h2>No office visit needed</h2></div>
<div class="grid g3"><div class="card">{icon("phone")}<h3>1. Free consultation</h3><p>Call, WhatsApp or send an enquiry. We review your position and urgent dates.</p></div>
<div class="card">{icon("cloud")}<h3>2. Documents online</h3><p>Send documents electronically; we agree a secure route for sensitive ones.</p></div>
<div class="card">{icon("check")}<h3>3. Prepared, reviewed, approved</h3><p>Work is prepared and reviewed by the practitioner and submitted only with your approval.</p></div></div></div></section>{cta()}"""
    page(c["path"], c["title"], c["desc"], body, "/service-areas/",
         [crumbs_schema([("Home", "/"), ("Service areas", "/service-areas/"), (c["short"], c["path"])]),
          {"@context": "https://schema.org", "@type": "Service", "name": f'Accounting and tax services in {c["city"]}', "provider": {"@id": DOMAIN + "/#practice"},
           "areaServed": {"@type": "City", "name": c["city"].split(" and ")[0]}}])

# ------------------------------------------------------------------ INSIGHTS
import shutil
shutil.rmtree(os.path.join(OUT, "insights"), ignore_errors=True)  # generated only; rebuilt from _src/articles
from urllib.parse import quote as _q
SHARE = lambda url, t: (f'<div class="share"><span>Share:</span>'
    f'<a href="https://wa.me/?text={_q(t + " " + url)}" target="_blank" rel="noopener" data-track="share_whatsapp">WhatsApp</a>'
    f'<a href="https://www.linkedin.com/sharing/share-offsite/?url={_q(url)}" target="_blank" rel="noopener" data-track="share_linkedin">LinkedIn</a>'
    f'<a href="https://www.facebook.com/sharer/sharer.php?u={_q(url)}" target="_blank" rel="noopener" data-track="share_facebook">Facebook</a>'
    f'<button type="button" data-copy="{url}">Copy link</button></div>')
for i, a in enumerate(ARTICLES):
    url = DOMAIN + a["path"]; svc = SVC[a["service"]]
    upd = f' · Updated {nice_date(a["updated"])}' if a.get("updated") else ""
    srcs = "".join(f'<li><a href="{E(u)}" target="_blank" rel="noopener">{E(t)}</a></li>' for t, u in a["sources"])
    others = [x for x in ARTICLES if x is not a][:4]
    body = f"""<section class="hero page-hero"><div class="container"><div class="crumbs"><a href="/">Home</a> / <a href="/insights/">Insights</a></div>
<span class="eyebrow light">{svc["short"]}</span><h1 style="max-width:900px">{E(a["title"])}</h1><p class="lead">{E(a["description"])}</p>
<p class="byline">By <a href="/about/">MF Banda</a>, SARS registered tax practitioner {PR} · Published {nice_date(a["date"])}{upd}</p></div></section>
<section class="section"><div class="container two-col"><article class="content article">{a["html"]}
<div class="sources"><h2>Official sources</h2><p>Checked on the publication date. Rules change; always confirm against the source.</p><ul>{srcs}</ul></div>
{note("General information, not advice", "This article explains general rules. Your position depends on your facts; contact us for advice on your situation.")}
{SHARE(url, a["title"])}</article>
<aside class="aside"><div class="card" style="background:var(--navy-900);border-color:var(--navy-900);color:#c9d4ec">
<h3 style="color:#fff">Need help with this?</h3><p style="font-size:.93rem">{svc["card"]}</p>
<a class="btn btn-primary" href="/contact/?service={svc["slug"]}" style="margin-top:14px;width:100%;justify-content:center">Book a free consultation</a>
<a class="btn btn-ghost" href="{svc and "/services/" + svc["slug"] + "/"}" style="margin-top:10px;width:100%;justify-content:center">{svc["short"]}</a></div>
{('<div class="card"><h3>More insights</h3><ul>' + "".join(f'<li><a href="{x["path"]}">{E(x["title"])}</a></li>' for x in others) + '</ul></div>') if others else ""}</aside></div></section>{cta()}"""
    art_ld = {"@context": "https://schema.org", "@type": "Article", "headline": a["title"], "description": a["description"],
              "datePublished": a["date"], "dateModified": a.get("updated") or a["date"], "mainEntityOfPage": url,
              "image": DOMAIN + "/assets/img/og.png", "wordCount": a["words"],
              "author": {"@id": DOMAIN + "/about/#mf-banda"}, "publisher": {"@id": DOMAIN + "/#practice"},
              "citation": [u for _, u in a["sources"]]}
    page(a["path"], a["seo_title"], a["description"], body, "/insights/",
         [art_ld, PERSON, crumbs_schema([("Home", "/"), ("Insights", "/insights/"), (a["title"], a["path"])])], og_type="article",
         head_extra=f'<meta property="article:published_time" content="{a["date"]}">')
if ARTICLES:
    ins = hero("Insights", "Tax and compliance insights",
      "Practical guidance on SARS, tax debt, VAT, payroll and business compliance, written and reviewed by a SARS registered tax practitioner and based on official sources.",
      [("Home", "/"), ("Insights", None)], actions=False) + f"""
<section class="section"><div class="container"><div class="grid g3">{article_cards(ARTICLES)}</div></div></section>{cta()}"""
    page("/insights/", "Tax &amp; SARS Insights for South Africans | Think Mybiz",
         "Practical, source-checked guidance on SARS letters, objections, tax debt, VAT, payroll and business compliance from a registered tax practitioner.",
         ins, "/insights/", [crumbs_schema([("Home", "/"), ("Insights", "/insights/")])])
os.makedirs(os.path.join(OUT, "insights"), exist_ok=True)
with open(os.path.join(OUT, "insights", "feed.xml"), "w", encoding="utf-8") as f:
    import email.utils as _eu, datetime as _dt
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n<rss version="2.0"><channel><title>Think Mybiz Insights</title>'
            f'<link>{DOMAIN}/insights/</link><description>Tax and compliance insights from Think Mybiz Accountants &amp; Tax Practitioners</description><language>en-za</language>\n')
    for a in ARTICLES:
        d = _eu.format_datetime(_dt.datetime.fromisoformat(a["date"] + "T08:00:00+02:00"))
        f.write(f'<item><title>{E(a["title"])}</title><link>{DOMAIN}{a["path"]}</link><guid>{DOMAIN}{a["path"]}</guid><pubDate>{d}</pubDate><description>{E(a["description"])}</description></item>\n')
    f.write("</channel></rss>\n")

# ------------------------------------------------------------------ 404
nf = hero("Page not found", "We couldn't find that page", "The page may have moved. Try our services, or contact us directly.", actions=False) + \
     f'<section class="section"><div class="container"><div class="grid g3">{svc_cards(SERVICES[:6])}</div></div></section>'
page("/404.html", "Page not found | Think Mybiz", "Page not found.", nf, "", noindex=True)

# ------------------------------------------------------------------ sitemap, robots, htaccess
import datetime
LM_FILE = os.path.join(OUT, "_src", "lastmod.json")
try: LM = json.load(open(LM_FILE))
except Exception: LM = {}
today = datetime.date.today().isoformat()
for pth, h in PAGES_HASH.items():
    if LM.get(pth, {}).get("hash") != h: LM[pth] = {"hash": h, "date": ARTICLE_DATES.get(pth, today)}
LM = {k: v for k, v in LM.items() if k in PAGES_HASH}
json.dump(LM, open(LM_FILE, "w"), indent=1, sort_keys=True)
with open(os.path.join(OUT, "sitemap.xml"), "w") as f:
    f.write('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n')
    for p in SITEMAP:
        pr = "1.0" if p == "/" else ("0.9" if p.startswith("/services/") or p == "/sars-letter/" else ("0.8" if p.startswith("/insights/") or p.startswith("/accountants-") else "0.7"))
        f.write(f"  <url><loc>{DOMAIN}{p}</loc><lastmod>{LM[p]['date']}</lastmod><priority>{pr}</priority></url>\n")
    f.write("</urlset>\n")
open(os.path.join(OUT, INDEXNOW_KEY + ".txt"), "w").write(INDEXNOW_KEY)
open(os.path.join(OUT, "robots.txt"), "w").write(f"User-agent: *\nAllow: /\nDisallow: /_src/\n\nSitemap: {DOMAIN}/sitemap.xml\n")
open(os.path.join(OUT, ".htaccess"), "w").write("""Options -Indexes
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
RewriteCond %{HTTP_HOST} ^www\\.(.+)$ [NC]
RewriteRule ^ https://%1%{REQUEST_URI} [L,R=301]
RedirectMatch 404 /\\.(git|gitignore)
RedirectMatch 404 ^/_src/
RedirectMatch 404 ^/README\\.md$
ErrorDocument 404 /404.html
<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/css "access plus 7 days"
ExpiresByType application/javascript "access plus 7 days"
ExpiresByType image/png "access plus 30 days"
ExpiresByType image/webp "access plus 30 days"
</IfModule>
""")
print(f"Built {len(SITEMAP)} indexed pages + 404")
