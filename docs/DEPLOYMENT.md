# Deploying to a live server

Written for shared PHP + MySQL hosting such as Hostinger, but any Apache + PHP 8.1+ + MySQL/MariaDB host works.
There is no build step: **the files are the application**.

## First-time setup

1. **PHP version.** In the hosting panel choose PHP **8.1 or newer** (8.2 recommended). `mysqli`, `mbstring`
   and `curl` must be enabled (they are by default).
2. **Create the database.** In the panel's *MySQL Databases* create an empty database and a user with
   all privileges on it. Note the host (usually `localhost`), database name, user and password.
3. **Put the code on the server** (git pull, the panel's Git integration, or upload) so that `index.php`,
   `.htaccess` and the `packages/` folder sit in the web root (`public_html`). `.htaccess` is a hidden
   file: make sure it is included. A sub-folder install also works; the URL prefix is detected automatically.
4. **Create `.env`** next to `index.php` (copy `.env.example`; never commit it):
   ```ini
   APP_ENV=live
   APP_URL=https://your-domain
   DB_HOST=localhost
   DB_NAME=your_database
   DB_USER=your_user
   DB_PASS=your_password
   SMTP_HOST=smtp.hostinger.com
   SMTP_PORT=465
   SMTP_SECURE=ssl
   SMTP_USER=no-reply@your-domain
   SMTP_PASS=the-mailbox-password
   MAIL_FROM=no-reply@your-domain
   ```
   `APP_ENV=live` makes the server impossible to mistake for a developer machine. `APP_URL` is used in emails
   and QR codes. The SMTP lines are explained under *Email* below.
5. **Open the site once.** The first request builds every table (about a second). Then sign in and check
   **Super Admin → Database**: it must say *Database is up to date*.
6. **Create the first Super Admin.** A new database has no users, and the demo accounts must **not** be used.
   - With SSH / a terminal:
     ```bash
     php database/create_admin.php --name "Your Name" --email you@yourdomain.com
     ```
     It prints a random password once. Change it after signing in.
   - Without a terminal: on your own computer run `php database/create_admin.php --hash "a long password"`,
     then in phpMyAdmin → `users` table → *Insert*: `name`, `email`, `password_hash` = the printed hash,
     `role` = `super_admin`, `status` = `active`.
7. **Add cities and users** from the Super Admin area (Manage Cities, All Users). A starter city can be added
   from System & Updates → Data packs.
8. **Turn on HTTPS** in the panel (free SSL) and redirect HTTP to HTTPS.
9. **Check the lock-down.** These must **not** open (403 or 404): `https://your-domain/.env`,
   `https://your-domain/database/schema.snapshot.json`, `https://your-domain/database/migrate.php`.
   If they do, `.htaccess` is not active: ask the host to allow `.htaccess` overrides (`AllowOverride All`).

## Updating the live site (every deploy)

1. **Back up** if the change is big: Super Admin → Database → *Backup (.sql)*.
2. **Update the files** (git pull or upload). Include `database/schema.snapshot.json` and any new files in
   `database/migrations/`. Do **not** overwrite `.env` or `uploads/`.
3. **Open any page.** Pending migrations and the new tables/columns apply themselves on that first request.
4. **Check Super Admin → Database.** Green = done. Red = read the error, fix, press *Update database now*.

The update only ever **adds**; it never deletes data or columns. To roll back, put the previous files back
(extra columns are harmless) and, if data was damaged, restore the backup in phpMyAdmin.

## Scheduled jobs (optional)

Four jobs run by themselves while people use the app: expire overdue riders (hourly), renewal reminders,
the data-integrity scan and old-session cleanup (daily). To make them run on time on quiet days too, add a
cron job in the panel (every 5 minutes is plenty):

```
*/5 * * * *  /usr/bin/php /home/<user>/domains/<your-domain>/public_html/cron/run.php
```

or call `https://your-domain/cron/run.php?token=<secret>` from a free pinger. The secret token is shown at
Super Admin → System & Updates → *Backup & scheduler*.

## Email

BodaERP sends password-reset links, new-account invites, sign-in codes, payment receipts and failure notices,
renewal reminders and the contact-form messages. Every template is editable at Super Admin → Email Templates.

1. In the hosting panel create a mailbox, for example `no-reply@your-domain`.
2. Put its details in `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`).
   Hostinger: `smtp.hostinger.com`, port `465`, `SMTP_SECURE=ssl`. Port 587 uses `SMTP_SECURE=tls`.
3. Super Admin → Email Templates → open any template → *Send test* to your own address.
4. Set Super Admin → Site Text & Branding → Contact details → *contact inbox* to the address that should receive
   contact-form messages.

Without SMTP nothing is lost: each email is saved to `storage/mail/` and listed in the Sent log, but nobody
receives it, and sign-in codes by email stay off (the yellow banner on the Email Templates page says so).

## SMS (EgoSMS)

Riders get payment receipts, renewal reminders, overdue notices and reminders pressed by their chairperson by text
message, and staff can choose to get their sign-in codes by SMS instead of email.

1. Open an account at egosms.co and ask for a sender ID (at most 11 characters, for example `BodaERP`).
2. Put `EGOSMS_USERNAME`, `EGOSMS_PASSWORD` and `EGOSMS_SENDER` in the server's `.env`. `EGOSMS_MODE` stays
   `sandbox` until you have tested; then set `EGOSMS_MODE=live`.
3. Super Admin → SMS → *Send test SMS* to your own phone. The same page shows the balance, every message sent
   (last three digits of the number only) and the wording of each message, per language.

Without the EgoSMS details nothing is sent: each message is saved to `storage/sms/` and listed in the log, and
codes by SMS cannot be chosen. A chairperson's "Send reminder" texts a rider at most once a day.

## USSD renewal (optional)

Riders without a smartphone can renew by dialling a code. EgoSMS does not sell USSD codes, so the code comes from
a USSD provider (for example Africa's Talking). The provider calls `https://your-domain/ussd.php?token=<USSD_TOKEN>`
with the usual form fields (`sessionId`, `phoneNumber`, `text`); BodaERP answers `CON` / `END`.

1. Generate a token (`php -r "echo bin2hex(random_bytes(24));"`) and put it in `.env` as `USSD_TOKEN`.
   Optionally list the provider's addresses in `USSD_ALLOWED_IPS`.
2. Give the provider the URL above with that token.
3. When the code is live, enter it in Super Admin → Site Text & Branding → General → `ussd_code`; SMS reminders
   then mention it.

The menu: *1. Renew a permit* (the caller's own permit, or any permit number) and *2. Check my permit*. Paying
starts an ordinary ioTec mobile-money request that the caller approves with their PIN, and the receipt comes by
SMS. Callers only ever see a masked name and the amount.

## Off-server backups

The nightly backup can also be emailed to an address outside the server: Super Admin → Site Text & Branding →
General → `backup_email`. Only encrypted backups are emailed, so set `BACKUP_KEY` first, and keep that key
somewhere other than the mailbox. Files over 18 MB are not emailed (most mail servers refuse them).

## Mobile-money payments (ioTec)

Payments are collected through ioTec Pay and are **in test mode** until you switch them on.

1. Put the keys in `.env`: `IOTEC_CLIENT_ID`, `IOTEC_CLIENT_SECRET`, `IOTEC_GRANT_TYPE`,
   `IOTEC_TEST_WALLET_ID`, `IOTEC_LIVE_WALLET_ID`, `IOTEC_IPN_SECRET`.
2. In ioTec's dashboard, point the payment notification (IPN) URL at `https://your-domain/ipn_handler.php`
   and use the same secret as `IOTEC_IPN_SECRET`.
3. Test mode charges the **test wallet** (no real money). Only after a full end-to-end test, set
   `IOTEC_MODE=live` in the server's `.env`. No code change or deploy is needed.
4. Callbacks without the right secret are refused, and even a correct callback only makes BodaERP re-check the
   payment with ioTec's API, so a forged callback can never confirm a payment.

## Before real users arrive: security checklist

- [ ] No demo accounts, no demo or sample data on the live database.
- [ ] A strong Super Admin password; the generated one was changed.
- [ ] `.env` exists, has `APP_ENV=live`, and is **not** downloadable (step 9).
- [ ] `APP_URL` is set to the real `https://` address. Without it, links in password-reset and invite emails
      are built from the request's host name, which a visitor can fake.
- [ ] `SHOW_DEMO_LOGINS` is **not** set in `.env` (it prints demo passwords on the login page).
- [ ] HTTPS is on.
- [ ] **Rotate secrets that were ever committed.** The old `config/database.php` (with the live database
      user and password) and the ioTec IPN secret exist in the git history. If the repository is, or ever
      was, shared beyond the team, change the database password in the hosting panel (then update `.env`)
      and generate a new IPN secret in ioTec.
- [ ] Backups: download one now and decide how often to repeat it (the hosting panel's own backups also help).
- [ ] Email works: a test from Super Admin → Email Templates arrives (needed for password reset and invites).
- [ ] Two-step sign-in for administrators: Platform Settings → *Require 2FA* (takes effect once email works).
- [ ] The Privacy Policy, Terms and Cookie Policy (Super Admin → Pages & Content) were reviewed by your legal adviser.
- [ ] Real contact details are filled in (Site Text & Branding → Contact details); empty ones are hidden.
- [ ] ID cards printed before this release carry the old QR link (permit number only, no photo on scan). Reprint them.

## Known limits on a live server

- **Email needs SMTP settings** (see *Email*). Until then reset links and invites are only saved to `storage/mail/`;
  an administrator can still set a password at Super Admin → All Users.
- **Demo helpers are off by design:** demo logins on the login page and the on-screen password-reset link
  only appear on a developer's own machine.
- Only the local database (MariaDB 10.4) was used when the automatic database updates were developed;
  the live server runs 11.8. After your first deploy, confirm the Database page is green.
