# Accessibility & Honest Content

Standard to follow: **WCAG 2.2 Level AA** (the common international benchmark for web accessibility).
Applies to: platform site, dashboard, member portal, **and the gym website templates** (gyms can't fix template code themselves).

Related: [legal-and-privacy-ph.md](../legal/legal-and-privacy-ph.md)

Phase 7 local pass (2026-10-06): 78 desktop/360px axe snapshots and keyboard login/member/payment/check-in/publish workflows pass, including 200% text reflow. Shared landmarks, contrast, labels, error focus and reduced-motion dialogs are improved; pricing claims now match implemented features. Scope/artifacts and remaining formal screen-reader/device checks: [launch verification](../engineering/launch-verification.md). This is not a blanket certification of every checklist item below.

---

## 1. Accessibility

Who benefits: members with low vision, color blindness, older owners, staff using only a keyboard + barcode scanner, people on cheap phones in bright sunlight.

### Text, images, color

- [ ] Every meaningful image has `alt` text. Decorative images use `alt=""`.
- [ ] Gym website builder **requires alt text** when an owner uploads gallery images (with a hint: "Describe the photo, e.g. 'Free weights area with squat racks'").
- [ ] Logos: `alt="Iron House Fitness"` (the name, not "logo").
- [ ] Text contrast ≥ **4.5:1** (normal text), ≥ **3:1** (large text ≥ 24px, icons, input borders, focus rings).
- [ ] **Gym brand colors:** owners pick their colors → the builder must check contrast and warn or auto-pick white/black text on their color.
- [ ] Never use color alone for meaning. Membership status shows **text + color**: `● Active`, `● Expiring soon`, `● Expired`.
- [ ] Text resizes to 200% without breaking layout; use `rem`, not fixed `px` for font sizes.
- [ ] Base font size ≥ 16px; line height ~1.5.
- [ ] Respect `prefers-reduced-motion` for animations.
- [ ] `<html lang="en">` (or `fil` for Filipino pages).

### Keyboard

- [ ] Everything works with **Tab, Shift+Tab, Enter, Space, Esc** — no mouse needed.
- [ ] Visible focus style on every interactive element (never `outline: none` without a replacement).
- [ ] Logical tab order (follows visual order).
- [ ] "Skip to main content" link at the top.
- [ ] Modals: focus moves into the modal, is trapped inside, `Esc` closes, focus returns to the button that opened it.
- [ ] Dropdowns, date pickers, tabs: use accessible components (e.g. Headless UI / Radix Vue / Reka UI) instead of building from scratch.
- [ ] Check-in page: the search/scan input is auto-focused, and USB QR scanners (which act as keyboards) work without clicking.

### Forms & buttons

- [ ] Every input has a visible `<label for="...">` — placeholders are **not** labels.
- [ ] Required fields marked in text ("required"), not only with `*` or red color.
- [ ] Errors: shown next to the field, in text, linked with `aria-describedby`; focus moves to the first error on submit.
- [ ] Error messages say how to fix: "Mobile number must be 11 digits, e.g. 09171234567" not "Invalid input".
- [ ] Use correct input types: `type="email"`, `type="tel"`, `inputmode="numeric"`, `autocomplete="name"` etc. (helps mobile keyboards and autofill).
- [ ] Buttons say what they do: "Record payment", "Renew membership" — not "Submit", "Click here", "OK".
- [ ] Icon-only buttons have `aria-label` ("Edit member", "Delete").
- [ ] Use `<button>` for actions and `<a>` for navigation — not clickable `<div>`s.
- [ ] Destructive actions (delete member, void payment) ask for confirmation.
- [ ] Touch targets ≥ 24×24 px (aim for 44×44 on mobile).
- [ ] Loading states announced (`aria-busy`, or a status message) — e.g. "Checking in…".

### Structure

- [ ] One `<h1>` per page; headings in order (h1 → h2 → h3).
- [ ] Landmarks: `<header>`, `<nav>`, `<main>`, `<footer>`.
- [ ] Data tables use `<table>`, `<th scope="col">`, and a caption — not div grids.
- [ ] Inertia page changes update `document.title` (use `<Head title="Members">`) so screen readers announce the new page.
- [ ] Links are distinguishable (underline or not color-only) and describe their target.

### Testing

- [ ] Automated: Lighthouse / axe DevTools browser extension on every main page (target 0 critical issues).
- [ ] Manual: unplug the mouse and complete — log in, add member, record payment, check in, publish website.
- [ ] Screen reader quick check: NVDA (free, Windows) on the member portal and a gym website.
- [ ] Zoom to 200% and test on a small phone.
- [ ] Color contrast checker: WebAIM Contrast Checker for brand colors.

---

## 2. Honest content (no fake or unprovable claims)

Misleading claims can violate the Consumer Act and damage trust with gym owners.

- [ ] **No fake reviews or testimonials.** Remove all placeholder testimonials, "Juan D. — Gym Owner" quotes, star ratings, and fake logos before launch. Only publish real ones **with written permission** from the person.
- [ ] **No fake numbers:** "Trusted by 100+ gyms", "10,000 members managed" — only when true and current.
- [ ] **No claims you can't prove:**
    - ❌ "100% secure", "unhackable", "bank-level security"
    - ❌ "99.99% uptime" (shared hosting can't promise this)
    - ❌ "Increase revenue by 50%"
    - ❌ "Lose 5 kg a month" (calorie helper — health claim)
    - ✅ "Passwords are encrypted and data is backed up daily" (if true)
- [ ] No fake urgency ("Only 2 slots left!") or fake countdown timers.
- [ ] Prices shown are the real total; say whether VAT is included and what each plan includes (the pricing cards gray out what a plan lacks).
- [ ] Calorie helper shows a clear disclaimer: estimates only, not medical advice.
- [ ] Remove all lorem ipsum, demo gyms, test accounts, and sample data from the live site.
- [ ] **Gym websites:** Terms say gym owners are responsible for their own content and claims; you provide a way to report abusive/infringing content and can take it down.

---

## 3. Business details

Show on the platform site (footer + Contact page):

- [ ] Registered business name (DTI/SEC name)
- [ ] Business address
- [ ] Contact email (support + privacy/DPO) and phone
- [ ] Business registration number (recommended for trust)
- [ ] Support hours and expected response time
- [ ] Links: Privacy Policy, Terms, Cookie Policy, Refund Policy

Gym websites must show the **gym's** details (name, address, contact, hours) — required fields in the builder before publishing.

---

## 4. Rights to images, fonts, icons, and code

Keep a credits/licenses record so you can prove you have rights.

| Asset type               | Safe sources                                                                             | Avoid                                                                                   |
| ------------------------ | ---------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Photos                   | Your own photos, paid stock, Unsplash / Pexels (read their license), photos the gym owns | Google Images, other gyms' websites, Facebook/Instagram photos                          |
| Fonts                    | Google Fonts (SIL Open Font License) — **self-host** them; licensed commercial fonts     | Fonts copied from other sites, "free for personal use" fonts in a commercial product    |
| Icons                    | Lucide, Heroicons, Tabler (MIT/ISC)                                                      | Ripped icon sets, brand logos you don't have permission for                             |
| Illustrations            | Your own, unDraw (check license), paid                                                   | Unlicensed downloads                                                                    |
| Code / packages          | Check each package license (MIT, BSD, Apache are fine for commercial use)                | GPL code copied into your app without understanding the obligations; code from employer |
| Music/video on gym sites | Gym's own or properly licensed                                                           | Copyrighted songs in promo videos                                                       |

- [ ] Create `docs/design/asset-credits.md` listing each asset: source, license, link, date.
- [ ] Terms of Service: gym owners confirm they have rights to everything they upload; you can remove infringing content.
- [ ] Don't use other gyms' or brands' logos/trademarks (e.g. "as seen on…", equipment brand logos) without permission.
- [ ] Your product name and domain: check it's not already a registered trademark (IPOPHL search) before building a brand around it.
