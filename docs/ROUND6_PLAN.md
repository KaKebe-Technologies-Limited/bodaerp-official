# Round 6 plan: roles, notifications, renewals, complaints, reports

Owner requests of 2026-11-06, written down before any code so the work can continue from any machine or session.
Work starts only after rounds 4 and 5 are committed, pushed and deployed (owner, 2026-11-06: "push everything and
deploy, only then we can start working on the new features").

Status of each phase is tracked in the table at the end. Mark a phase done only after its tests and a Playwright
pass have run.

## What the owner asked for

1. Riders can only view their own ID card: no printing, no downloading, no saving the QR code.
2. When the permit expires the rider requests a renewal and pays for it. The chairperson (and the city) are told,
   so the new card is printed and handed over.
3. Who does what:
   - Super admin creates cities and government users (and city admin accounts). Super admin can do every task of
     every other role.
   - City admin creates stages and their chairperson accounts, registers riders, and sends notices to riders and
     chairpersons in the city.
   - Chairperson searches for riders and adds them to, or removes them from, their own stage. A chairperson can
     never delete a rider.
   - Only city admin and super admin delete riders.
4. A rider always belongs to a stage. If the stage (or its chairperson) does not exist yet, it is created first,
   in the same step as registering the rider.
5. Every role has its own notifications page: all notifications, old and new; mark one read or unread, mark many,
   mark all read; open one to see the full text, who sent it (city, stage chairperson, platform, system) and the
   record it is about.
6. Complaints: anyone, riders included, can log a complaint, and it is handled to the end (status, replies,
   escalation).
7. A reporting system.
8. Licensing and monitoring, not a basic register.
9. README lists exactly what each role does and everything else there is to know about the system.
10. (later the same day) Deploy first, then build. The public can report too (a visitor reports a rider, for
    example by plate number). Public pages get pop-up modals for these actions, including checking a permit, and
    the permit check also works with a bike number plate.

## Decisions and assumptions (state them to the owner; change only if the owner asks)

- A chairperson adds or moves a rider directly, with no approval step ("it is their business to perform their
  tasks"). The rider's previous chairperson and the city admin get a notification, and the city admin can reverse
  the move.
- "Remove from my stage" moves the rider to another stage of the same city (a rider must belong to a stage). A
  chairperson who thinks a rider should leave the register altogether files a removal request with a reason; the
  city admin decides.
- Moving a rider to another city is super admin only (fees and permit terms differ between cities).
- Deleting stays a soft delete with a required reason; payments and audit history are kept; the card is cancelled;
  city admin and super admin can restore.
- Renewal can be paid by mobile money (ioTec) or in cash to the stage chairperson.
- The reporting system is two things: a Reports page per role (tables with filters and CSV export) and the
  complaints case system.
- Government stays read-only. It sees national report figures and complaint counts, never complaint texts or
  personal data.
- Super admin "does everything" through the same city pages with a city switcher (an acting city kept in the
  session and written to every audit entry), not through copies of every page.

## Phase 1: notifications for every role

Schema, migration `2026_11_07_100000_notifications_all` (idempotent `mig_*` helpers):
- `notifications`: `rider_id` becomes NULL-able; add `user_id` INT NULL (the account that receives it),
  `sender_user_id` INT NULL, `sender_label` VARCHAR(120) ("Lira City Council", "Railway Stage chairperson",
  "BodaERP", "System"), `title` VARCHAR(150), `body` TEXT, `link_url` VARCHAR(255), `entity_type` VARCHAR(40),
  `entity_id` VARCHAR(40), `notice_id` INT NULL, `read_at` DATETIME NULL. Indexes `(user_id, is_read, created_at)`
  and `(rider_id, is_read, created_at)`. Backfill `title` from `type` and `read_at` for rows already read.

Backend, new `packages/backend/notify.php`:
- `notify_user(int $userId, string $type, string $title, string $body, array $opts = [])`: opts `link`, `entity`,
  `sender_label`, `sms` (bool), `email` (bool). Honours the user's `notify_in_app`, `notify_sms`, `notify_email`.
- `notify_stage_chair(int $stageId, ...)`, `notify_city_admins(string $cityId, ...)`, `notify_super_admins(...)`.
- `rider_notify()` in functions.php stays the only way to reach a rider and is rebuilt on top, so every existing
  caller keeps working.
- `notification_sender_label()` derives the label from the signed-in user's role and scope.

Pages:
- `pages/account/notifications.php`, one page for all five roles (the rider page becomes a wrapper): tabs All /
  Unread / Read, filter by type, 25 a page, tick boxes with "Mark read", "Mark unread", and "Mark all read".
- `?id=` opens one notification: full text, sender, time, a link to the record (payment, card, complaint, rider,
  transfer). Opening it marks it read.
- Top bar bell in `partials/topheader.php`: unread count and the latest five, "See all".
- Sidebar entry in every role's group (portal_nav.php).

Tests: access.php (a user sees and marks only their own; ids of others give 404); flows.php (mark read, unread,
all; the counter follows).

## Phase 2: sending notices

- Table `notices`: id, sender_user_id, sender_label, scope_city_id, audience (text that describes who got it),
  title, body, channels (portal/sms/email), recipients, created_at. Each recipient gets a `notifications` row
  with `notice_id`.
- Page `pages/account/send-notice.php` with the audiences each role may use:
  - super admin: everyone, one city, one role in one city, one person;
  - city admin: all chairpersons of the city, all riders of the city, the riders of one stage, one person;
  - chairperson: all riders of the stage, one rider.
- Portal notice always; SMS and email optional (SMS respects `sms_opt_out` and the rider's language; email
  respects `notify_email`). The confirm dialog states the number of recipients.
- "Sent notices" list with the count reached. Rate limit per sender per hour.
- Tests: a chairperson cannot reach riders of another stage; a city admin cannot reach another city.

## Phase 3: accounts, stages, riders, transfers, removal

Accounts:
- Super admin: cities (cities.php, exists), government and city admin accounts (users.php; check that government
  accounts can be created and are read-only).
- City admin, new `pages/citycouncil/chairpersons.php`: create, edit, suspend and re-invite chairperson accounts for
  the city's stages (invite by email or SMS, the same invite flow as riders). One chairperson account per stage.

Stages:
- Migration adds `stages.chair_user_id` (the chairperson's account). `chairperson_name` and `chairperson_phone` are
  kept in step with the account so views and old reports keep working.
- stage-management.php: link an existing chairperson or create one in the same form. A stage with riders cannot be
  deleted: offer "Move all riders to ..." first, or deactivate the stage.

Riders:
- Move the registration logic out of `chairperson/register-rider.php` into `rider_register()` (rider_records.php) and a
  shared form partial. New `pages/citycouncil/register-rider.php` with a stage picker and "Stage not listed": the
  stage name, location and the chairperson's name, phone and optional email are created in the same transaction as
  the rider.
- Chairperson, new `pages/chairperson/find-rider.php`: search the city's register by name, permit number, plate or
  phone. Riders of other stages show name, stage and plate only (no NIN, phone or date of birth). "Add to my stage"
  takes a reason.
- My riders: "Move to another stage" (a stage of the same city, with a reason). Remove the delete button and the
  delete handler from `chairperson/my-riders.php`; access.php proves a chairperson POST to delete changes nothing.

Transfers, table `rider_transfers`: id, rider_id, city_id, from_stage_id, to_stage_id, reason, moved_by,
moved_by_role, reversed_by, reversed_at, created_at.
- `rider_transfer()` in rider_records.php is the only way to change a rider's stage (also used by the edit-rider
  pages and the import). It updates the stage, moves the live card to the new stage and issues a card with the
  new reason `transfer` (same QR code) when the old one was printed, then notifies the old chairperson, the new
  chairperson, the city admin and the rider. Every transfer is audited.
- City admin: transfer one rider or many (city-riders.php bulk action); reverse a transfer from the rider's history.

Removal:
- City admin and super admin only. Reasons: left the trade, duplicate record, moved away, deceased, other (with
  text). Adds `riders.removed_reason`, `riders.removed_by`. The card is cancelled; payments stay.
- Removal requests from a chairperson (table `rider_removal_requests` or a complaint type, decided in this phase)
  reach the city admin as a notification.
- New "Removed riders" page (city, super) with Restore.

Tests: chairperson search masks other stages; transfer notifies three people and keeps the QR code; chairperson
cannot delete; stage with riders cannot be deleted; restore brings the rider back.

## Phase 4: renewal requests and a view-only rider card

Rider card page (`pages/rider/my-id-card.php`):
- Remove print, save QR and copy link; keep Enlarge. A print stylesheet leaves the card out of anything the rider
  prints, with a note that the card comes from the chairperson. Say plainly (in the docs) that a screenshot cannot
  be prevented; the QR check always shows the true status.
- An expired permit shows the card stamped EXPIRED and a "Request renewal" button.

Table `renewal_requests`: id, rider_id, city_id, stage_id, fee, late_fee, method (mobile_money, cash), status
(requested, paid, card_ready, collected, cancelled), payment_id, card_id, requested_at, paid_at, closed_at, note.
- The rider requests from the Renewal page (from `permit_renewal_window_days()` before expiry, or any time after).
  Mobile money starts an ioTec payment tied to the request; cash notifies the chairperson to collect the amount.
- `payment_confirm()` (one path for cash, mobile money and USSD) marks the open request paid, issues the renewal
  card (`id_card_issue(..., 'renewal')`, same QR code), and notifies the chairperson (print and hand over), the
  rider (collect your card) and the city admin.
- Marking the card handed over closes the request.
- Chairperson dashboard: renewals waiting for cash, cards to print, cards to hand over.

Tests: the rider page has no print, QR download or copy controls; a confirmed renewal creates the card and three
notifications; a second open request is refused.

## Phase 5: complaints

Tables:
- `complaints`: id, code (CMP-YYYY-NNNNN, unique, never reused), filed_by_user_id, filer_role, rider_id NULL,
  about_type (rider, chairperson, stage, city_staff, payment, card, officer, system, other), about_id, city_id,
  stage_id, category, subject, details, priority (normal, high, urgent), status (open, in_progress, waiting,
  resolved, closed), level (stage, city, platform), assigned_to_user_id, created_at, updated_at, resolved_at.
- `complaint_updates`: id, complaint_id, user_id, role, note, status_from, status_to, internal (bool), created_at.
- Optional attachments under `uploads/complaints/` served only to the people on the case.

Routing:
- A rider's complaint goes to the stage chairperson, or to the city when it is about the chairperson.
- A chairperson's goes to the city; a city admin's goes to the platform.
- Any handler can escalate one level. The super admin sees everything.
- Government sees counts by city and category only.
- The filer sees status changes and non-internal replies; every step sends a notification (phase 1).

Pages: `complaints.php` for every role (file one, my complaints, and for staff the queue with filters and days
open), `complaint.php?id=` with the thread, status change, assign, escalate. Visitors keep the contact form.

Tests: each level sees only its own queue; internal notes never reach the filer; government sees no text.

## Phase 5b: public reports and pop-up modals on the public site

- "Report a rider or a problem" for visitors: a modal on every public page (header button and on verify.php), with
  category (dangerous riding, overcharging, harassment, rider without a permit, lost card found, other), the plate
  or permit number if known, where and when, details, optional photo, optional name and phone for a reply.
  Rate-limited per IP and phone, honeypot field, no account needed. It becomes a complaint (phase 5) with
  `filer_role = 'public'`, routed to the city that issued the plate's permit (or to the platform when no rider
  matches), and the visitor gets a reference number to quote.
- "Check a permit" as a modal on every public page, not only verify.php: permit number OR bike plate. Plate lookup
  normalises spaces and case (`UFA 123B` = `ufa123b`), uses the same masked result and rate limit as the permit
  number lookup (`verify.php`), and shows the same states (valid, expiring, expired, suspended, card no longer
  valid). If a plate matches more than one record, show only "more than one record, use the permit number".
- Modals use controls.js's dialog service and work without JavaScript by falling back to the full pages
  (verify.php, contact.php / a report page).
- Tests: plate lookup with and without spaces; rate limit applies to plates; the public report lands in the right
  city's queue; no personal data in public answers.

## Phase 6: reports

- `packages/backend/reports.php` holds the report definitions: key, title, roles allowed, columns, query, and the
  scope rule per role (stage for chairperson, city for city admin, all for super admin, national totals without
  personal data for government).
- Reports:
  - rider register;
  - payments and revenue;
  - renewals and requests;
  - ID cards (issued, printed, handed over, lost);
  - complaints (open, overdue, resolved, by category);
  - transfers and removals;
  - roadside checks;
  - enforcement;
  - notices sent.
- One partial renders a report: filters (date range, stage, status), a paginated table, CSV export through
  `csv_put()`, print. A `reports.php` page per role lists the reports that role may run. The existing report pages
  (stage report, revenue report, platform reports, government reports) stay and are linked.

Tests: smoke opens every report for every role; access proves the scope rule on the CSV export.

## Phase 7: monitoring

- `rider_timeline()`: registration, payments, renewal requests, cards, roadside checks, enforcement, transfers,
  complaints, notices, newest first. Shown on the chairperson's rider profile, the city's rider page and to the
  super admin.
- City dashboard "Needs attention": renewal requests waiting, cash to collect, cards not handed over, complaints
  open and overdue, riders without photo, signature or NIN, transfers this week.
- Chairperson dashboard: the same for the stage. Super admin: per city.

## Phase 8: documentation and verification

- README rewrite:
  - what BodaERP is, for whom, and why;
  - every role with the exact list of what it can and cannot do (checked against `portal_nav.php` and the page
    handlers);
  - visitors;
  - each process from start to end: onboarding, payment, renewal, cards, transfer, removal, enforcement, roadside
    checks, complaints, notices, data requests;
  - the notification matrix (event, who is told, which channel);
  - setup with Docker or XAMPP, configuration, tests, deploy (link to docs/DEPLOYMENT.md).
- docs/ARCHITECTURE.md and docs/DATABASE.md updated with the new tables.
- Full test run plus Playwright over every page of all five roles, one window.

## Status

| Phase | State |
|---|---|
| 1 Notifications for every role | not started |
| 2 Sending notices | not started |
| 3 Accounts, stages, riders, transfers, removal | not started |
| 4 Renewal requests, view-only rider card | not started |
| 5 Complaints | not started |
| 5b Public reports, modals, plate check | not started |
| 6 Reports | not started |
| 7 Monitoring | not started |
| 8 Docs and verification | not started |
