# Security Checklist

Stack: Laravel + Vue 3 + Inertia, MySQL, Hostinger shared hosting.
Rule of thumb: **assume every request is from an attacker who belongs to a different gym.**

Related: [performance-and-reliability.md](../engineering/performance-and-reliability.md) · [legal-and-privacy-ph.md](../legal/legal-and-privacy-ph.md)

---

## 1. Tenant isolation (most important)

A leak between gyms is the worst thing that can happen to this product.

- [ ] Every gym-owned model uses the `BelongsToGym` trait (global scope + auto-fill `gym_id` on create).
- [ ] **Never accept `gym_id` from the request.** It always comes from the logged-in user's current gym.
- [ ] Every controller action uses a Policy (`$this->authorize(...)`) — the scope alone is not enough for actions like "staff of Gym A edits a plan".
- [ ] Route model binding resolves through the scope (Gym B's ID → `404`, not `403`, so IDs aren't confirmed to exist).
- [ ] No `DB::table()` / raw SQL on gym-owned tables without `where('gym_id', ...)`. Review every `withoutGlobalScopes()` call.
- [ ] Queued jobs and scheduled commands set the gym context explicitly (there's no logged-in user in a job).
- [ ] Cache keys include the gym ID: `"gym:{$gymId}:dashboard-stats"`, never just `"dashboard-stats"`.
- [ ] Uploaded files are stored under a per-gym path: `gyms/{gym_id}/...`.
- [ ] **Feature test for every endpoint**: user from Gym B gets `404` on Gym A's record (view, update, delete, export).

## 2. Inertia-specific: props are public

Everything you pass to an Inertia page ends up in the browser (page source / network tab), even if Vue doesn't display it.

- [ ] Never pass whole models: `Inertia::render('Members/Show', ['member' => $member])` can leak hidden columns, tokens, notes.
- [ ] Pass explicit arrays or API Resources with only the fields the page needs.
- [ ] Shared props (`HandleInertiaRequests::share`) contain only the minimum: user name, role, current gym name. No full user model.
- [ ] Set `$hidden` on models for sensitive columns as a second layer (`password`, `remember_token`, `two_factor_secret`, token hashes).

## 3. Authentication

- [ ] Use the Laravel starter kit auth (hashed passwords with bcrypt/argon2 — never store or log plain passwords).
- [ ] Password rules: `Password::min(8)->uncompromised()` (checks known breached passwords).
- [ ] Login throttling (built in) — confirm it's active. Lock-out message must not reveal whether the email exists.
- [ ] Password reset / "forgot password" responses are identical whether or not the email exists.
- [ ] Two-factor authentication available; **required for owners and platform admin**.
- [ ] Session ID regenerated on login (built in); invalidated on logout and on password change ("log out other devices").
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` in production.
- [ ] Reasonable session lifetime for staff on shared front-desk computers (e.g. 120 min) + visible logout button.
- [ ] Email verification for owner sign-up.
- [ ] Removing a staff member immediately revokes their access (delete sessions / tokens).

## 4. Authorization & roles

- [x] Add member requires an active, current-gym catalog plan, bounded start date and UUID submission key. SaveMember uses the existing locked membership assignment inside its member/audit transaction; assignment failure rolls back the new member and audits. Request data cannot select another gym's/archived plan or change the initial operation from assign. Existing profile edits do not create subscriptions; member capacity, branch writes, agreed centavo price/duration and consent checks remain.
- [x] Add member excludes client home_branch_id and derives registration branch inside SaveMember's existing gym/subscription transaction: current view/manager branch, otherwise active main/oldest branch within the tenant. Read-only selections and missing active branches remain blocked. Profile edits retain tenant/manager-scoped branch validation; gym-wide attendance access is unchanged.
- [x] Gym-wide attendance membership: authorize the check-in destination branch before using the existing same-gym `CurrentGym::runAs()` for member/QR/membership reads and the locked attendance transaction. This retains the tenant scope and restores manager/view context on success or error. Managers may check in visitors only at their assigned branch; profile/subscription/QR management stays home-branch restricted. Foreign members, QR credentials and branches remain rejected.
- [ ] Role-permission matrix written down (owner / staff / coach / member / platform admin) and enforced via Policies and Gates.
- [ ] Authenticated members can only see **their own** private portal record — test it. The separate public Check membership lookup is limited to the five approved fields below.
- [ ] Staff cannot change their own role or create owners.
- [ ] Platform admin area on a separate route group with its own middleware + 2FA; impersonation (if added) is logged.
- [ ] Hide UI buttons by permission **and** enforce on the server — hiding a button is not security.

## 5. Input handling

- [ ] All input through Form Requests. Use `$request->validated()`, **never** `$request->all()` into `create()`/`update()`.
- [ ] Models use `$fillable` (whitelist). `gym_id`, `role`, `status` (where sensitive) are not fillable from user input.
- [ ] SQL injection: Eloquent/query builder bindings only. In `whereRaw`/`orderByRaw` use `?` bindings.
- [ ] Sort/filter params from the URL are whitelisted: `in_array($sort, ['name', 'expires_at'])`.
- [ ] Validate dates, money (integers in centavos, not floats), and enums server-side.

## 6. XSS (gym website builder is the risk)

- [ ] Vue `{{ }}` escapes automatically — **avoid `v-html`**. If unavoidable, sanitize first.
- [ ] Blade: use `{{ }}`, avoid `{!! !!}` on anything user-provided.
- [ ] Rich text from the website editor is sanitized **on save** with an allowlist (e.g. `mews/purifier` / HTMLPurifier).
- [ ] Gym owners cannot inject `<script>`, inline event handlers, `javascript:` links, or `<iframe>` (except an allowlisted embed like Google Maps if needed).
- [ ] Links in gym content get `rel="noopener noreferrer"` when `target="_blank"`.

## 7. File uploads (logos, banners, gallery)

- [ ] Validate: `image|mimes:jpg,jpeg,png,webp|max:4096` (KB). **No SVG uploads** (SVG can contain scripts).
- [ ] Re-encode / resize images server-side (e.g. Intervention Image) — strips hidden payloads and EXIF location data.
- [ ] Store with random filenames (`$file->store(...)`), never the user's filename.
- [ ] Never store uploads in a folder where PHP files execute. Make sure `.php` in storage can't run.
- [ ] Per-gym storage quota (shared hosting has disk + inode limits).

## 8. QR codes & member portal

- [ ] Tokens: `Str::random(48)` or longer; store **only a hash** (`hash('sha256', $token)`) in `member_qr_tokens`.
- [ ] Separate tokens for attendance and portal; each has `revoked_at` and optional `expires_at`.
- [ ] Reissuing a QR revokes the old one (in a DB transaction).
- [ ] QR URL contains no member ID, name, or gym-internal data.
- [ ] Portal QR shows nothing private until verification passes (login or one-time code).
- [ ] Rate-limit QR resolution (e.g. 10/min per IP) and log failed lookups — catches token guessing.
- [ ] Check-in endpoint only usable by authenticated staff of that gym.
- [x] Guest Check membership accepts an exact saved phone number or email within the resolved gym, for active profiles only. Member-ID and full-name input are prohibited server-side, including alongside a valid ID/code. Phone punctuation is normalized and email matching is case-insensitive. Return only server-masked name/plan/start date/valid-through/status; no full name, contacts, account or payment details. Mask each name word before serialization, including short and accented names. Missing/foreign/inactive contacts receive the same generic error. Keep the shared ten-per-minute resolution limit, CSRF, no-store/noindex/no-referrer responses and cleared client results. The lookup itself grants no login or attendance access; an explicit QR-generation action is separately feature-gated, accepts the same contact lookup, returns one opaque attendance credential once, refuses to replace an existing QR, and never logs the secret or hash. Authorized staff can recover current QR payloads through protected no-store actions; payloads are encrypted at rest and legacy hash-only cards require reissue. Legacy QR/check-code lookups remain status-only. Keep downloaded cards private.

## 9. CSRF, headers, HTTPS

- [ ] CSRF: Inertia + Laravel handle it via the `XSRF-TOKEN` cookie — don't disable `VerifyCsrfToken` for convenience.
- [ ] Force HTTPS: `URL::forceScheme('https')` in production + Hostinger "Force HTTPS".
- [ ] Security headers middleware:
    - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    - `X-Content-Type-Options: nosniff`
    - `X-Frame-Options: DENY` (dashboard) — prevents clickjacking
    - `Referrer-Policy: strict-origin-when-cross-origin`
    - `Content-Security-Policy` — start in report-only mode, then enforce
    - `Permissions-Policy: camera=(self)` (camera needed for QR scanning), others off
- [ ] CORS: no wildcard origins; the app doesn't need cross-origin API access by default.

## 10. Rate limiting

| Endpoint                                | Suggested limit                                                                                                                           |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Login, 2FA, password reset              | 5/min per email + IP                                                                                                                      |
| QR / portal token resolution            | 10/min per IP                                                                                                                             |
| Public contact / inquiry forms          | 3/min per IP + honeypot or captcha                                                                                                        |
| Request-access form (`/request-access`) | 3/hour per IP + 30/hour overall, honeypot, too-fast check (encrypted start time), 1 request per email per 24h, auto-prune (`model:prune`) |
| "Try demo"                              | 5/hour per IP, max 200 active demos, auto-delete after 24h                                                                                |
| Invitation links (staff + owner)        | 20/min per IP; tokens hashed, single-use, expire in 7 days                                                                                |
| Member search (staff)                   | 60/min per user                                                                                                                           |
| Exports / report generation             | 5/min per user                                                                                                                            |
| General authenticated API               | 120/min per user                                                                                                                          |

## 11. Secrets & configuration (production)

- [ ] `APP_ENV=production`, **`APP_DEBUG=false`** (debug mode shows your `.env` values on error pages).
- [ ] Domain document root points to `public/` — `.env`, `.git`, `storage/`, `vendor/` must not be reachable by URL. Test: `https://yourdomain/.env` → 404.
- [ ] `.env` never committed. `.env.example` has no real values.
- [ ] `APP_KEY` generated once per environment and backed up securely (losing it breaks encrypted data and sessions).
- [ ] Separate MySQL user per project with access to **only** its database and a long random password.
- [ ] No production credentials in AGENTS.md, README, issues, or chat.

## 12. Hostinger / shared hosting account

- [ ] 2FA on the Hostinger account and on GitHub.
- [ ] SSH with keys, not passwords.
- [ ] Other sites in the same hosting account can read this app's files if compromised — keep every site updated, delete unused sites/WordPress installs.
- [ ] File permissions: `storage/` and `bootstrap/cache/` writable; everything else not writable by the web user where possible.
- [ ] Directory listing disabled.

## 13. Dependencies

- [ ] `composer audit` and `npm audit` before each release.
- [ ] Enable GitHub Dependabot alerts.
- [ ] Keep Laravel and PHP on supported versions.
- [ ] Only install well-maintained packages; check last release date and download count.

## 14. Logging, audit, monitoring

- [ ] Audit log (who, what, when, before/after) for: membership changes, payments (create/edit/void), role changes, QR reissue, website publish, member data export/deletion.
- [ ] Audit logs are append-only for gym users (no edit/delete UI).
- [ ] Never log passwords, tokens, full request bodies with personal data.
- [ ] Error tracking (e.g. Sentry, Flare, or Laravel Nightwatch) with personal data scrubbing.
- [ ] Alert on: spikes in failed logins, many QR lookup failures, 500 errors.

## 15. Backups

- [ ] Daily database backup + Hostinger backups.
- [ ] One copy stored **outside** Hostinger (encrypted).
- [ ] Backups contain personal data — encrypt them and limit who can access them.
- [ ] **Test a restore** before launch and every few months.

## 16. Incident response (write it before you need it)

1. Contain: take the affected feature offline (`php artisan down --secret=...`), rotate passwords/keys.
2. Assess: what data, which gyms, how many members, since when (use audit logs).
3. Notify: affected gyms immediately; **NPC and affected individuals within 72 hours** if the breach meets the Data Privacy Act criteria — see [legal-and-privacy-ph.md](../legal/legal-and-privacy-ph.md#breach-notification).
4. Fix and add a test so it can't happen again.
5. Write a short post-incident note.

## 17. Before-launch quick test

Local verification on 2026-10-06; evidence and deployment limits are in [launch verification](launch-verification.md). Checked entries describe local tests, not deployed-host certification.

- [x] `/.env`, `/.git/config`, `/storage/logs/laravel.log` return 404 in tests and live localhost requests; vendor source also returns 404.
- [x] A test-only production-mode exception with debug disabled returns a generic 500 without its secret marker or source file. No probe route is shipped.
- [x] Tenant isolation sweep: foreign gym routes return 404/403.
- [x] Portal member authentication cannot authorize `/members`, `/payments` or the dashboard.
- [x] Attendance/portal QR regression tests reject revoked/replaced credentials.
- [x] Website upload tests reject PHP disguised as JPEG.
- [x] Website content tests verify escaped text and sanitized rich content without executable scripts.
- [ ] Run [securityheaders.com](https://securityheaders.com), SSL Labs, cookie/header and file-access checks on the deployed domain (Phase 7 task 5).
