# Test Plan

## Purpose

Verify that the redesigned Code Blocks Laboratory Equipment Borrowing System interface preserves the laboratory
business rules while remaining usable, readable, responsive, and accessible in
both light and dark themes.

## Scope

### In scope

- Authentication, session rotation, inactive-account rejection, login lockout.
- Role-based access control and record ownership.
- CSRF protection on state-changing requests.
- Equipment create/edit/archive/restore and condition-bucket movement.
- Borrow request creation, approval, rejection, cancellation.
- Checkout, partial return, final return, damaged return, overdue state.
- Inventory quantity invariant and database CHECK constraint.
- Reports and CSV export.
- User management and profile/password changes.
- In-app notifications, read/dismiss persistence, unread count.
- Audit trail and filtering.
- Light/dark theme, persistence, component readability.
- Desktop, tablet, and 390 px mobile layout.
- Keyboard operation of dialogs, focus visibility, reduced motion.
- Removal of development-only visible text.

### Out of scope

- Formal WCAG 2.2 AA certification by an external auditor.
- Cross-browser matrix beyond the installed Chrome build.
- Screen-reader verification with NVDA/JAWS/VoiceOver.
- Email/SMS delivery, institutional SSO, and barcode hardware.
- Load, soak, and penetration testing.
- Production deployment hardening.

## Environment

| Item | Value |
|---|---|
| Server | XAMPP Apache 2.4.58 |
| PHP | 8.2.x |
| MySQL | 8.0.45 on port 3307 for this run |
| Browser | Chrome 156 (headless for automation) |
| Python | 3.12 |
| Selenium | 4.51.0 |
| Requests / PyMySQL | 2.32.5 / 2.2.8 |
| Base URL | `http://127.0.0.1:8080/codeblocks-lab-inventory/public` |
| Database | `codeblocks_lab_inventory` |

## Test approach

1. **Static checks** — PHP syntax, JavaScript syntax, source review for debug
   text and duplicated security headers.
2. **Browser journey (Selenium)** — real browser clicks through login, RBAC,
   full borrow → approve → return → report flow, CSV download, and evidence
   screenshots.
3. **Acceptance/security regression (HTTP + DB)** — 50 independent cases driven
   by independent admin/instructor/student/guest sessions, with direct MySQL
   assertions after each case.
4. **Visual QA** — light/dark screenshots at desktop, tablet, and 390 px mobile
   widths; console error inspection; keyboard and focus checks.
5. **Text cleanup scan** — rendered page text checked for development-only
   terms such as localhost, 127.0.0.1, stack traces, and server details.

## Entry criteria

- Apache and MySQL running.
- Database seeded with `php database/setup.php --seed --force`.
- Application reachable at the configured base URL.
- Selenium/Chrome and Python dependencies installed.

## Exit criteria

- Selenium suite: 4/4 pass.
- Acceptance suite: 50/50 pass.
- All PHP files pass lint; JavaScript syntax check passes.
- No severe browser console errors on tested pages.
- No horizontal page overflow at 390 px; desktop and tablet layouts usable.
- Both themes remain readable on tested pages.
- No visible development-only technical text in the tested interface.

## Latest execution

| Suite | Result | Evidence |
|---|---|---|
| Selenium browser journey | 4 run, 0 failed | `tests/selenium/evidence/20261028-115959/` |
| Acceptance/security regression | 50 run, 50 passed | `tests/regression/evidence/20261028-120047/` |
| PHP lint | All files pass | command output in `docs/test-evidence.md` |
| JavaScript syntax | Pass | `node --check public/assets/js/app.js` |
| Visual/theme/responsive QA | Pass in Chrome | `docs/screenshots/` |

## Case coverage map

| Area | Acceptance cases | Browser coverage |
|---|---|---|
| Security headers/session | MTC-01, MTC-02, MTC-07, MTC-48, MTC-50 | Selenium test 01 |
| RBAC/ownership | MTC-05, MTC-06, MTC-23, MTC-38, MTC-45, MTC-46, MTC-47 | Selenium test 02 |
| Equipment | MTC-09–MTC-14 | Visual + HTTP |
| Borrowing | MTC-15–MTC-25 | Selenium test 03 |
| Returns | MTC-26–MTC-33 | Selenium test 03 |
| Reports/CSV | MTC-34, MTC-35 | Selenium test 04 |
| Users/profile | MTC-36–MTC-41 | Visual + HTTP |
| Notifications/audit | MTC-42, MTC-43 | Visual + HTTP |
| Responsive/static | MTC-44 | Visual screenshots |
| Stock re-check | MTC-49 | HTTP + DB |

The detailed case table is in `tests/manual-test-cases.md`. The executable
acceptance suite is `tests/regression/acceptance_suite.py`.

## Commands

```bash
# Seed clean demo data (destructive)
php database/setup.php --seed --force

# Selenium
python tests/selenium/run_tests.py --reset-db --db-port 3307 \
  --base-url http://127.0.0.1:8080/codeblocks-lab-inventory/public

# Acceptance/security regression
python tests/regression/acceptance_suite.py --reset-db --db-port 3307 \
  --base-url http://127.0.0.1:8080/codeblocks-lab-inventory/public

# Static checks
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check public/assets/js/app.js
```

## Risks and limitations

- The acceptance suite is HTTP-level, not a replacement for manual exploratory
  testing; visual judgments still require a human review.
- The Selenium suite uses a desktop viewport. Mobile layout was verified with
  Chrome device metrics and screenshots, not a physical phone.
- No external accessibility audit has been performed. Basic keyboard,
  focus-trap, label, heading, and contrast checks were performed manually and
  with browser inspection.
- The demo database is local-only; timings and records are not production
  benchmarks.
