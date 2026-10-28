# Code Blocks Laboratory Equipment Borrowing System

Laboratory Equipment Inventory and Borrowing Management System

A PHP + MySQL web application for a university laboratory. Code Blocks Laboratory Equipment Borrowing System keeps
equipment quantities, borrowing decisions, returns, condition reports, reports,
notifications, and audit history in one place.

The interface uses a light/dark glassmorphism design with responsive layouts
for desktop, tablet, and mobile. The application is a portfolio project and is
not intended for production use without the hardening steps listed at the end
of this file.

## Main features

- Role-based access for administrators/laboratory personnel, instructors, and
  students.
- Equipment catalog with search, category/availability/condition filters, and
  archive/restore support.
- Equipment quantity model:
  `total = available + borrowed + damaged + under repair + lost`.
- Borrowing workflow: request, approve or reject, checkout, partial return,
  final return, overdue tracking.
- Condition-first returns: a return cannot be recorded without a condition
  report, and damaged/under-repair units never return silently to available
  stock.
- Reports for inventory, borrowing, returns, pending requests, overdue items,
  condition history, and borrowers, with print and CSV export.
- In-app notification center with unread count, mark-read, dismiss, and history.
- Audit trail for security-sensitive and inventory-changing actions.
- Instant light/dark theme switch, persisted in the browser.
- Responsive navigation, accessible forms, keyboard-friendly dialogs, focus
  indicators, and reduced-motion support.

## Technology

| Layer | Technology |
|---|---|
| Presentation | HTML5, CSS3, vanilla JavaScript, inline SVG icons |
| Application | PHP 8.2, custom router/controllers/services/models |
| Data | MySQL 8, PDO prepared statements, transactions, row locking |
| Local server | Apache and MySQL from XAMPP |
| Browser automation | Python 3.12, Selenium 4, Chrome |
| Acceptance testing | Python, requests, PyMySQL |

There is no front-end framework or build step. CSS and JavaScript are served
directly from `public/assets/`.

## Setup with XAMPP

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy the project to `C:\xampp\htdocs\codeblocks-lab-inventory`.
3. Create and seed the database:
   ```bash
   php database/setup.php --seed --force
   ```
   XAMPP defaults are used: host `127.0.0.1`, port `3306`, user `root`, empty
   password.
4. Open:
   `http://localhost/codeblocks-lab-inventory/public/`
5. Sign in with a demo account below.

If MySQL runs on a different port, copy `config/config.local.example.php` to
`config/config.local.php` (or edit `config/config.php`) and set the port. The
acceptance and Selenium runners accept `--db-port` as well.

## Deploying to InfinityFree

GitHub Pages cannot run this application's PHP backend or MySQL database. The
repository can be hosted on a PHP/MySQL provider such as InfinityFree; the
source repository itself is not a running website.

The current InfinityFree File Manager location for this project is
`/htdocs/codeblocks-lab-inventory/`. Upload the project contents there, keeping
the `app/`, `config/`, `database/`, and `public/` folders together. The
application entry point is in `public/`; after setup, open:

`https://codeblockslaboratory.freepage.cc/codeblocks-lab-inventory/public/login`

### Configure the database

1. In the InfinityFree control panel, create a MySQL database and note the
   assigned database host, database name, username, and password. Use the
   exact values shown there; hosted database names and users are usually
   prefixed and differ from the local XAMPP defaults.
2. Open that database in phpMyAdmin and select it before importing.
3. Import `database/schema.sql`, followed by `database/seed.sql`. The SQL files
   operate on the selected database and do not try to create an unprefixed
   database. **The seed file deletes existing rows from the demo tables**; do
   not import it into a database with real data.
4. In File Manager, create
   `/htdocs/codeblocks-lab-inventory/config/config.local.php` with the
   following settings, replacing all database placeholders with the values
   from the hosting control panel:

   ```php
   <?php
   return [
       'app' => [
           'url' => 'https://codeblockslaboratory.freepage.cc/codeblocks-lab-inventory/public',
           'env' => 'production',
           'debug' => false,
           'pretty_urls' => true,
           'show_demo_credentials' => false,
       ],
       'db' => [
           'host' => 'YOUR_ASSIGNED_MYSQL_HOST',
           'port' => '3306',
           'name' => 'YOUR_ASSIGNED_DATABASE_NAME',
           'user' => 'YOUR_ASSIGNED_DATABASE_USERNAME',
           'password' => 'YOUR_DATABASE_PASSWORD',
           'charset' => 'utf8mb4',
       ],
   ];
   ```

   Keep this file private and do not commit it. The repository ignores
   `config/config.local.php`.
5. Test the login page above. The URL in `app.url` must include the
   `/codeblocks-lab-inventory/public` path for this folder layout; leaving it
   at the domain root makes the router generate links that do not match the
   installed application path.
6. Change or remove the seeded demo accounts before public use. Their
   credentials are listed above and are intended only for classroom testing.

Use the hosting provider's FTP/File Manager and database control panel for
secrets. Do not send database passwords or FTP credentials in chat or store
them in the public repository.

## Demo accounts

| Role | Email | Password | Notes |
|---|---|---|---|
| Administrator / Lab Personnel | `admin@codeblocks.test` | `Admin@12345` | Full inventory and approval access |
| Instructor | `maria.santos@codeblocks.test` | `Instructor@12345` | Can borrow; admin routes are blocked |
| Student | `ana.reyes@codeblocks.test` | `Student@12345` | Borrower account |
| Student | `juan.delacruz@codeblocks.test` | `Student@12345` | Second borrower for ownership checks |
| Instructor | `paolo.garcia@codeblocks.test` | `Instructor@12345` | Seeded borrower |
| Inactive student | `mia.torres@codeblocks.test` | `Student@12345` | Used to verify inactive-account rejection |

These are demo credentials only. Change every password before exposing the
application outside a local training environment.

## Theme and notification behaviour

- The theme key is `codeblocks-theme` in `localStorage`. The theme is applied
  before paint by a small script in the layout, so refresh and login do not
  flash the wrong theme.
- The top-right toggle switches between light and dark, updates
  `aria-pressed`, and changes its icon to show the target mode.
- Notifications are available from the bell menu and the notification center.
  Mark-read and dismiss actions use AJAX and update MySQL immediately; the item
  disappears without a page reload.
- Dismissed notifications stay hidden after refresh because the
  `notifications.dismissed_at` column is persisted. History (`?show=all`) shows
  read notifications that were not dismissed.

## Accessibility and responsive behaviour

- Skip link, semantic landmarks, labelled form controls, visible focus rings,
  and `aria-live` feedback.
- Confirm dialogs use `role="dialog"`, `aria-modal`, Escape-to-close, a focus
  trap, and focus return to the trigger.
- `prefers-reduced-motion` disables entrance and chart animation.
- Breakpoints at 980 px (sidebar becomes a drawer), 760 px (single-column
  forms and card-style tables), and 480 px (compact top bar and page padding).
- Verified at a true 390 px mobile emulation width with no horizontal page
  overflow.

## Tests

### Selenium browser journey

```bash
python tests/selenium/run_tests.py --reset-db --db-port 3307 \
  --base-url http://127.0.0.1:8080/codeblocks-lab-inventory/public
```

Latest result: **4 run, 0 failed** (2026-10-28).
Evidence: `tests/selenium/evidence/20261028-115959/`.

### 50-case acceptance and security regression

```bash
python tests/regression/acceptance_suite.py --reset-db --db-port 3307 \
  --base-url http://127.0.0.1:8080/codeblocks-lab-inventory/public
```

Latest result: **50 run, 50 passed** (2026-10-28).
Evidence: `tests/regression/evidence/20261028-120047/`.

The suite covers security headers, session rotation, RBAC/ownership, CSRF,
CRUD, quantity invariants, borrow/approve/return branches, reports/CSV,
notifications, audit filtering, lockout, and logout.

### Static checks

```bash
# PHP syntax
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

# JavaScript syntax (Node.js)
node --check public/assets/js/app.js
```

Latest result: all PHP files pass lint; JavaScript syntax check passes.

## Screenshots

Selected final screenshots are in [`docs/screenshots`](docs/screenshots):

- Login, light and dark.
- Admin dashboard, equipment catalog, reports, notifications, and transactions
  in both themes.
- Confirm dialog, notification menu, and account menu.
- Tablet and 390 px mobile layouts.
- Mobile sidebar and notification menu.

## Documentation

- [System overview](docs/system-overview.md)
- [UI redesign notes](docs/ui-redesign.md)
- [Database design](docs/database-design.md)
- [Test plan](docs/test-plan.md)
- [Test evidence](docs/test-evidence.md)
- [Defect log](docs/defect-log.md)
- [Known limitations](docs/known-limitations.md)
- [Portfolio evidence checklist](docs/portfolio-checklist.md)
- [Manual test cases](tests/manual-test-cases.md)

## Before production use

- Replace demo passwords and disable/remove demo accounts.
- Serve over HTTPS and set secure, HttpOnly, SameSite cookies; keep
  `APP_DEBUG` off.
- Move credentials out of source control and restrict database privileges.
- Add institutional SSO, email/SMS delivery, and backup/restore procedures if
  required.
- Re-run the acceptance and Selenium suites after any deployment change.
