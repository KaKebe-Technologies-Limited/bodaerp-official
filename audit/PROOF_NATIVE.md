# Native browser controls: inventory and proof (2026-11-06)

Every control the browser used to draw in its own style is now drawn by BodaERP (`assets/js/controls.js` +
`assets/css/controls.css`, loaded by the public, sign-in and portal shells). The original element stays in the form,
hidden, so the value posts under the same name and page scripts that read or set `.value` keep working.

## Inventory and what happened to each

| Native surface | Before | Now |
|---|---|---|
| `window.confirm()` | fallback in `main.js` when Bootstrap was missing | removed; `BodaUI.confirm()` only, and a missing controls.js refuses the action instead of going ahead |
| `window.prompt()` | "Copy this link" fallback (2 calls) | `BodaUI.showText()` dialog with the text selected |
| `window.alert()` | none | none |
| `<select>` | 67 in 27 files | listbox (`role=combobox` button + `role=listbox`); filter box above 10 options; list capped at 200 rows with "type to narrow"; optgroups; typeahead, arrows, Home/End, PageUp/Down, Esc |
| `type="date"` | 4 | text field (dd/mm/yyyy, also accepts yyyy-mm-dd and 2-digit years) + calendar dialog (arrows, PageUp/Down, Shift+PageUp/Down for years, Today, Clear, min/max) |
| `type="range"` | 4 (revenue splits) | `role=slider` with pointer drag and keyboard; fires `input`/`change` on the hidden original, so the split redistribution still runs |
| `type="color"` | 1 (branding) | swatch button + palette of brand colours; the hex field beside it stays the precise input |
| `type="number"` | 22 | text field with the numeric keyboard (`inputmode`); range checked by the validator with plain messages |
| `type="file"` | 5 visible | drop zone with "Choose a file", file list, remove buttons, type and size checks (`data-max-mb`) for picked and dropped files |
| `type="search"` | 2 | browser clear button hidden, own clear button |
| `<datalist>` | 1 (users: agency) | suggestion combobox |
| `<details>/<summary>` | FAQ page and home | accordion buttons (`render_faq_item()` in site.php) |
| `title=""` tooltips | 38 | tooltip on hover and keyboard focus, Esc closes; icon-only buttons keep their accessible name |
| validation bubbles | 102 of 103 forms validated natively | every form `noValidate`; inline messages under the field, linked with `aria-describedby`, focus moves to the first problem, re-checked on blur and change |
| scrollbars | 6px webkit only | 10px, styled in Chromium and Firefox |
| autofill, selection, caret, placeholder, tap highlight, checkbox/radio glyphs, number spinners, Edge password reveal | browser defaults | styled in controls.css |

## Bugs found on the way
- `pattern="[A-Za-z0-9-]{4,40}"` (permit number, home and verify) and `[a-z0-9-]*` (page slug) are invalid in the
  `v`-flag regex mode browsers now use, so the browser ignored them. Fixed (`\-`).
- Bare confirm copy ("Delete this rider?") now names the rider, plate, stage counts or amount; deleting a city asks
  for the city name to be typed; bulk actions say how many rows are ticked and refuse when none are.

## Evidence (Playwright, Chromium, one window)
- 66 pages (public, sign-in, every sidebar page of all 5 roles, My account), re-run after the portal redesign: zero native `<select>`, date, range,
  colour, number, file or datalist controls left, zero `title` attributes, every form `noValidate`, zero console errors.
- `alert`/`confirm`/`prompt` poisoned by an init script and a `dialog` listener on the page for the whole session: 0 calls.
- By hand: select keyboard and filter (enforcement modal, 355 riders), calendar keys and typed dates, slider keys
  and drag, colour swatch both ways, file type check, typed city-name confirmation, Esc and focus return, inline errors
  on login, contact, home permit check, enforcement and branding forms.

## Not covered
- Firefox and Safari were not run (the Playwright server here drives Chromium only).
- No screen-reader walkthrough; roles, names and states follow the WAI-ARIA patterns but were checked in the DOM only.
- `forced-colors` rules are written but not checked in an emulated high-contrast mode.
- Upload progress per file: forms post normally, so the submit button shows the busy state instead.
- Server-side errors still show as the alert at the top of the form, not under the field.
- The file chooser window, the print dialog and autofill pop-ups belong to the browser and cannot be replaced.
