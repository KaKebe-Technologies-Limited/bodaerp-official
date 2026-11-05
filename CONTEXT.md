# Project Context
Last updated: 2026-11-06

## Current task
Round 3 done: SMS through EgoSMS, optional sign-in codes by SMS or email, USSD renewal, cash handover, late fees,
duplicate check, rider import, roadside checks, off-server backup email, languages mechanism, design pass, and a
platform-wide anti-AI-tell sweep. Committed and pushed to main. Waiting for the owner's review.

## Stack
PHP 8.1+ with mysqli, MariaDB/MySQL, plain JavaScript, CKEditor 5 (GPL, self-hosted). No Composer, no Node, no build.
Docker is a dev convenience only (`docker compose up -d --build`, ports 7080-7082); XAMPP and the live Hostinger
server run without it. Full detail in CLAUDE.md, README.md and docs/.

## Recent decisions
- 2026-11-06: SMS provider is EgoSMS (owner). Without credentials messages go to storage/sms/ and the SMS log.
- 2026-11-06: No authenticator app anywhere (owner). Sign-in codes are optional per user, by SMS or email; the
  platform `require_2fa` setting still forces them for staff roles.
- 2026-11-06: USSD uses the common aggregator format (sessionId, phoneNumber, text; CON/END), token-protected,
  because EgoSMS sells no USSD codes.
- 2026-11-06: Translations stay switched off until a native speaker reviews them.
- 2026-11-06: `.claude/` is no longer tracked in git (tooling, not product).

## Known issues
- Owner actions block launch: SMTP, ioTec end-to-end test, EgoSMS account, USSD short code, legal review, real
  contact details, ID card reprint, secret rotation, live-server migration test (CLAUDE.md → BLOCKING).
- Test scripts do not run JavaScript; button behaviour is proven only by the Playwright pass.

## Next steps
- Owner reviews round 2 and round 3 in the browser and sends feedback.
- With EgoSMS sandbox credentials: send a test SMS from Super Admin → SMS, then try SMS sign-in codes.

## Active branches
- main: the only branch
