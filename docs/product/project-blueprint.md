# Gym Management SaaS — Project Blueprint

> **Business model:** Starter ₱999 · Pro ₱1,999 · Multi-Branch ₱2,999 per month (yearly: pay 10 months, get 12) — see §11  
> **Document status:** Working concept blueprint. Requirements, pricing, and technical choices are proposals to validate—not a finalized specification.

## 1. Executive Summary

The proposed Gym Management SaaS is a subscription-based platform for small and medium-sized gyms in the Philippines. It centralizes member records, memberships, attendance, payment tracking, workout programming, and operational reporting.

Each subscribing gym also receives a configurable public website and a member portal where members can securely view their own membership status and expiration date.

The platform is intended to serve multiple gyms from one application while keeping each gym’s data isolated. Pricing is three tiers — **Starter ₱999, Pro ₱1,999, Multi-Branch ₱2,999 per month**, with a yearly "pay 10 months, get 12" option (see §11); tiers and service terms still require validation with gym owners.

## 2. Product Goals

- Reduce manual administration for gym owners and staff.
- Make membership validity, renewal, and payment status easy to monitor.
- Provide QR-assisted attendance and convenient member self-service.
- Let each gym maintain its own branded website without editing code.
- Give members access to assigned routines and basic progress information.
- Build a maintainable SaaS foundation that can support additional gyms over time.

## 3. Users and Roles

| Role                                                    | Responsibilities                                                                                                      |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| SaaS Platform Administrator (optional operational role) | Manage tenant subscriptions, platform settings, support, and service health. Separate from day-to-day gym operations. |
| Gym Owner / Manager                                     | Configure the gym, manage staff, members, plans, website content, payments, attendance, and reports.                  |
| Gym Staff / Front Desk                                  | Register or update members, record payments, scan attendance QR codes, and assist members according to permissions.   |
| Coach / Trainer (optional)                              | Create or assign routines and review member fitness progress, if enabled by the gym.                                  |
| Gym Member                                              | View personal membership details, expiration, payment or renewal instructions, assigned workouts, and progress.       |

## 4. Core Modules and Functional Requirements

### 4.1 Gym and Tenant Setup

- Create a tenant record for each gym, with a unique slug or subdomain.
- Configure gym name, logo, brand colors, contact details, location, social links, and operating hours.
- Invite staff and assign role-based permissions.
- Keep tenant records logically isolated so one gym cannot access another gym’s data.

### 4.2 Member Management

- Require the member's name, consent acknowledgement, active membership plan/subscription and start date when adding a member; mobile number and email are optional. Registration branch is server-assigned from the navbar branch (manager: assigned branch); All branches uses the active main branch or oldest active branch. Do not ask for a Home branch during registration: memberships work at all branches in the gym. Save the member and initial membership atomically using the plan's agreed price/duration; calculate its validity through the existing assignment rules. Payment is recorded separately. Profile edits do not assign another membership. Validate provided contact details and keep name/member ID lookup available when contact details are blank.
- Create, view, update, deactivate, and search member profiles.
- Store contact details and relevant membership records with appropriate access controls.
- Track membership plan, start date, end date, status, and renewal history.
- Support statuses such as **Active, Expiring Soon, Expired, Suspended,** and **Cancelled**.
- Show a member’s membership and relevant transaction history.

### 4.3 Membership Plans

- Allow each gym to configure plan name, duration, price, inclusions, and availability.
- Assign a plan to a member and calculate validity from the recorded start date and plan rules.
- Support renewals, extensions, and manual adjustments with an audit trail.
- Display current plan and expiration clearly in the member portal.

### 4.4 QR Attendance

- Members and their memberships belong to the gym and work at every branch. Owners/staff see the same Members list regardless of the selected branch; home branch records registration/administration, not an access restriction.
- Branch managers can find and check in visiting members at their assigned branch without gaining access to manage those members' profiles or subscriptions. Attendance records the actual visited branch; gym tenant isolation remains mandatory.
- Issue a unique QR credential to each member for attendance identification.
- Allow authorized staff to scan the QR and record check-in time, gym, and staff/device context where appropriate.
- Prevent accidental duplicate check-ins according to configurable rules.
- Provide daily, weekly, and monthly attendance views.
- Provide a manual check-in fallback for scanning issues.

### 4.5 Member QR Portal

- Provide a secure QR link that opens the member portal for the correct gym and member.
- Show membership status, plan, start date, and expiration date after appropriate verification.
- Use a revocable, high-entropy token or authenticated account flow. Do not expose sequential database IDs or sensitive data in the QR URL.
- Allow the gym to revoke or reissue a member portal QR if it is lost or shared.
- Do not display private information until the visitor is authenticated or the secure link has passed appropriate access checks.
- **Guest Check membership:** no sign-in is required for an exact saved phone number or email lookup within the current gym. Show only a server-masked member name, plan, start date, valid-through date and status. Never send the full name or contact details to the guest browser. Member-ID and full-name lookup are prohibited; do not offer a public member directory or autocomplete. The lookup does not grant portal access. When QR attendance is enabled, the result may offer an explicit one-time generation/download of the member's opaque attendance QR; existing QR cards cannot be replaced through this public action, authorized staff can recover current cards from the member tab, and the card must be kept private.

**Suggested member flow:**

1. Member scans their personal QR code.
2. The link opens the correct gym’s member portal.
3. The member signs in or completes the configured secure verification.
4. The portal shows the current plan, membership status, and expiration date.
5. If expired or nearing expiration, the page can show the gym’s renewal instructions or contact details.

**Important distinction:** Attendance QR and member-portal QR can be separate credentials for clearer security and operations. A future unified member QR could open a portal where the member presents a short-lived attendance pass.

### 4.6 Gym Website Builder / Content Management

Each gym can have its own branded public website, managed from the SaaS dashboard.

**Suggested public pages:**

- Home
- About
- Membership Plans
- Facilities / Gallery
- Announcements
- Contact
- Member Portal

**Owner-managed content:**

- Gym name, logo, colors, and banner
- Gym description and facilities
- Photos and gallery
- Membership plan descriptions and prices
- Contact information, address, operating hours, and social links
- Promotions and announcements
- Website navigation and published/unpublished state

Start with a platform subdomain such as `gym-slug.yourplatform.example`. Custom domains can be considered later. Owners should preview changes before publishing and only manage their own gym’s content.

### 4.7 Payments and Revenue Tracking

- Record payment amount, date, payment method, membership period, reference/receipt details, and recording staff.
- Track paid, unpaid, partially paid, and overdue records where applicable.
- Show payment history per member.
- Provide daily, monthly, and date-range revenue summaries.
- Keep payment recording separate from online payment processing; gateway integration can be a later phase.
- Include an audit trail for edits and voids.

### 4.8 Workout and Fitness Management

- Allow coaches or authorized staff to create workout routines.
- Assign routines to members.
- Organize routines by day, muscle group, exercise, sets, reps, and notes.
- Let members view assigned routines and record basic completion or progress, if enabled.
- Keep fitness recommendations informational and configurable; do not present automated calorie estimates as medical advice.

### 4.9 Calorie and Goal Helper

Potential inputs:

- Age
- Height
- Weight
- Activity level
- Goal (for example, maintain weight, lose weight, or gain weight)

Potential outputs:

- Estimated daily energy range
- Goal tracking
- General fitness guidance

This should be treated as an optional informational tool, with clear caveats and user control over personal data.

### 4.10 Reports and Dashboard

- Active, expiring, and expired memberships
- New member registrations
- Attendance trends
- Payment and revenue summaries
- Membership renewals
- Website inquiries or contact submissions, if collected
- Export to CSV or spreadsheet in a later iteration

## 5. Website and QR Experience

### Example gym website

**IRON HOUSE FITNESS**  
Navigation: Home · About · Membership Plans · Facilities · Contact · Member Portal

The gym owner updates the site from the management dashboard. Members and prospective customers see the published version.

### Example member portal information

| Field           | Example                                  |
| --------------- | ---------------------------------------- |
| Member          | Sample Member                            |
| Membership plan | Monthly                                  |
| Status          | Active                                   |
| Start date      | October 1, 2026                          |
| Expiration date | October 31, 2026                         |
| Renewal         | Gym-defined instructions or contact link |

These values are illustrative only.

## 6. Proposed System Architecture

```text
Gym Owner / Staff ───────┐
                         ├── Gym SaaS Application ─── Tenant-aware API
Gym Member / Visitor ────┘                                  │
                                                            ├── Gym A data
Public Gym Websites ─────────────────────────────────────────┤
                                                            ├── Gym B data
                                                            └── Gym C data
```

**Architecture principles:**

- Multi-tenant application with explicit tenant scoping.
- Role-based access control.
- Shared application with tenant-owned records, unless future scale or compliance needs justify separate databases.
- Centralized authentication and authorization.
- Audit logs for sensitive actions.
- Backups, monitoring, and secure configuration.
- Public website content separated from private member data.
- Avoid assuming a QR URL itself is sufficient authentication for sensitive information.

## 7. Proposed Technology Stack

| Layer             | Proposed technology                                                                      |
| ----------------- | ---------------------------------------------------------------------------------------- |
| Backend           | Laravel 12 (or current supported Laravel version at implementation time)                 |
| Frontend          | Vue 3                                                                                    |
| Database          | PostgreSQL or MySQL; select one for the initial build                                    |
| Cache / Queues    | Redis when needed                                                                        |
| Local development | Docker Compose optional; native local setup acceptable initially                         |
| Web server        | Nginx                                                                                    |
| Hosting           | Start with a suitable managed VPS or cloud platform; compare cost and operational burden |
| Source control    | Git and GitHub                                                                           |
| Project tracking  | Jira, Linear, or GitHub Issues                                                           |
| API documentation | OpenAPI / Swagger as API surface grows                                                   |
| CI/CD             | GitHub Actions or equivalent in a later stage                                            |

The stack is a proposal, not a requirement to adopt every tool on day one.

## 8. Suggested Data Model

Initial entities and relationships to consider:

| Entity                       | Purpose / key fields                                                             |
| ---------------------------- | -------------------------------------------------------------------------------- |
| `gyms`                       | Tenant identity, slug, branding, contact details, settings                       |
| `users`                      | Authentication identities                                                        |
| `gym_user` or memberships    | User-to-gym relationship and role                                                |
| `members`                    | Gym-specific member profile                                                      |
| `membership_plans`           | Gym-defined plan, duration, price, active flag                                   |
| `member_subscriptions`       | Member, plan, start/end dates, status                                            |
| `payments`                   | Amount, method, date, reference, status, recorded-by                             |
| `attendance_records`         | Member, gym, check-in timestamp, source/staff                                    |
| `member_qr_tokens`           | Hashed/revocable token metadata, purpose, expiry/revocation                      |
| `gym_pages`                  | Website page content and publication state                                       |
| `gym_media`                  | Logo, banner, and gallery asset metadata                                         |
| `announcements`              | Gym-specific notices or promotions                                               |
| `workout_routines`           | Routine metadata and owner gym                                                   |
| `workout_exercises`          | Exercise details within routines                                                 |
| `member_routine_assignments` | Routine-to-member assignment                                                     |
| `audit_logs`                 | Sensitive changes and actor metadata                                             |
| `gym_subscriptions`          | SaaS plan, billing status, and renewal dates, if platform billing is implemented |

**Data design notes:**

- Include a `gym_id`/tenant key on gym-owned records and enforce tenant scoping in application policies and queries.
- Use database constraints and indexes for common lookups, including member, gym, status, and expiration date.
- Store QR secrets as hashes where feasible; support revocation and rotation.
- Define retention, privacy notice, consent, and access/deletion procedures before production launch.
- Avoid storing card details directly; use a payment provider if online payments are introduced.

## 9. MVP Development Roadmap

### Phase 1 — Foundation

- Project setup, authentication, and role-based access
- Gym tenant creation and settings
- Tenant isolation policies and tests
- Basic owner dashboard

### Phase 2 — Members and Memberships

- Member CRUD and search
- Membership plan setup
- Assign, renew, expire, suspend, and cancel memberships
- Member status dashboard

### Phase 3 — QR Attendance

- Generate member attendance credentials
- Staff scanning/check-in page
- Attendance history and duplicate-check-in handling
- Manual check-in fallback

### Phase 4 — Payments

- Record payments and receipts/references
- Payment history
- Expiration and unpaid-member views
- Basic revenue summaries

### Phase 5 — Gym Website and Member Portal

- Gym-branded public website templates
- Owner content editor and preview/publish workflow
- Public plans and contact information
- Secure member portal
- Member QR link, revocation, and reissue
- Membership status and expiration display

### Phase 6 — Workouts and Reports

- Routine builder and assignments
- Member routine view
- Attendance, membership, and revenue reports
- Export options

### Phase 7 — SaaS Operations and Growth

- SaaS subscription billing and plan limits
- Custom domains
- Email notifications
- Monitoring, backups, CI/CD, and performance testing
- Additional integrations based on customer feedback

## 10. Security, Privacy, and Reliability Requirements

- Enforce tenant authorization on every gym-owned resource and test cross-tenant access denial.
- Use secure password handling, session controls, and optional multi-factor authentication.
- Protect member portal routes and avoid exposing personal data through public URLs.
- Use CSRF protection for browser sessions and validate API authorization.
- Rate-limit sensitive endpoints such as sign-in, QR resolution, and public forms.
- Record audit events for membership, payment, role, and website publication changes.
- Use HTTPS, backups, secrets management, and least-privilege access.
- Provide clear privacy notices and collect only necessary personal data.
- Establish backup restoration and incident-response procedures before production.

## 11. Business Model

Three monthly plans per gym account. **The source of truth for prices and limits is `config/plans.php`** — the landing page reads it, and plan limits/billing will too.

|                                         | Starter ₱999/mo            | Pro ₱1,999/mo                               | Multi-Branch ₱2,999/mo                            |
| --------------------------------------- | -------------------------- | ------------------------------------------- | ------------------------------------------------- |
| Branches                                | 1                          | 1                                           | 2 included (+₱999 per extra branch)               |
| Owner account                           | 1                          | 1                                           | 1 + branch managers                               |
| Staff accounts                          | 2                          | 5                                           | 5 per branch                                      |
| Active members                          | Up to 150                  | Up to 500                                   | Unlimited                                         |
| Membership & expiry tracking            | ✅                         | ✅                                          | ✅                                                |
| Manual sales recording                  | ✅                         | ✅                                          | ✅                                                |
| Daily/monthly reports                   | Basic (today + this month) | Full reports + charts + export to Excel/PDF | Full reports + combined and per-branch comparison |
| Gym mini-site / public page             | Basic page                 | Custom page (photos, plans, schedule)       | Custom page per branch                            |
| Online payments (GCash/Maya/card)       | ❌                         | ✅                                          | ✅                                                |
| Expiry reminders to members             | ❌                         | ✅ (email)                                  | ✅ (email)                                        |
| Staff activity log                      | ❌                         | ✅                                          | ✅                                                |
| Members can use any branch              | —                          | —                                           | ✅                                                |
| Branch managers (see own branch only)   | —                          | —                                           | ✅                                                |
| Transfer members/staff between branches | —                          | —                                           | ✅                                                |

**Yearly option — "pay 10 months, get 12" (2 months free):** Starter ₱9,990/yr · Pro ₱19,990/yr · Multi-Branch ₱29,990/yr (+₱9,990/yr per extra branch). Gym owners like paying once, and the business gets cash upfront.

Illustrative gross monthly revenue (before hosting, payment fees, taxes, support):

| Mix of gyms                         | Gross monthly recurring revenue |
| ----------------------------------- | ------------------------------: |
| 10 Starter                          |                          ₱9,990 |
| 5 Starter + 5 Pro                   |                         ₱14,990 |
| 10 Starter + 8 Pro + 2 Multi-Branch |                         ₱31,980 |

Still to decide: whether prices include VAT, free trial length (besides "Try demo"), upgrade/downgrade and refund rules (see [legal-and-privacy-ph.md §7](../legal/legal-and-privacy-ph.md)).

## 12. Risks and Open Decisions

- **Tenant isolation:** Choose and test a tenant strategy before building many modules.
- **QR privacy:** Decide whether portal QR requires login, a one-time code, or a carefully scoped secure token.
- **Website customization:** Begin with structured templates rather than a fully flexible drag-and-drop page builder.
- **Payments:** Decide whether MVP only records offline payments or also integrates a payment gateway.
- **Notifications:** Choose email, SMS, or in-app notifications based on cost and gym needs.
- **Data privacy:** Review applicable Philippine privacy requirements and prepare notices and retention policies.
- **Pricing:** Validate the three tiers (₱999 / ₱1,999 / ₱2,999) with target gyms; decide VAT treatment.
- **Employment/IP obligations:** Review any current employment agreement and keep side-project work, accounts, devices, and code separate from employer resources.

## 13. MVP Definition of Done

The first usable release should allow one gym to:

- Sign in as owner and staff with appropriate permissions.
- Configure gym profile and membership plans.
- Register members and assign memberships.
- See active, expiring, and expired membership states.
- Record payments.
- Scan attendance QR codes and review attendance history.
- Publish a branded gym website using editable sections.
- Issue a secure member portal QR/link.
- Let a member view their own plan, status, and expiration date.
- Demonstrate that users from one gym cannot access another gym’s private records.
- Back up and restore the application database successfully.

---

## Appendix A — Product Positioning

The product is best described as a **Gym Business Operating System**: gym management, website publishing, member self-service, attendance, payment tracking, and fitness engagement in one subscription platform.

## Appendix B — Next Planning Artifacts

Recommended follow-up documents:

1. Detailed software requirements specification (SRS)
2. ERD and database migrations plan
3. Role-permission matrix
4. API endpoint specification
5. Screen-by-screen UI wireframe inventory
6. MVP task backlog with milestones and acceptance criteria
