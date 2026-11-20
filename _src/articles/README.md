# Insights articles

One Markdown file per article: `_src/articles/<url-slug>.md`. The file name becomes the URL `/insights/<url-slug>/`.

```markdown
---
title: Received a SARS letter of demand? Your options explained
seo_title: SARS Letter of Demand? Your Options Explained | Think Mybiz   (max 60 characters)
description: One or two sentences for Google (max 160 characters).
date: 2026-11-20
updated: 2026-12-01            (optional; set when facts are re-checked or changed)
service: tax-debt              (slug of the related service page)
status: published              (or draft: not built)
sources:
  - SARS: What if I owe SARS money? | https://www.sars.gov.za/...
---

Article body in Markdown (## headings, lists, tables).
```

Rules: facts, dates, thresholds and deadlines only from official sources (SARS, CIPC, Department of Employment and Labour, legislation), listed under `sources`; no outcome promises; every article is approved by MF Banda before it reaches `main`. The build refuses an article without sources, with an unknown service, or with a title or description that is too long.
