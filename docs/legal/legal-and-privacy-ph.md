# Legal, Privacy & Compliance (Philippines)

> **Not legal advice.** This is a developer's working checklist. Laws, circulars, and thresholds change — confirm with the official sources (NPC, DTI, BIR) or a Philippine lawyer before launch, especially the policy pages.

Location: developer in the Philippines; target users (gym owners, staff, members) in the Philippines.

Related: [security.md](../engineering/security.md) · [accessibility-and-content.md](../design/accessibility-and-content.md)

---

## 1. Which laws apply?

| Law                                                                                                  | Why it matters here                                                                                                                                                      |
| ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Data Privacy Act of 2012 (RA 10173)** + IRR, enforced by the **National Privacy Commission (NPC)** | You process members' personal data. This is the main one.                                                                                                                |
| **Internet Transactions Act of 2023 (RA 11967)**                                                     | Covers businesses selling goods/services online in the PH — accurate business info, fair online transactions. Check DTI guidance on whether/how it applies to your SaaS. |
| **Consumer Act (RA 7394)**                                                                           | No false/misleading claims or deceptive sales practices.                                                                                                                 |
| **E-Commerce Act (RA 8792)**                                                                         | Electronic contracts/clicks ("I agree to the Terms") are legally valid.                                                                                                  |
| **Cybercrime Prevention Act (RA 10175)**                                                             | Relevant if you're attacked (reporting) and for content hosted on your platform.                                                                                         |
| **Intellectual Property Code (RA 8293)**                                                             | Images, fonts, logos, code you use must be licensed.                                                                                                                     |
| **Tax / business registration (BIR, DTI, LGU)** incl. **EOPT Act (RA 11976)**                        | You'll be selling monthly/yearly subscriptions (₱999–₱2,999/month) → you need to be registered and issue invoices.                                                       |

**Foreign laws:** If you only serve PH gyms and members, PH law is the focus. If you ever accept gyms/members in the EU (GDPR) or elsewhere, review those laws first.

## 2. Your role under the Data Privacy Act

This is important for a multi-gym SaaS:

| Party              | DPA role                                                                                                                     | Meaning                                                                    |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Each gym**       | Personal Information **Controller (PIC)**                                                                                    | Decides what member data is collected and why. Responsible to its members. |
| **You (the SaaS)** | Personal Information **Processor (PIP)** for member data; **PIC** for gym owners'/staff accounts and your own marketing site | Process member data only on the gym's instructions.                        |

Actions:

- [ ] **Data Processing Agreement (DPA)** between you and each gym — can be part of the Terms of Service the owner accepts at sign-up. Covers: you only process data per their instructions, security measures, sub-processors (Hostinger, email provider), breach notification to the gym, data return/deletion when they cancel.
- [x] Provide each gym a **template privacy notice for its members** (the gym publishes it on its gym website / front desk).
- [ ] **Designate a Data Protection Officer (DPO).** As a solo founder, that can be you. Publish a contact email (e.g. `privacy@yourdomain`).
- [ ] **NPC registration:** required if you meet NPC thresholds (e.g. 250+ employees, or processing **sensitive personal information of 1,000+ individuals**, or high-risk processing). With many gyms you may cross 1,000 members quickly — especially if you store age/birthdate or health data (see §3). **Check the current NPC circular** and register if it applies.
- [ ] Keep a simple **Record of Processing Activities** (the data inventory in §3 is a good start).

The implemented notice is in `resources/views/gym-site/privacy.blade.php`, with owner fields in **Gym website > Privacy** and the existing reviewed publication flow. Before enabling inquiries, the owner must supply privacy contact and accurate provider/location, backups, and member/accounting retention information, and acknowledge review. Inquiry records are retained for 365 days by the daily pruning job; consent does not authorize marketing. This provides the template; the remaining launch/legal checklists still apply. Structure follows the NPC's [right to be informed](https://privacy.gov.ph/the-right-to-be-informed/) guidance.

## 3. Data inventory — collect only what you need

Under the DPA, **age, health, and government IDs are "sensitive personal information" (SPI)** — they need stronger justification, consent, and protection. Avoid collecting them unless truly needed.

| Data                              | Whose    | Purpose                          | Needed?                                           | Sensitive?                                            | Suggested retention                        |
| --------------------------------- | -------- | -------------------------------- | ------------------------------------------------- | ----------------------------------------------------- | ------------------------------------------ |
| Name                              | Member   | Identify member                  | ✅ Required                                       | No                                                    | While member + gym retention period        |
| Mobile number                     | Member   | Contact, renewal reminders       | ✅ Required                                       | No                                                    | Same                                       |
| Email                             | Member   | Portal login, receipts           | Optional (some members have none)                 | No                                                    | Same                                       |
| **Birthdate / age**               | Member   | Calorie helper, age restrictions | ⚠️ Only if needed — consider "18+ yes/no" instead | **Yes (age)**                                         | Same                                       |
| Photo                             | Member   | Check-in identity verification   | ⚠️ Optional, gym setting                          | Biometric-like risk if used for face matching — don't | Delete on request                          |
| Address                           | Member   | —                                | ❌ Usually not needed                             | No                                                    | Don't collect                              |
| Emergency contact                 | Member   | Safety                           | Optional                                          | No (but third-party data)                             | Same                                       |
| **Height, weight, goals**         | Member   | Calorie/goal helper              | Optional, member-controlled                       | **Yes (health)**                                      | Member can delete anytime                  |
| **Medical conditions / injuries** | Member   | Coaching safety                  | ⚠️ Avoid in MVP                                   | **Yes (health)**                                      | —                                          |
| **Government ID numbers**         | Member   | —                                | ❌ Don't collect                                  | **Yes**                                               | —                                          |
| Attendance timestamps             | Member   | Attendance tracking              | ✅                                                | No                                                    | e.g. 2–3 years, then aggregate/delete      |
| Payment records                   | Member   | Accounting                       | ✅                                                | No                                                    | Per BIR record-keeping rules (often years) |
| Owner/staff name, email, role     | Staff    | Accounts                         | ✅                                                | No                                                    | While account active                       |
| Owner business details            | Gym      | Billing, invoices                | ✅                                                | TIN is SPI                                            | Per BIR rules                              |
| Contact form messages             | Visitors | Inquiries                        | ✅                                                | No                                                    | e.g. 1 year                                |
| IP addresses (logs)               | Everyone | Security                         | ✅                                                | No                                                    | e.g. 14–90 days                            |

- [ ] Review every form field: "If we didn't collect this, what breaks?" If nothing → remove it.
- [ ] Health fields (calorie helper) are **opt-in by the member**, not entered by staff by default.
- [ ] **Minors:** if a gym has members under 18, require parent/guardian consent.

## 4. Data subject rights (must be possible in the system)

Members have the right to be informed, access, object, rectify, erase/block, data portability, and to file complaints with the NPC.

- [ ] Member can view their own data in the portal.
- [ ] Gym can export a member's data (JSON/CSV) on request.
- [ ] Gym can correct member data (audit-logged).
- [ ] Gym can delete/anonymize a member (keep payment records required by tax rules, anonymize the rest).
- [ ] When a gym cancels its subscription: export all their data for them, then delete after a stated period (e.g. 30–90 days).

## 5. Breach notification

- [ ] If a breach involves sensitive personal information or data that could enable identity fraud, and is likely to cause real risk of serious harm → notify the **NPC and affected individuals within 72 hours** of knowledge (NPC Circular on Personal Data Breach Management — confirm current version).
- [ ] As a processor, notify the affected **gyms immediately** so they can meet their obligations.
- [ ] Keep a breach log, even for minor incidents.
- See incident steps in [security.md](../engineering/security.md#16-incident-response-write-it-before-you-need-it).

## 6. Cross-border data transfer

- [ ] Find out where your Hostinger server is physically located (selected data center). If outside the PH, mention in the privacy policy that data is stored/processed abroad with appropriate safeguards.
- [ ] Same for every third-party service (email, error tracking, analytics).

## 7. Required pages (platform site)

| Page                                               | URL                                         | Needed?                                                                                               |
| -------------------------------------------------- | ------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Privacy Policy                                     | `/privacy`                                  | ✅ Required                                                                                           |
| Terms of Service (incl. Data Processing Agreement) | `/terms`                                    | ✅ Required — owners accept at sign-up (checkbox, not pre-ticked), store accepted version + timestamp |
| Cookie Policy                                      | `/cookies` (or a section in Privacy Policy) | ✅ Recommended                                                                                        |
| Refund & Cancellation Policy                       | `/refund`                                   | ✅ Required — you're selling subscriptions                                                            |
| Acceptable Use Policy                              | section in Terms                            | ✅ Recommended — gyms can't post illegal/infringing content on their sites                            |
| Contact / business details                         | `/contact` + footer                         | ✅ Required                                                                                           |

**Each gym website** (generated by your platform) should show:

- [ ] Gym's own privacy notice (from your template, editable by the gym)
- [ ] Gym's own contact details and business name
- [ ] Small "Powered by [Your SaaS]" link to your platform's terms

### Privacy Policy must cover

Who you are + DPO contact · what data · why (purpose) · legal basis (consent, contract, legal obligation) · who you share with (sub-processors list) · where it's stored · how long · how it's protected · data subject rights + how to exercise them · cookies · how to complain to the NPC · last-updated date.

### Refund policy — decide these

- Free trial length (if any) and what happens when it ends.
- Monthly: cancel anytime, access until end of paid period, **no partial-month refunds** (common) — or pro-rated (friendlier).
- Annual (if offered): refund rules.
- Refunds for downtime / outages (service credits?).
- Data access after cancellation (export window).
- How to request a refund + how long it takes.

## 8. Cookies — do you need a consent banner?

The PH has no separate "cookie law"; cookies that identify a person count as personal data under the DPA, so consent and transparency principles apply.

| Cookie                                            | Set by          | Purpose                    | Consent needed?                                     |
| ------------------------------------------------- | --------------- | -------------------------- | --------------------------------------------------- |
| `{app}_session`                                   | Laravel         | Keeps you logged in        | No — strictly necessary (disclose in cookie policy) |
| `XSRF-TOKEN`                                      | Laravel/Inertia | Security (CSRF)            | No — strictly necessary                             |
| `remember_web_*`                                  | Laravel         | "Remember me"              | No — user chose it                                  |
| Google Analytics / Meta Pixel / ads               | Third party     | Tracking                   | **Yes — ask first, don't load until accepted**      |
| Embedded YouTube / Google Maps / Facebook widgets | Third party     | They set their own cookies | **Yes, or use privacy-friendly embeds**             |

**Decision:** if you use **only** Laravel's essential cookies → **no banner needed**, just the cookie policy. The moment you add analytics, pixels, or third-party embeds → add a consent banner with "Accept" and "Reject" equally visible, and don't load those scripts before consent.

Privacy-friendly options: self-hosted or cookieless analytics (e.g. Plausible, Umami), `youtube-nocookie.com` embeds, a static map image linking to Google Maps instead of an embedded map.

## 9. Third-party integrations audit

For **each** service, record it here and in the privacy policy (sub-processor list):

| Service                                          | Purpose             | Data sent                                    | Location | Cookies? | DPA/terms checked                |
| ------------------------------------------------ | ------------------- | -------------------------------------------- | -------- | -------- | -------------------------------- |
| Hostinger                                        | Hosting + database  | All app data                                 | _TBD_    | —        | [ ]                              |
| Email/SMTP provider                              | Transactional email | Name, email, message                         | _TBD_    | —        | [ ]                              |
| Error tracking (Sentry/Flare)                    | Bug reports         | IP, user ID (scrub PII)                      | _TBD_    | —        | [ ]                              |
| Google Fonts (if loaded from Google)             | Fonts               | **Visitor IP to Google**                     | US       | —        | ⚠️ Prefer **self-hosting fonts** |
| Google Maps embed                                | Gym location        | Visitor IP, cookies                          | US       | Yes      | [ ]                              |
| Analytics (if any)                               | Traffic stats       | IP, behavior                                 | _TBD_    | Maybe    | [ ]                              |
| Payment gateway (later: PayMongo / Xendit, etc.) | Online payments     | Payment details (handled by them, never you) | _TBD_    | —        | [ ]                              |
| SMS provider (later)                             | Reminders           | Mobile numbers                               | _TBD_    | —        | [ ]                              |

- [ ] Check page source / browser Network tab on every page for scripts you didn't expect.
- [ ] Gym owners cannot embed arbitrary third-party scripts on their sites (they'd add tracking you're responsible for hosting).

## 10. Consent wording for forms (drafts — adapt and have reviewed)

Rules: **no pre-ticked boxes**, separate consent for optional/marketing uses, plain language, link to the full notice, store _what_ was consented to, _when_, and _which version_.

**Gym owner sign-up (platform):**

> ☐ I agree to the [Terms of Service](/terms) (including the Data Processing Agreement) and have read the [Privacy Policy](/privacy).
>
> ☐ _(optional)_ Send me product updates and tips by email. You can unsubscribe anytime.

**Member registration (entered by gym staff at the front desk — gym shows/gives the notice):**

> [Gym Name] collects your name, optional mobile number and email, and membership details to manage your membership, record attendance and payments, and contact you about renewals. Your data is stored securely using [Your SaaS] on our behalf. See our [Privacy Notice](/privacy) for your rights, including access and deletion. Contact: [gym email/phone].
>
> ☐ Member has been informed and agrees to the processing above. _(staff checkbox — recorded with staff name + timestamp)_
>
> ☐ _(optional)_ Member agrees to receive promotions by SMS/email.

**Member portal — calorie & goal helper (health data):**

> To estimate your daily energy needs, we'll use your age, height, weight, equation choice, activity level, and goal. This is **optional** and for general information only — it is **not medical advice**. Only your verified member portal displays saved inputs; there is no staff or coach viewing screen. You can delete saved inputs and withdraw consent anytime, including when this helper is disabled. Saved inputs are encrypted; retained backups follow your gym's privacy notice. The helper is for adults aged 18 or older who are not pregnant or breastfeeding.
>
> By clicking **Save and estimate**, I agree to [Gym Name] storing these inputs for this estimate. I can delete them anytime.

**Public contact / inquiry form (gym website):**

> We'll use your name, contact details, and message only to respond to your inquiry. See our [Privacy Notice](/privacy).

**Minor members:**

> ☐ I am the parent/legal guardian of this member and agree to the processing described above.

## 11. Business registration & invoicing

- [ ] Register the business: **DTI** (sole proprietorship business name) or **SEC** (corporation), **Barangay** clearance, **Mayor's/Business permit**, **BIR** registration.
- [ ] Issue BIR-compliant **sales invoices** for each subscription (₱999 / ₱1,999 / ₱2,999 monthly, or yearly) (under the EOPT Act, invoices replaced official receipts for services — confirm current BIR rules).
- [ ] **Payment records generated by your app for gyms are not official BIR invoices** unless the system is registered/accredited with BIR for that purpose. Label them "Payment Record / Acknowledgment — not an official invoice" and say so in the Terms.
- [ ] Put your real business name, address, contact email, and phone on the platform site (see [accessibility-and-content.md](../design/accessibility-and-content.md#3-business-details)).

## 12. Employment / IP

- [ ] Review your current employment contract for side-project, moonlighting, and IP clauses.
- [ ] Build only on personal devices, personal accounts, and personal time. No employer code, data, or tools.

## 13. Official sources to check

- National Privacy Commission — privacy.gov.ph (DPA, IRR, circulars on registration and breach management)
- Department of Trade and Industry — dti.gov.ph (business name, Internet Transactions Act / E-Commerce Bureau)
- Bureau of Internal Revenue — bir.gov.ph (registration, invoicing, EOPT)
