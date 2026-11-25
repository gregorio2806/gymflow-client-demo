# Appointment module

The Appointment sidebar contains **Appointments** and **Coach calendar**. Both use the existing coach-calendar feature and the shared list, dialog, drawer, and theme components.

- **Appointments manages bookings:** compact summary cards, date/name/coach/branch/service/status filters, a paginated record table, and a details drawer. New appointment selects a gym member or named customer, service, coach, assigned branch, date, duration, available time, and optional location/notes.
- **Coach calendar manages time:** Month, Week, Day, and selected-day List views; session types have labeled color indicators. Bookings appear automatically. Block time reserves breaks or leave; Edit availability sets weekly working hours. Existing generic schedules become blocked time and retain their history.
- **Reports analyzes history:** actual appointment totals, confirmed completion, cancellations, no-shows, status trends, and top-ten coach/service/branch comparisons. Blocked time is excluded. There is no appointment payment linkage, so service revenue is omitted.

## Booking rules

Working hours belong to the coach's membership in this gym. New coaches begin with no working days enabled. Owners/staff and the coach can edit those hours; a branch manager can view them and block their assigned branch, but cannot change hours shared across branches.

Bookings require a coach assigned to the destination branch, enabled hours, a same-day duration of 15 minutes to eight hours, and no overlapping appointment or blocked interval for that coach at any branch. Available times are offered every thirty minutes; duration is editable. Adjacent sessions are allowed. The server rechecks availability inside the existing gym transaction, so stale client slots cannot bypass conflicts. Slots expose times only, never another branch's booking details.

Owners/staff manage bookings; branch managers manage bookings at their assigned branch, including visiting members registered elsewhere in the same gym. Coaches read their own bookings and calendar. Members/customer bookings do not imply a public self-service booking page.

Scheduled bookings can be rescheduled, reassigned, or changed to Completed, Cancelled, or No-show. Completion/no-show requires the session to have ended. Terminal statuses cannot be reversed or edited. Cancellation releases the slot and preserves history. Creation uses UUID retries; edits/status changes use revisions and transactional audits.

The existing schedule table stores both bookings and blocks, avoiding separate conflict stores. Times are stored in UTC and displayed in the gym timezone. Tenant/role scopes, read-only branch restrictions, explicit public props, and request limits apply.

## Steps to reproduce

1. Appointment > Coach calendar > choose a coach > Edit availability > enable working days and save.
2. Appointment > Appointments > New appointment > choose a member/customer, service, coach, assigned branch, date, duration and available time > Confirm appointment.
3. View its details, reschedule if needed, then open Coach calendar to see it automatically. Block time removes overlapping slots from new bookings.
4. Complete/cancel/mark no-show through the details drawer; Reports > Appointments shows the recorded lifecycle and comparisons.
