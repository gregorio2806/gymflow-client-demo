# Platform policies and account agreements

The platform provides `/privacy`, `/terms` (including the gym data processing agreement), `/cookies`, `/refund`, and `/contact`. The landing/request-access footer links to them. The same structured content appears in **Read terms and policies** on owner/team invitations and on a member's first verified portal visit. Checkboxes start unchecked. Versions and UTC acceptance timestamps are server-owned; edits to policy content or operator details rotate the fingerprint. Existing accounts and fictional seed data are not backfilled with claimed agreement. Portal agreement is tenant/proof scoped, transactional, idempotent and audited without staff attribution. General agreement does not grant optional health or marketing consent.

Set the `LEGAL_*` values listed in `.env.example`: registered business name/address, contact/privacy email, phone/contact/Facebook name, tax notice, actual providers/processing countries, retention, cancellation/access/data-return terms and refund rules. The supplied local email, phone and Facebook contact are configured only in `.env`; no real contact values are seeded into tests.

The pages remain marked **Draft for local review** and `noindex` until all required fields are set and `LEGAL_PUBLISHED=true`. Production account agreement/signup is blocked while publication is incomplete. Complete and review the terms before enabling production signups. This implementation does not invent business registration, refund promises, retention automation, billing, or data-export capabilities.

The privacy notice/account-consent distinction follows the NPC's [right to be informed](https://privacy.gov.ph/the-right-to-be-informed/) and [consent guidelines](https://privacy.gov.ph/wp-content/uploads/2023/11/NPC-Circular-No.-2023-04_Guidelines-on-Consent_07Nov2023.pdf). Business/transaction transparency was checked against DTI's [Internet Transactions Act and IRR](https://ecommerce.dti.gov.ph/ra11967/). These are implementation sources; the operator still needs accurate business decisions and policy review before launch.

STR:

1. Landing footer > **Privacy / Terms & DPA / Cookies / Refunds / Contact**.
2. Owner/team invitation > **Read terms and policies** > close > check agreement > create/join account.
3. Member first sign-in > **Review your account terms** > review policies > check agreement > **Agree and continue**. Later visits use the recorded version; revised policies require review again.
