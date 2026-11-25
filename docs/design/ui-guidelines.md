# UI Guidelines

The rules every screen follows, so the app looks and behaves the same everywhere.
Numbers come from [mobile-ui-ux-cheat-sheet.md](mobile-ui-ux-cheat-sheet.md); accessibility rules from [accessibility-and-content.md](accessibility-and-content.md).
Here they're translated into **the Tailwind classes and components we actually use**.

> Users: gym owners and front-desk staff — often on a **phone**, in a busy gym, sometimes on slow mobile data.
> Design **mobile-first**, then enhance for desktop.

---

## 1. Brand & color

Branding uses the clean dumbbell mark in `AppLogoIcon.vue`, on login pages and on public-site headers. Keep existing theme colors and compact sizing; the hooded emblem was reverted at the user's request. Authenticated sidebar/header branding must use this shared mark rather than the Laravel starter logo.

Always use the **theme tokens** (defined in `resources/css/app.css`), never raw hex colors — tokens switch automatically for dark mode.

| Token (Tailwind class)                       | Use for                                        | Light            | Dark            |
| -------------------------------------------- | ---------------------------------------------- | ---------------- | --------------- |
| `bg-background` / `text-foreground`          | Page background / main text                    | white / black    | black / white   |
| `bg-card`                                    | Cards, panels                                  | white            | near-black      |
| `bg-muted` / `text-muted-foreground`         | Subtle areas / secondary text                  | light gray       | dark gray       |
| `bg-primary` / `text-primary-foreground`     | **Main action** (one per screen), brand accent | red #dc2626      | red #dc2626     |
| `bg-secondary`                               | Secondary buttons                              | gray             | dark gray       |
| `bg-destructive`                             | Delete / void (always with confirmation)       | dark red #b91c1c | dark red        |
| `border-border`, `border-input`, `ring-ring` | Borders, input borders, focus rings            | gray / red ring  | gray / red ring |

**Status colors** (the only place non-brand colors are allowed) — always **text + color**, never color alone:

| Status        | Classes                                                    |
| ------------- | ---------------------------------------------------------- |
| Active / paid | `bg-emerald-500/15 text-emerald-700 dark:text-emerald-400` |
| Expiring soon | `bg-amber-500/15 text-amber-700 dark:text-amber-300`       |
| Expired / due | `bg-primary/15 text-red-700 dark:text-red-300`             |
| Suspended     | `bg-muted text-muted-foreground`                           |

Themes: **guest pages** (landing, login, register, onboarding) are always dark (`class="dark"` wrapper).
**Logged-in app** defaults to light; users can switch in Settings → Appearance.

## 2. Spacing (4px system)

Only use Tailwind's 4px steps. No custom values like `mt-[13px]`.

| px  | Class     | Use                                                                        |
| --- | --------- | -------------------------------------------------------------------------- |
| 4   | `1`       | Icon ↔ text inside a badge                                                 |
| 8   | `2`       | Label ↔ input, title ↔ subtitle, icon ↔ text                               |
| 12  | `3`       | Between related items in a list                                            |
| 16  | `4`       | **Default**: card padding (mobile), gaps                                   |
| 24  | `6`       | Between form fields groups, card padding (desktop), page padding (desktop) |
| 32  | `8`       | Between sections inside a page                                             |
| 48+ | `12`–`20` | Between page sections (landing page)                                       |

Form rhythm: `Title → 8 → Subtitle → 24 → Field → 16 → Field → 24 → Button`.

## 3. Typography

Font: **Instrument Sans** (self-hosted by Vite — no external font requests). Max ~6 sizes:

| Use                     | Class                                       | px      |
| ----------------------- | ------------------------------------------- | ------- |
| Page title (`h1`)       | `text-2xl font-semibold`                    | 24      |
| Section heading (`h2`)  | `text-xl font-semibold`                     | 20      |
| Card title (`h3`)       | `text-base font-semibold`                   | 16      |
| Body                    | `text-base` (inputs) / `text-sm` (dense UI) | 16 / 14 |
| Secondary / helper text | `text-sm text-muted-foreground`             | 14      |
| Caption, badges         | `text-xs`                                   | 12      |

Landing/marketing pages may go bigger (`text-4xl`–`text-6xl` hero). One `h1` per page; headings in order.
Inputs stay `text-base` on mobile — smaller makes iOS zoom in on focus.

Dashboard typography also adapts to the compact laptop breakpoint (1024–1440px wide, at most 720px tall, hover/fine pointer). Titles/counts, section headings, body labels and captions scale further than spacing so displayed text at 150% zoom approximates normal desktop sizes. Chart axes inherit the same density through a dashboard CSS variable. Keep these overrides scoped to Dashboard; ordinary desktop and touch/mobile retain their existing typography and input sizes.

## 4. Sizes — buttons, inputs, icons

**Rule: 44px minimum for anything tappable.**

| Element                   | Size                                                    | Class                            |
| ------------------------- | ------------------------------------------------------- | -------------------------------- |
| Button (default)          | **44px** high, 16–24px side padding                     | `h-11 px-4` (`size="default"`)\* |
| Button (large / hero CTA) | 44–48px                                                 | `h-11 px-6` / `h-12`             |
| Button (small)            | 36px — **desktop-only dense UI** (table rows, toolbars) | `size="sm"`                      |
| Icon button               | 44×44 touch area, 20px icon                             | `size-11` + icon `size-5`        |
| Input / select            | **44px** high                                           | `h-11`\*                         |
| Checkbox / radio          | 16–20px box, **44px** clickable row (wrap in `<Label>`) |                                  |
| Icons                     | 16 (inline text) / 20 (buttons, nav) / 24 (feature)     | `size-4` / `size-5` / `size-6`   |
| Avatar                    | 32 / 40                                                 | `size-8` / `size-10`             |
| List / table row          | 48–64px                                                 | `py-3` with 16px text            |

- Already applied in `ui/button`, `ui/input`, `ui/select` and `NativeSelect.vue` (guarded by `tests/Unit/UiSizeStandardsTest.php`).
  If you re-generate a shadcn-vue component, restore these sizes. Use `<NativeSelect>` for simple dropdowns (native phone picker, no JS).

Radius: `rounded-md` (6px) buttons & inputs · `rounded-lg` (8px) small cards · `rounded-xl` (12px) cards/panels · `rounded-full` badges & avatars.

## 5. Layout & breakpoints

Mobile-first: write the phone layout, then add `sm:` / `md:` / `lg:`.

| Breakpoint | Width  | Typical device            |
| ---------- | ------ | ------------------------- |
| (none)     | < 640  | Phones (design for 360px) |
| `sm:`      | ≥ 640  | Large phones              |
| `md:`      | ≥ 768  | Tablets (front desk)      |
| `lg:`      | ≥ 1024 | Laptops                   |
| `xl:`      | ≥ 1280 | Desktops                  |

- Page side padding: `px-4` (16px) mobile → `sm:px-6` (24px).
- Content width: `max-w-6xl mx-auto` (marketing), full width inside the app shell; forms `max-w-xl`; reading text `max-w-prose`.
- **No horizontal scrolling** at 360px — tables become stacked cards on mobile.

### App shell (logged in)

```
Desktop (lg+)                              Mobile
┌──────────┬──────────────────────────┐    ┌────────────────────────┐
│ Sidebar  │ Header: breadcrumbs  user│    │ ☰  Page title      👤 │  ← 56px header
│ ─ Gym    ├──────────────────────────┤    ├────────────────────────┤
│ Dashboard│ Page title      [Action] │    │ [Primary action]       │
│ Members  │                          │    │ Content (stacked)      │
│ Check-in │ Content                  │    │                        │
│ Payments │                          │    │                        │
│ Settings │                          │    │                        │
└──────────┴──────────────────────────┘    └────────────────────────┘
```

Sidebar collapses into a sheet (☰) on mobile — the starter kit's `AppSidebar` does this already.

**Compact laptop density:** logged-in app layouts use a 75% root font size on short desktop viewports (1024–1440 CSS pixels wide, at most 720 pixels tall, hover/fine pointer). This scales rem-based fonts, sidebar width, controls, icons and spacing together, including dropdowns/dialogs. It makes the 150% laptop layout closer to the normal desktop density. Default buttons become 33 CSS pixels in this desktop mode (about 50 screen pixels at 150% zoom); the normal 44px sizing remains on touch devices, phones and larger/taller screens. This is the requested desktop density exception to §4. Browser zoom is not overridden; narrower viewports at higher zoom still use the normal responsive layout. Guest/public/portal layouts retain their existing sizing.

## 6. Page templates

Every app page follows one of these:

| Template                     | Structure                                                                                                                            |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| **List** (Members, Payments) | Title + count · primary action (top right; full-width on mobile) · search/filter row · table (desktop) / cards (mobile) · pagination |
| **Detail** (Member)          | Title + status badge · action buttons · summary card · tabs or sections (History, Payments)                                          |
| **Form** (Add member)        | Title + short description · fields in one column (`max-w-xl`) · primary + cancel at the bottom                                       |
| **Dashboard**                | Stat cards (2 cols mobile → 4 desktop) · "needs attention" list (expiring, unpaid) · quick actions                                   |
| **Settings**                 | Starter-kit settings layout: side nav + form sections                                                                                |

## 7. Buttons & actions

- **One primary (red) button per screen.** Others are `outline`, `secondary`, or `ghost`.
- Label = verb + thing: "Add member", "Record payment", "Save changes" — never "Submit" / "OK".
- Order: primary on the **right** (desktop), **on top / full width** (mobile). Cancel next to it as `ghost` or `outline`.
- Destructive actions (`variant="destructive"`) always open a confirm dialog that names the thing:
  "Delete member Juan Dela Cruz? This can't be undone."
- While submitting: disable + spinner + present-tense label ("Saving…"). Use `form.processing` / `<Form v-slot="{ processing }">`.

## 8. Forms

- Visible `<Label>` for every field (placeholders are examples, not labels). Mark optional fields "(optional)" — most fields are required.
- One column. Group related fields with a small heading.
- Right input type + `autocomplete` (`tel`, `email`, `name`) for mobile keyboards.
- Errors: `<InputError>` under the field, in plain words with how to fix it.
- Validate on submit (server is the source of truth); don't scream errors while the user is still typing.

## 9. States — design all of them

| State    | Pattern                                                                                                                                                                                                                                                                  |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Loading  | **Skeletons** shaped like the content: `<SkeletonList>` as the `<Deferred>` fallback for slow lists; spinner only inside buttons. Page changes: red top bar after 250ms, `NavigationLoader` pill after 1s. First load: CSS splash in `app.blade.php`. All zero-download. |
| Empty    | Icon + one sentence + the action to fix it: "No members yet. **Add your first member**."                                                                                                                                                                                 |
| Error    | What happened + what to do: "Couldn't load payments. Check your connection and **try again**."                                                                                                                                                                           |
| Success  | Toast (top-right / bottom on mobile) via `Inertia::flash('toast', ...)` — "Payment recorded."                                                                                                                                                                            |
| Disabled | `disabled` attribute + 50% opacity; explain why nearby if not obvious                                                                                                                                                                                                    |
| Focus    | Visible red ring (`focus-visible:ring`) — never remove outlines                                                                                                                                                                                                          |

## 10. Tables vs cards

- **Members is the visual reference for record lists.** Use `resources/js/components/RecordList.vue` across modules instead of rebuilding tables and mobile cards on each page.
- Use a neutral `bg-card` panel, `rounded-xl` border, `bg-muted/40` header, `text-xs` header labels, `text-sm` rows, `p-3` desktop cells, separators and `hover:bg-muted/30` rows. Keep the primary identity readable, secondary information muted, and Actions last.
- The shared component chooses a desktop table at **48rem of available container width**, or **64rem with `wide`** for dense lists. Smaller containers display stacked cards with visible field labels. This also adapts to an open sidebar and laptop zoom.
- Tables and mobile cards use the same rows and controls in one DOM tree. Keep captions, column headers, keyboard controls and unique form IDs. Use cell slots for existing links, status badges, dialogs and forms; preserve role/feature restrictions.
- Always preserve existing pagination and filters. Default to 25 records when adding a paginated list; only offer sizes supported by the endpoint. Show ranges/counts when supplied. Do not add decorative sorting, checkboxes, statistics or actions without working behavior.
- Use the same outline action buttons, meaningful labels and 44px touch targets. Obvious View/Edit actions can use labeled icon buttons. Destructive actions keep their confirmation/reason flow.

History records show creation date/time and the creator account name using shared `RecordMetadata.vue`. `RecordList.vue` renders supplied `createdAt`/`createdBy` fields consistently. Resolve creators from server-owned recorder IDs or the original creation audit, expose only the name, and show **Not recorded** when historical data has no creator. Keep subsequent actors (such as who voided a payment) separate from the creator. Editable catalogs such as membership plans also show `updatedAt`/`updatedBy` from the latest audited edit or availability change, both in lists and edit views.

Dashboard density: use compact `p-3 sm:p-4` page padding, `p-3` panels and `gap-3`/`space-y-3` rhythm. Charts use fixed `h-32 md:h-36` viewports with measured geometry and readable axes; widening a panel must not increase chart height. Keep chart data available through expandable details and retain clear count values.

Attendance uses one page: compact KPIs above a check-in card on the left and filtered history on the right. Stack cards on narrow containers. Inside the check-in card, Search member and Scan QR are alternative workflows: show only search/results/selections/check-in action or preview/camera/photo/instructions, never both at once. Keep branch and method controls shared; preserve selections and stop the camera when returning to Search. Member cards show initials, masked mobile, status and agreed plan price/duration; keep multiple selections visible as removable chips. Frame guidance must describe attendance QR scanning. QR photos decode on the device; do not imply facial recognition or stored photos. Avoid automatically focusing search on mobile page load.

### Consistency on every page

Public gym navigation uses **Check membership** to open the guest phone-or-email lookup. Its reference layout uses a dark branded header, red heading/action accents, one form/details column and a compact help panel, stacked on phones. Reuse only published gym name/logo/hero imagery; without imagery use the clean dumbbell and dark gradient. Keep contact help, clear/error/loading states and readable results: a server-masked member name above the plan, dates and status grid. Avoid broad security or speed claims; private portal sign-in and pages retain their existing controls/layout.

Website editor uses one scrollable form, consistent content panels and a saved-draft preview beside it on wide screens (stack below on mobile). Device controls resize the preview; label it as saved content and keep explicit Save draft/Publish confirmation. Public gym templates use a dark hero with owner-supplied image, red accents and white content/contact cards without fabricated facilities or claims. Do not show custom-domain, autosave or publication-toggle controls until implemented.

Plans offers Plan cards or Uploaded image. Show only the selected workflow; retain saved cards when switching to image mode. Require an accessible description of image-based prices/durations. Display the published image responsively without cropping, with a full-size link; uploads and mode changes follow the same draft/preview/publish flow.

Keep pages clean and aesthetic without overwhelming users: prioritize the current task, use restrained accents and clear hierarchy, and avoid duplicate analytics or unnecessary controls. Attendance history follows the supplied reference with four compact summary cards (check-ins, unique members, daily average and busiest hour), one filters/records panel, member initials/contact, status badges and compact pagination; attendance trends belong on Dashboard. Record creation details stay available in a disclosure. Check-in uses one card with mutually exclusive Search member and Scan QR workflows. Add details only when they help users act; references do not authorize decorative or unimplemented features.

Every new or edited page must follow the existing template and shared components for its purpose. Match title sizes, page padding (`p-4 sm:p-6`), section rhythm (`space-y-5`), controls, status colors, empty states, date formatting and pagination to related pages. Reuse shared components for fixes so modules stay aligned. Forms, calendars, dashboards and public pages retain their appropriate templates, with consistent controls and theme tokens; record lists use `RecordList`. A different layout needs a clear functional reason, not a per-page styling preference.

## 11. Motion & performance

Reports provides detailed analysis rather than repeating Dashboard's overview. Date filters offer This year/This month through today, Last month, Specific month and Custom range. Show the month picker only for Specific month and From/Through only for Custom range; use the same applied range for records, charts and exports. Keep filter widths compact and retain the current report limits. Payment history belongs in member details and Reports > Payments; omit the duplicate sidebar entry. Default to Revenue and show only implemented, feature-enabled report tabs. Use applied date/branch filters, report-specific metrics, one trend/breakdown and the shared paginated record list. Keep creation metadata behind Record details. Revenue shows daily cash totals and owns the queued spreadsheet summary; Membership & Payments owns purchase-level records. Detailed CSV downloads follow the same applied filters for reports that expose detailed records. Label agreed membership prices separately from received revenue and completed appointments separately from paid transactions. Reports > Appointments uses actual bookings and recorded lifecycle states; blocked time is excluded. Do not invent data for future product sales or services.

- Transitions 150–200ms, `ease-out`. Only animate `opacity` and `transform`.
- Respect reduced motion: `motion-reduce:transition-none` / `motion-reduce:animate-none`.
- **Zero-cost UI:** no GIFs, videos, Lottie, or icon fonts. Icons are inline SVG (Lucide). Images: WebP, sized, `loading="lazy"` below the fold.
- Public pages (landing, gym websites) are **Blade with no JavaScript** where possible.

## 12. Writing (microcopy)

- Plain English, short sentences, "you" for the user. Philippine context: ₱ amounts as `₱1,999`, dates as "October 31, 2026", times in Manila time.
- Be specific: "Membership expires in 3 days" beats "Expiring soon" when there's room.
- No jargon ("tenant", "slug", "record ID") in the UI — say "gym", "web address", nothing.

## 13. Checklist for every new screen

- [ ] Works at 360px wide, no horizontal scroll
- [ ] One primary action; buttons/inputs ≥ 44px; icon buttons have `aria-label`
- [ ] Only theme tokens (looks right in light **and** dark)
- [ ] Loading, empty, error, and success states exist
- [ ] Keyboard: tab order, visible focus, Enter submits, Esc closes dialogs
- [ ] `<Head title="...">` set; one `h1`
- [ ] Lists paginated; Inertia props contain only needed fields
- [ ] Consistent with related pages; record lists reuse `RecordList.vue` and the Members table style

### Date display

Display record dates as full English month names, for example **October 6, 2026**. Use `formatDisplayDate` from `@/lib/date-format` for gym-local dates and timestamps. Preserve time labels on timestamps. Native date inputs and submitted/filter values keep `YYYY-MM-DD`.

Appointment pages follow the [appointment module rules](appointment-module.md): operational bookings use a shared record list and details drawer; Coach calendar manages working hours and blocked time, with Month/Week/Day/List views. Keep controls compact and show only relevant actions.
