# Plans & Pricing

Implemented locally on October 8, 2026. The standalone Plans & Pricing sidebar dropdown separates gym access subscriptions, paid membership plans, discount categories and training packages. All prices, durations, package validity modes and session counts are gym-owned configuration; no example prices are seeded into existing gyms.

## Catalog and checkout

Owners create/edit/archive offerings and configure branches. Staff and managers can read pricing; their existing member and branch write permissions govern checkout. Archived offerings remain in history and cannot be newly purchased. Tables reuse RecordList, compact filters, existing status controls and responsive mobile cards. Sidebar links and body category buttons select the same Gym subscriptions/Membership plans/Discounts/Training packages page state. The default is Gym subscriptions; there is no Overview tab. Catalog rows keep only offering details. View/Edit shows that offering’s paginated History log on the right, stacked below on mobile: creation, edits, activation and archiving, newest first, with actor and Manila timestamp. Owner saves refresh the timeline; staff/managers can view without editing.

Add member requires a name; phone/email remain optional. Choose any combination of subscription, benefit and up to ten packages. By default at least one offering is required; existing saved pricing settings are retained. The Checkout policy section is removed from the catalog. Add/Edit no longer require a consent checkbox; historical consent is preserved and no acknowledgement is inferred from saving. The desktop form has a sticky price summary and stacks on mobile. Prices and discounts are recalculated on the server before saving. Checkout offers Paid for the full quoted total or Unpaid with the balance outstanding; partial payments can be recorded later on the member Payment form. No payment gateway is connected.

Subscriptions grant access. Benefits and packages do not grant access on their own. Subscription periods cannot overlap; benefit periods cannot overlap independently. Multiple separate packages are allowed. Branch restrictions are preserved with purchased terms and checked during attendance or training use. Dates start at gym-local midnight, with an inclusive start and exclusive end; displayed Valid through is the last valid local date. Calendar months clamp at month end. Training packages may use a day duration or session-based validity; session-based packages have no date expiry and still consume one session per completed linked appointment.

Member > Membership > Assign or renew opens the same checkout used for registration. Existing active benefit terms and verified category eligibility apply to new purchases/renewals. Benefit price, duration, discount, eligible subscriptions, package applicability and renewal policy are independent. A benefit never discounts its own fee. Owners may allow deferred benefit activation; benefits must be valid at the purchased offering's start date to apply.

## Discounts and statutory treatment

Regular has no category discount. Student and Others can use percentage or fixed peso promotions, eligible offering types/IDs, effective dates and optional verification. Member eligibility stores category, verification actor/time and effective dates, without uploading identity documents. The default promotional policy selects the best single eligible discount. Optional additive promotions use the original line price and cap total savings at that price. Money is integer centavos; percentages use basis points and integer rounding.

Example: monthly subscription P1,000 + annual benefit P599, with an immediately valid 10% subscription benefit, totals P1,499. With a Student 5% promotion, default best-single pricing still totals P1,499; owner-enabled additive pricing totals P1,449.

PWD and Senior are modeled separately as statutory rules. Their canonical rate is 20%, verification is required, and paid benefit membership fees are excluded. The owner must confirm the gym's VAT profile before applying these rules. VAT-inclusive eligible prices remove 12% VAT before the statutory calculation; non-VAT prices use their original base. The calculation chooses the more favorable promotional or statutory total without stacking them together. Eligibility and service applicability must be verified by the operator; keeping proof and issuing tax-compliant invoices remain outside this offline payment-record feature.

Primary references: [BIR RR 7-2010, senior citizen recreation discounts and club membership exclusion](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/55830), [Expanded Senior Citizens Act IRR](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/56201), and [BIR RR 5-2017, PWD discount/VAT rules](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754/).

## Training use and history

Purchases retain the package name/session count, price and discounts, branch/coach restrictions, access requirement and expiry snapshots. Editing the catalog cannot change purchased terms. The member Payment form lists all three purchase types, autofills each saved unpaid balance and records payment through the existing offline action. Payments have unique purchase keys even when catalog IDs overlap. History identifies the offering. Subscription, benefit and package payment balances appear in the existing payment overview/reports; voided payments do not settle balances or contribute revenue.

Link a purchased package when booking a member appointment. Completion requires valid purchased dates, allowed branch/coach, any required gym access and full package settlement. Completion consumes exactly one session under the existing gym/schedule locks and a locked purchase row; a unique redemption constraint prevents repeats. Cancellation/no-show consumes none. Usage history shows its appointment, recorded time and account. Purchase history shows original/net price, discount reasons, creator and latest owner change metadata.

Owner adjustments require a reason and unchanged expected balance, and cannot make the balance negative. Cancelling a purchase freezes unused sessions and preserves records. It does not automatically void payments or issue a refund. Refunds remain manual/offline with existing reviewed void records where appropriate. Packages are not transferable. Expired sessions cannot be used for future appointments; a valid historical appointment may be completed later.

## Combined reports

Reports > Membership & Payments replaces the separate membership/payment tabs. It includes all three purchase kinds, selected by purchase creation date, with original amount, final cash amount, combined savings, paid and balance. Final amount is the agreed purchase price less any later payment discount; savings equals original amount minus final amount. Paid and payment discounts include all recorded settlements to date, including prior branches; voided payments are excluded. Cancelled purchases show zero collectible balance and retain received cash. Revenue uses actual payment dates. Purchase type and Paid/Partial/Unpaid filters, pagination and CSV use the same tenant/manager scope. Details show saved discount sources, dates, branch, creator/time and remaining training sessions. Current catalog edits never recalculate old purchases. The internal benefit kind retains its meaning; its display label is Membership plans.

## Freeze records

Edit member > Freeze records opens only that member’s history and offers a Freeze / Resume shortcut. Members > Freeze records lists audited subscription freezes and resumptions with member, plan, current status, expiry, optional reason, actor and timestamp. Search and pagination respect gym and manager/member branch scopes; no audit IP addresses or full models enter page props. Use Membership > Freeze/Resume and confirm to change access. Repeated identical state requests add no audit. Freezing preserves the agreed expiry date; resuming restores only days still valid. This reuses existing suspension status/audit records and needs no migration.

## Migration and verification

The additive migration `2026_10_08_060000_add_gym_pricing_and_member_purchases.php` is applied locally. Existing membership_plans remain gym subscriptions through the default kind; existing subscription dates, agreed prices and payment records are unchanged. Benefits/packages reuse the typed catalog and have independent member_purchases. pricing_checkouts stores submission fingerprints and immutable totals; session_redemptions records completed usage. Every new table is gym-scoped. Gym erasure removes dependent payments/redemptions/purchases before branch deletion.

Existing legacy assignment retries preserve their saved terms. Gyms opt into configured renewal pricing when saving a benefit/package, discount or checkout policy. New checkout always uses the server-owned pricing calculation. Production migration/deployment remains launch work; back up the database before deployment and do not roll back purchase history after taking real payments.

Validation uses focused pricing, payment, member, appointment, model and tenant-isolation checks, PHP/frontend formatting, TypeScript/build, and Chrome at 1280px/360px. The full launch suite is not rerun for this task.

## Reproduce

1. Plans & Pricing: add a P1,000 monthly subscription and P599 yearly benefit with 10% off that subscription; optionally add a three-session package.
2. Members > Add member: enter a fictional name, select subscription/benefit. Check the P1,499 summary; save unpaid or with an offline payment.
3. Open Membership > Assign or renew for later purchases. Buy/pay a package, link it in New appointment, complete the appointment, then inspect the remaining balance and usage history.
