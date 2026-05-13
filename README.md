# Money Wise 2026

A static HTML website on credit cards and personal finance, built for Google AdSense approval and SEO ranking. Pure HTML5 + CSS3 + Vanilla JavaScript &mdash; no frameworks, no backend, no build process.

**Live site:** [moneywise2026.com](https://moneywise2026.com)

---

## Project Overview

- **Niche:** Credit Cards, Credit Score, Personal Finance
- **Target audience:** US consumers
- **Total pages:** 23+ (Homepage + 7 static pages + 15 blog articles + 404)
- **Content:** 35,000+ words of original educational content
- **Stack:** HTML5 / CSS3 / Vanilla JS only
- **Design aesthetic:** Editorial/magazine style (Investopedia / NerdWallet inspired)
- **Approval target:** Google AdSense first-attempt approval

---

## File Structure

```
/
├── index.html                          # Homepage
├── about.html                          # About Us
├── contact.html                        # Contact form
├── privacy-policy.html                 # Privacy Policy (GDPR/CCPA compliant)
├── terms.html                          # Terms of Service
├── disclaimer.html                     # Financial disclaimer + affiliate disclosure
├── blog.html                           # Blog index (all 15 articles)
├── 404.html                            # Not Found page
├── robots.txt                          # Crawler directives
├── sitemap.xml                         # XML sitemap (22 URLs)
├── favicon.svg                         # Vector favicon
├── README.md                           # This file
│
├── assets/
│   ├── css/style.css                   # Complete design system (~900 lines)
│   ├── js/main.js                      # Vanilla JS (menu, smooth scroll, lazy load)
│   └── images/                         # (Empty — using CSS color blocks)
│
└── blog/
    ├── best-credit-cards-beginners-2026.html
    ├── top-cashback-credit-cards.html
    ├── how-to-improve-credit-score-fast.html
    ├── best-travel-credit-cards-2026.html
    ├── credit-card-vs-debit-card.html
    ├── choose-first-credit-card.html
    ├── best-business-credit-cards.html
    ├── top-balance-transfer-cards.html
    ├── best-student-credit-cards-2026.html
    ├── maximize-credit-card-rewards.html
    ├── how-credit-card-interest-works.html
    ├── top-premium-credit-cards.html
    ├── credit-cards-bad-credit.html
    ├── credit-card-fees-explained.html
    └── pay-off-credit-card-debt.html
```

---

## Local Preview

Open `index.html` in any browser. No build step needed.

For a local server (recommended to test relative paths):

```bash
# Python 3
python -m http.server 8000

# Node (if you have npx)
npx serve

# PHP
php -S localhost:8000
```

Then visit `http://localhost:8000`.

---

## Deployment

The site is a fully static collection of HTML/CSS/JS files and can be deployed to any static host.

### Option 1: Cloudflare Pages (recommended)
1. Push the repo to GitHub.
2. Log in to Cloudflare Pages and connect the repo.
3. Build settings: **None** (no build command, output directory `/`).
4. Add custom domain `moneywise2026.com` in Pages > Custom Domains.
5. Update DNS to Cloudflare nameservers.

### Option 2: Vercel
1. Push the repo to GitHub.
2. Import the repo into Vercel.
3. Framework preset: **Other**. Build command: empty. Output directory: `./`.
4. Add `moneywise2026.com` as the production domain.

### Option 3: Netlify
1. Push the repo to GitHub.
2. New site from Git.
3. Build command: empty. Publish directory: `./`.
4. Add custom domain in Domain Settings.

### Option 4: GitHub Pages
1. In repo Settings > Pages, set source to `main` branch / root.
2. Add `moneywise2026.com` in the custom domain field.
3. Add the corresponding A/CNAME records to your DNS.

### Option 5: Traditional shared hosting (cPanel etc.)
Upload all files to the `public_html` directory of your host via FTP or the file manager. Ensure `index.html` is at the document root.

---

## SSL / HTTPS

All recommended hosts above provide free SSL via Let's Encrypt or their own certificate. Ensure HTTPS is enabled before submitting to AdSense.

---

## Adding Google AdSense (after approval)

1. **Get your AdSense publisher ID** in the format `ca-pub-XXXXXXXXXXXXXXXX`.
2. **Add the AdSense script** to every HTML file by replacing the comment:
   ```html
   <!-- AdSense Code - To be added after approval -->
   ```
   with the actual AdSense snippet:
   ```html
   <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>
   ```
3. **Add ad units** in the placeholder locations within article HTML:
   - `<!-- AD-UNIT-1: Top of article -->`
   - `<!-- AD-UNIT-2: Mid-page -->` (homepage only)
   - `<!-- AD-UNIT-3: End of article -->`
4. **Wait 24-48 hours** for ads to start displaying.

To bulk-replace the AdSense placeholder across all files (PowerShell, run from the project root):

```powershell
Get-ChildItem -Path . -Filter *.html -Recurse | ForEach-Object {
    (Get-Content $_.FullName -Raw) -replace '<!-- AdSense Code - To be added after approval -->', '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>' | Set-Content $_.FullName -Encoding utf8
}
```

Replace `ca-pub-XXXXXXXXXXXXXXXX` with your actual AdSense ID.

---

## Google Search Console Setup

1. Verify ownership at [Google Search Console](https://search.google.com/search-console).
   - Easiest method: add the verification HTML tag to `index.html` `<head>`, or upload the verification HTML file to the root.
2. Submit the sitemap: `https://moneywise2026.com/sitemap.xml`.
3. Request indexing of the homepage and key articles.

---

## Google Analytics (optional)

Add the GA4 tag to every HTML file&rsquo;s `<head>`:

```html
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

A bulk PowerShell replace can insert this snippet after the `<head>` tag in every HTML file similarly to the AdSense replace above.

---

## AdSense Approval Checklist

Before submitting to AdSense, verify:

- [x] All 23+ pages have unique, original content
- [x] All articles are 1500-2000 words each (original, educational)
- [x] No copied content from other websites
- [x] Privacy Policy explicitly mentions AdSense and cookies
- [x] Disclaimer states "not financial advice" and includes FTC affiliate disclosure
- [x] Contact page has a working method to reach the site (mailto + form)
- [x] About page describes mission, editorial team, and standards
- [x] No broken internal links
- [x] All pages mobile-responsive
- [x] Sitemap.xml lists all pages and is reachable at `/sitemap.xml`
- [x] Robots.txt allows crawlers
- [x] Schema.org JSON-LD on every page (Article, FAQPage, BreadcrumbList, Organization)
- [x] Consistent navigation across all pages
- [x] Footer has all legal pages linked
- [ ] HTTPS enabled (handled at hosting level)
- [ ] Custom domain pointing correctly (handled after deployment)
- [ ] Submitted to Google Search Console

---

## Brand and Editorial Notes

- **Site name:** Money Wise 2026
- **Domain:** moneywise2026.com
- **Tagline:** "Smart Money Decisions for 2026 and Beyond"
- **Tone:** Educational, authoritative, neutral. No aggressive sales language.
- **Editorial team (fictional):** Sarah Mitchell (Senior Editor), James Carter (Credit Analyst), Emily Rodriguez (Personal Finance Writer)
- **Color palette:** Navy `#0a2540`, Emerald `#00875a`, Warm beige `#f5f1ea`, Charcoal `#1a1a1a`
- **Typography:** Playfair Display (serif headlines), Source Sans 3 (sans body), Lora (article body serif)

---

## Updating Content

Each article is a standalone HTML file. To update one:

1. Edit the relevant file in `/blog/`.
2. Update the `<meta property="article:modified_time">` and the "Last updated" date in the article header.
3. Update the `<lastmod>` in `sitemap.xml` for that URL.
4. Commit and push to redeploy.

---

## License

All content is &copy; 2026 Money Wise 2026. Code structure may be freely reused; written content is proprietary.

---

## Contact

For questions about this codebase or deployment: contact@moneywise2026.com
