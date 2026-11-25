# Phase 7 local launch verification

This record covers tasks 1–4 locally. It is not a production certification. Deployment, the live domain/SSL checks, backups and the real-gym pilot remain tasks 5–6.

## Legal pages and agreement

See [platform policies](../legal/platform-policies.md). Five policy pages, the footer and unchecked signup/portal agreement modal are implemented. Contact details supplied by Rhodwick are configured locally. Registered business details, tax wording, actual provider/retention facts and commercial cancellation/refund decisions still require operator completion before `LEGAL_PUBLISHED=true`. Production agreement is blocked while policies remain drafts.

## Security quick test

- Private environment, Git, log and vendor paths return 404 in feature tests and live localhost HTTP checks. The document root must still be `public/` on Hostinger.
- A test-only production exception with debug disabled returns a generic 500 without its secret marker or source filename. No error-probe endpoint is shipped.
- The complete tenant isolation sweep checks foreign gym routes; portal authentication cannot authorize dashboard/member/payment routes. Central routes explicitly use the `web` guard.
- Existing attendance/portal tests cover revocation and replaced QR credentials. Upload tests reject PHP disguised as JPEG. Website tests cover escaped text and sanitized rich content.
- Shared Inertia user props use an explicit field list, excluding credentials, privileged flags and agreement metadata. Portal props continue to omit staff identity.
- Responses carry nosniff, frame denial, referrer and camera permission headers. Production HTTPS adds HSTS. CSP is **report-only**, allowing existing inline scripts/styles; it is not enforced protection against every script source. Portal no-referrer/no-store headers remain intact.
- Session cookies default to secure in production, HTTP-only and SameSite=Lax. Set and verify these on the actual HTTPS deployment.
- **Pending deployment:** securityheaders.com and SSL Labs require the real HTTPS domain. These scans and hosting file-access checks must be run in task 5. Local HTTP cannot establish wildcard SSL or hosting security.

## Retained fictional dataset

`php artisan db:seed --class=LoadTestingSeeder` creates **Fictional Load Test Gym**, isolated from Iron House and other gyms: two branches, 1,000 members/periods, 250 partial offline payments and 5,000 baseline check-ins. It also seeds a public fictional website, seven schedules and an assigned workout. Existing rows/manual edits are preserved on rerun. Production execution is blocked. No real privacy/account agreement is claimed for seeded members; portal agreement follows the actual login workflow.

The fixture remains in the local database. Load runs add a few check-ins; keyboard verification adds a fictional one-cent offline payment and publishes a clearly fictional description. The temporary keyboard-created member and separate signup fixtures are cleaned up; the 1,000-member dataset remains.

- Main login **Development accounts**: `owner@loadtest.invalid`, `staff@loadtest.invalid`, `coach@loadtest.invalid`, `manager@loadtest.invalid`; password `password`. Credentials appear only locally and only after this gym exists.
- Portal: `http://fictional-load-test.localhost:8000/portal/login`, `member0001@loadtest.invalid` through `member0005@loadtest.invalid`; password `password`.
- Owner/staff: Members/search/pagination, Attendance, Payments, Reports, Coach calendar and Workouts. Managers see the main branch. Portal member0001 has an assigned workout; remaining portal logins can demonstrate the first agreement screen.

## HTTP load measurements

The native Node runner `scripts/load-test.mjs` authenticates owner/staff/member sessions, warms eight read scenarios, measures mixed requests, creates check-ins and asserts duplicate prevention. It refuses remote hosts, limits concurrency/rounds, checks response errors and the 200 KB JSON budget, and returns a failing exit code when timing budgets are missed.

Final baseline: 1,000 members, **two concurrent clients / four local PHP workers**, 30 samples per read scenario (240 reads) and eight check-ins. PHP 8.4, local MariaDB, database sessions/cache, debug disabled, OPcache explicitly loaded and isolated cached config/routes/views. **Zero HTTP errors; duplicate check passed; JSON bodies below 8 KB.**

| Scenario       | p95 (ms) | Budget (ms) |
| -------------- | -------: | ----------: |
| Public website |      138 |         200 |
| Dashboard      |      381 |         500 |
| Members        |      272 |         500 |
| Member search  |      170 |         500 |
| Attendance     |      178 |         500 |
| Payments       |      310 |         500 |
| Reports        |      241 |         500 |
| Member portal  |      173 |         500 |
| Check-in       |      147 |         300 |

Raw report: `storage/load-test-results.json`. The **four-client** run is kept as `storage/load-test-four-clients.json`: all HTTP/payload checks pass, but public p95 **223 ms** misses 200 ms; dashboard 482 ms, check-in 158 ms and other timings pass. The original single-process development-server results are kept in `storage/load-test-dev-single-worker.json`; queueing and unloaded OPcache made it much slower. These results do **not** prove 1,000-concurrent-user or Hostinger capacity. Repeat on a production-like temporary environment before launch; never stress a shared Hostinger account.

Fixes prompted by measurement: member list now eager-loads home branches; a regression test caps branch-query growth. The additive membership summary covering index improves period selection; the expiring dashboard count uses one relationship predicate instead of repeating it. Existing status/tenant/manager tests remain green.

Repeat the default development-server check with `node scripts/load-test.mjs`. It deliberately reports missed timing budgets on an unoptimized server. For the optimized baseline, use the following **only in temporary test terminals**; cached files are isolated so ordinary development/tests keep their configuration:

```powershell
$env:APP_DEBUG = 'false'
$env:APP_CONFIG_CACHE = 'storage/load-server-cache/config.php'
$env:APP_ROUTES_CACHE = 'storage/load-server-cache/routes.php'
$env:APP_EVENTS_CACHE = 'storage/load-server-cache/events.php'
New-Item -ItemType Directory -Path storage/load-server-cache -Force | Out-Null
php artisan optimize
```

Start four PHP workers in separate terminals, each with those same environment settings. From `public/`, run the command below with port **8011, 8012, 8013 or 8014**. The OPcache DLL path is specific to this local PHP install; verify the extension path elsewhere.

```powershell
php -d zend_extension=C:/php84/ext/php_opcache.dll -d opcache.enable_cli=1 -d opcache.jit=off -S 127.0.0.1:8011 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

In a separate root terminal:

```powershell
$env:LOAD_PORTS = '8011,8012,8013,8014'
$env:LOAD_CONCURRENCY = '2'
$env:LOAD_ROUNDS = '30'
$env:LOAD_SERVER_PROFILE = 'OPcache loaded, APP_DEBUG=false, isolated cached config/routes/views'
node scripts/load-test.mjs
```

The runner dispatches to free workers and preserves the canonical localhost Host header. Stop temporary workers afterward; leave the database fixture. Repeat at higher concurrency to measure limits; do not present the two-client pass as the larger target.

## Accessibility

**78 axe snapshots pass with zero detected violations or page overflow**: 39 pages/states at 1280px and 360px, using WCAG A/AA (2.0/2.1/2.2) and best-practice rules. Scope: landing, all five policies, invited signup/policy modal, request access/login, dashboard, member list/add/view/memberships, record/payment overview, membership plans, staff, branches/settings, check-in/its open dropdown, attendance, reports, workouts/calendar, website editor/all section tabs, public gym, portal login/membership/workout/status check.

Chrome keyboard-only checks pass for login, add member, offline payment, check-in and save/publish. They verify skip links, logical navigation, password toggle, invalid login/mobile/overpayment error focus and descriptions, member-row selection/chips, publish dialog focus trapping/Escape/return, and 200% text with 640px dashboard/public/portal reflow plus 360px member login. The mobile screenshot was visually inspected. No browser page errors were recorded.

Fixes: shared main/navigation landmarks and skip links; contrast/definition lists; labels containing visible action/member details; accessible member combobox/dialog; error association/announcement/focus after disabled fields unlock; reduced-motion dialogs and larger close controls. Public pricing now describes implemented features, omits unsupported gateway/reminder/staff-log/Excel/PDF/per-branch-site claims, and displays configurable tax wording without inventing VAT treatment.

Artifacts: `storage/browser-check/phase7-axe-results.json`, `phase7-keyboard-results.json` and `phase7-keyboard-mobile.png`. Browser harnesses are kept alongside them; their local-only PHP helpers clean only their temporary fictional fixtures. Tests that temporarily modify public assets must run separately from browser checks.

**Validation:** full suite 657 passed / 5,172 assertions; PHP formatting, frontend lint/format, types and production build passed. These checks do not establish full WCAG certification. Formal NVDA/assistive-device and physical-camera testing, actual operator/brand content review, and deployed-host checks remain before launch. Optional calorie/security-settings flows are outside this axe snapshot scope.
