# Performance & Reliability — "What Could Crash It?"

**Target:** stay up and responsive with **1,000 users active at the same time**; design so that growing toward 100k total users is a hosting upgrade, not a rewrite.

Stack: Laravel + Vue 3 + Inertia, MySQL, Hostinger shared hosting.

Related: [security.md](../engineering/security.md)

---

## 0. Reality check: shared hosting and 1,000 concurrent users

- "1,000 users at the same time" ≠ 1,000 requests per second. Users click, read, and wait. 1,000 active users ≈ roughly **30–100 requests/second** at peak.
- Shared hosting limits how many PHP processes can run **at once** per account (and CPU/RAM). When the limit is hit, extra visitors get **503 / "resource limit reached"** errors — that's the "crash" you'll see first.
- Realistic gym traffic is far lower: 20 gyms × a morning rush of ~30 check-ins/hour is tiny. Your biggest spikes will come from **public gym websites** (promo posted on Facebook) and **members checking the portal** at the same time.
- Strategy: make every request **cheap and fast**, cache public pages, and **load test**. When tests or monitoring show you're near the limit → move to a VPS (the code below works the same there).

## 1. Performance budget (enforce these)

| Metric                       | Target                       |
| ---------------------------- | ---------------------------- |
| Staff check-in request       | < 300 ms                     |
| Dashboard / list pages (p95) | < 500 ms                     |
| Public gym website page      | < 200 ms (served from cache) |
| DB queries per page          | ≤ 15 (flag anything above)   |
| Rows loaded per request      | ≤ 100 (paginate)             |
| Inertia page payload         | < 200 KB JSON                |
| Memory per request           | < 64 MB                      |

---

## 2. Crash scenarios and how to prevent them

### Database

| Scenario                                                           | What happens                                                                  | Prevention                                                                                                                                                                                                                                                     |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **N+1 queries** — members list loads each member's plan separately | 50 members = 51 queries; 1k users → DB overloaded                             | Eager load: `Member::with('subscription.plan')`. Add `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider` so it throws in dev.                                                                                                         |
| **Missing indexes**                                                | Queries scan whole tables; fine with 100 rows, dead with 500k attendance rows | Composite indexes starting with `gym_id`: `members(gym_id, status)`, `member_subscriptions(gym_id, end_date)`, `attendance_records(gym_id, checked_in_at)`, `attendance_records(member_id, checked_in_at)`, `payments(gym_id, paid_at)`. Check with `EXPLAIN`. |
| **Loading everything** — `Member::all()` / `->get()` on lists      | Memory limit exceeded → 500 error                                             | Always `->paginate(25)` or `->cursorPaginate()`. Never send unbounded arrays as Inertia props.                                                                                                                                                                 |
| **Search with `LIKE '%juan%'`**                                    | Can't use index → full table scan on every keystroke                          | Debounce search in Vue (300 ms), min 2 chars, prefer prefix `LIKE 'juan%'` or MySQL FULLTEXT index; limit results to 20.                                                                                                                                       |
| **Huge reports** — "revenue for all time"                          | Query runs for minutes, blocks other requests                                 | Limit date ranges (max 1 year), aggregate with `SUM/COUNT ... GROUP BY` in SQL (not PHP loops), cache results for 5–15 min.                                                                                                                                    |
| **Too many DB connections**                                        | Shared MySQL has a per-user connection cap → "Too many connections"           | Keep requests short; no long-running requests; don't open extra connections.                                                                                                                                                                                   |
| **Long migration on a big table**                                  | Table locked during deploy → app hangs                                        | Add columns as nullable, backfill in chunks via a job, run heavy migrations off-peak (late night PH time).                                                                                                                                                     |
| **Database grows forever**                                         | Slower queries, disk quota hit                                                | Prune: `sessions`, `jobs`/`failed_jobs`, old logs, expired tokens (`model:prune` scheduled daily). Decide attendance retention.                                                                                                                                |

### Race conditions & double actions

| Scenario                                         | What happens                                            | Prevention                                                                                                                          |
| ------------------------------------------------ | ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Staff double-clicks "Record payment"             | Two payments saved                                      | Disable button while submitting (Inertia `form.processing`); server-side idempotency key / unique reference; wrap in a transaction. |
| Two staff renew the same member at the same time | Overlapping memberships (MySQL can't block this itself) | `DB::transaction` + `lockForUpdate()` on the member row, then check overlap, then insert.                                           |
| QR scanned twice quickly                         | Duplicate check-ins                                     | Check "already checked in within X minutes" inside a transaction with a lock, or a unique key on `(member_id, check_in_window)`.    |
| QR reissued while being scanned                  | Old token still accepted                                | Revoke + create in one transaction; look up only `whereNull('revoked_at')`.                                                         |

### Background work (cron-only queue on shared hosting)

| Scenario                                                  | What happens                                       | Prevention                                                                                                                                   |
| --------------------------------------------------------- | -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| **Sending emails during the request**                     | Slow SMTP → page hangs/times out                   | Always queue mail (`ShouldQueue`).                                                                                                           |
| **Daily "expire memberships" job loads all members**      | Out of memory, times out at max execution time     | `chunkById(500)`, or a single `UPDATE ... WHERE end_date < today` query.                                                                     |
| Scheduled task runs longer than 1 minute                  | Next cron starts a second copy → double processing | `->withoutOverlapping()` on every scheduled task.                                                                                            |
| Queue backlog grows (cron runs worker only once a minute) | Emails delayed for hours                           | Small, fast jobs; `queue:work --stop-when-empty --max-time=50`; monitor `jobs` table size.                                                   |
| Job fails forever and retries endlessly                   | Wasted CPU, spam                                   | Set `$tries`, `$backoff`, `$timeout` on jobs; check `failed_jobs`.                                                                           |
| **Email sending limit** — hosting SMTP caps daily emails  | Reminder emails silently stop                      | Know your plan's daily email limit; use a transactional email provider (e.g. Brevo, Mailgun, Amazon SES) once volume grows; batch reminders. |

### Requests & resources

| Scenario                                                     | What happens                                                        | Prevention                                                                                            |
| ------------------------------------------------------------ | ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Large image uploads                                          | Memory/time limits hit; disk/inode quota fills                      | Max 4 MB, resize to max ~1600px + WebP, per-gym quota.                                                |
| CSV export of 50k rows in a request                          | Timeout / out of memory                                             | Queue the export, stream with `lazy()`/`cursor()`, email or notify when ready.                        |
| Calling an external API (SMS, payment, maps) without timeout | One slow provider hangs all your PHP processes → site down          | `Http::timeout(5)->retry(2, 200)`; queue it when possible.                                            |
| Bots/scrapers hammer public gym sites                        | Uses up process limit → dashboard dies too                          | Cache public pages; rate limit; put **Cloudflare (free plan)** in front for caching + bot protection. |
| Brute-force on login/QR endpoints                            | CPU spent hashing passwords                                         | Rate limits (see [security.md](../engineering/security.md#10-rate-limiting)).                         |
| Logs fill the disk                                           | Disk quota full → app can't write sessions/cache → everything fails | `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning` in production.                          |
| File-based cache/sessions on shared hosting                  | Thousands of small files → inode limit                              | `SESSION_DRIVER=database`, `CACHE_STORE=database` (Redis later on a VPS).                             |

### Time & dates (classic silent bugs)

| Scenario                                   | What happens                                                     | Prevention                                                                                                                                     |
| ------------------------------------------ | ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Server in UTC, gym in Manila               | Membership "expires" 8 hours early/late; attendance on wrong day | Store UTC (`APP_TIMEZONE=UTC`), display in `Asia/Manila` (per-gym timezone setting). Define clearly: "expires end of day Oct 31, Manila time". |
| Month-length math (`+1 month` from Jan 31) | Expiry lands on Mar 3                                            | Use Carbon `addMonthNoOverflow()`; write tests for Jan 31, Feb 29, Dec 31.                                                                     |

### Frontend (Vue + Inertia)

| Scenario                                                     | What happens                                   | Prevention                                                                                                 |
| ------------------------------------------------------------ | ---------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Huge props on every page (shared props loading lots of data) | Slow pages, high server load                   | Keep shared props tiny; use Inertia **lazy/deferred props** and **partial reloads** (`only: ['members']`). |
| Rendering 2,000 rows in a table                              | Browser freezes on cheap front-desk PCs/phones | Server-side pagination always.                                                                             |
| Stale JS after deploy                                        | Users get broken pages until refresh           | Inertia asset versioning (built in with Vite manifest) — keep it enabled.                                  |
| Search fires a request per keystroke                         | 10× load                                       | Debounce + cancel previous request.                                                                        |
| QR scanner on slow mobile network                            | Repeated scans, duplicate requests             | Show "Checking in…" state, disable re-scan until response.                                                 |

### Deploys & operations

| Scenario                                                 | What happens                | Prevention                                                                                              |
| -------------------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------------------- |
| Deploy mid-day breaks the site                           | Gyms can't check in members | `php artisan down --secret=...` during deploy; deploy off-peak; keep the previous release to roll back. |
| Forgot `php artisan optimize`                            | Slower on every request     | Deploy script always runs `optimize` (config/route/view cache).                                         |
| `APP_DEBUG=true` in production                           | Slow + leaks secrets        | Deploy checklist.                                                                                       |
| Migration fails halfway (MySQL has no transactional DDL) | Half-migrated DB            | Small migrations (one change each); backup before deploy; test migrations on a copy of production data. |

---

## 3. Caching plan

| What                     | Cache key (always include gym)        | TTL    | Invalidate when                                     |
| ------------------------ | ------------------------------------- | ------ | --------------------------------------------------- |
| Public gym website pages | `website-page:{id}` (version checked) | 5 min  | Published content, gym name, or asset build changes |
| Gym settings / branding  | `gym:{id}:settings`                   | 1 day  | Settings saved                                      |
| Dashboard stats          | `gym:{id}:stats:{date}`               | 5 min  | — (short TTL)                                       |
| Membership plans list    | `gym:{id}:plans`                      | 1 hour | Plan changed                                        |
| Revenue reports          | `gym:{id}:report:{hash-of-filters}`   | 15 min | —                                                   |

Production: `php artisan optimize` (config, routes, views, events) + OPcache enabled (check in hPanel PHP settings).

---

## 4. Load testing (do it before launch)

Phase 7 local baseline (2026-10-06): the user approved **1,000 fictional members**, retained in a separate gym by `LoadTestingSeeder`. The local HTTP runner is `scripts/load-test.mjs`. Two concurrent clients with OPcache and isolated framework caches pass the existing budgets; a four-client run misses the public-page budget (223 ms vs 200 ms). This reduced exercise does not prove the larger capacity target below. Dataset, timings, limitations and repeat commands: [launch verification](launch-verification.md).

- Tool: [k6](https://k6.io) (free). Test on **local or a temporary VPS copy**, not your shared hosting — load testing a shared account can get it suspended.
- Seed realistic data: 50 gyms, 100k members, 2M attendance records (factories + seeders).
- Scenarios:
    1. 1,000 virtual users browsing public gym sites.
    2. 200 staff doing check-ins + member searches.
    3. 500 members opening the portal.
    4. All three combined.
- Pass criteria: p95 within the budget in §1, error rate < 1%.
- Use **Laravel Debugbar / Telescope locally** to find slow queries and N+1s before testing.

```js
// load-test.js — run: k6 run load-test.js
import http from 'k6/http';
import { sleep, check } from 'k6';

export const options = {
    stages: [
        { duration: '1m', target: 200 },
        { duration: '3m', target: 1000 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const res = http.get('http://ironhouse.gymapp.test/');
    check(res, { 'status 200': (r) => r.status === 200 });
    sleep(Math.random() * 3 + 2); // users read for 2–5 seconds
}
```

## 5. Monitoring (know before gym owners tell you)

- [ ] Uptime monitor every 1–5 min (e.g. UptimeRobot, Better Stack — free tiers) on the homepage and a `/up` health route (Laravel has it built in).
- [ ] Error tracking with alerts (Sentry / Flare / Nightwatch).
- [ ] Slow query logging: log any query > 500 ms (`DB::whenQueryingForLongerThan` / `DB::listen` in production with sampling).
- [ ] Weekly check: Hostinger resource usage graphs (CPU, processes, inodes, disk).
- [ ] Alert if `jobs` table > 1,000 pending or `failed_jobs` grows.

## 6. When to move off shared hosting

Move to a VPS when **any** of these is true:

- Resource-limit errors appear in Hostinger stats or uptime alerts.
- p95 response time exceeds the budget under normal load.
- Queue delays matter (reminders arriving late).
- You need Redis, a constant queue worker, or Inertia SSR.
- Roughly 30–50+ active gyms, or a single large gym chain.

Then, in order: VPS → Redis for cache/sessions/queues → Supervisor queue workers → separate managed DB → read replica / more app servers (only if ever needed).
