# Project Context
Last updated: 2026-11-07

## Current task
2026-11-06: rounds 4 and 5 (own controls, homepage, portal redesign, ID cards rebuilt on the national-ID layout,
holder signature, document-page layout, 23 copy-promised features, upgrades) committed and pushed at the owner's
request. Now: deploy to the Kakebe VPS (docs/DEPLOYMENT.md, "Kakebe VPS"). Then round 6 (docs/ROUND6_PLAN.md).

## Stack
PHP 8.1+ with mysqli, MariaDB/MySQL, plain JavaScript, CKEditor 5 (GPL, self-hosted). No Composer, no Node, no build.
Docker is a dev convenience only (`docker compose up -d --build`, ports 7080-7082); XAMPP and the live Hostinger
server run without it. Full detail in CLAUDE.md, README.md and docs/.

## Recent decisions
- 2026-11-06: Rider ID card is view only; renewal is requested and paid by the rider, staff are notified (round 6).
- 2026-11-06: Chairpersons never delete riders; city admin and super admin do, with a reason (round 6).
- 2026-11-06: Browser-drawn controls are replaced by controls.js everywhere; never call alert/confirm/prompt (owner: "system style").
- 2026-11-06: Portal design copies the Goodname Finance staff portal (owner). Outfit replaces Inter.
- 2026-11-06: No hero badge, slide numbers or pause button; autoplay stops once the visitor uses the arrows (owner).
- 2026-11-06: Public text speaks to why, how and for whom; nothing about where the system is made (owner). Legal pages keep the operator name.
- 2026-11-06: Homepage photos are Wikimedia Commons (CC BY-SA/CC0), credited on /p/photo-credits.
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
- Deploy to the Kakebe VPS behind a shared Traefik, copy the live database, switch DNS (owner approved a brief Roru outage).
- Round 6 phases 1-8 in docs/ROUND6_PLAN.md.
- Owner reviews round 4 (homepage, portals, controls); commit when asked (the vendor/ folder must go in with it).
- Owner reviews round 2 and round 3 in the browser and sends feedback.
- With EgoSMS sandbox credentials: send a test SMS from Super Admin → SMS, then try SMS sign-in codes.

## Active branches
- main: the only branch
