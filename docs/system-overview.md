# System Overview

Code Blocks Laboratory Equipment Borrowing System is a web-based laboratory equipment inventory and borrowing
system. It follows a three-tier structure:

- **Presentation:** server-rendered PHP views, HTML5, CSS, and vanilla
  JavaScript.
- **Application:** PHP router, controllers, validation, services, models, and
  event listeners.
- **Data:** MySQL accessed through PDO prepared statements, with transactions
  and row locking for stock-changing operations.

## Roles

| Role | Main responsibilities |
|---|---|
| Administrator / Laboratory Personnel | Equipment records, condition buckets, approvals, returns, users, reports, audit trail |
| Instructor | Browse equipment, submit borrowing requests, view own requests and returns |
| Student | Browse equipment, submit borrowing requests, view own requests and returns |
| Guest | Login page only; all application routes require authentication |

Administrators cannot approve their own borrowing request because the approval
screen is restricted to laboratory personnel and the owner is checked at the
controller/service boundary. Students and instructors can only open their own
borrowing records.

## Modules

1. **Authentication and session security**
   - Email or school/employee ID login.
   - Password hashing, session rotation on login and password change.
   - CSRF token on state-changing requests.
   - Login attempt lockout after repeated failures.
   - Inactive-account rejection.

2. **Equipment inventory**
   - Category, code, total quantity, location, description, and record status.
   - Quantity buckets: available, borrowed, damaged, under repair, lost.
   - Condition adjustment moves units between buckets without changing the
     total.
   - Archive/restore; archived or damaged equipment cannot be checked out.

3. **Borrowing**
   - Request creation with quantity, purpose, and expected return date.
   - Requests do not reserve stock until approval.
   - Approval re-checks availability under a row lock and creates a checkout
     transaction.
   - Duplicate approvals, duplicate requests, and over-quantity requests are
     rejected.
   - Owners can cancel pending requests.

4. **Returns**
   - Return requires a condition report.
   - Supports partial and final returns.
   - Damaged, under-repair, and lost units move to the correct bucket; they do
     not silently re-enter available stock.
   - Over-returns, future dates, and duplicate returns are rejected.

5. **Reports**
   - Inventory, borrowing, returns, pending requests, overdue checkouts,
     condition register, and borrowers.
   - CSV export and print view use the same live data as the page.

6. **Notifications**
   - In-app notifications for approvals, rejections, returns, condition
     attention, and due-date reminders.
   - Unread count, mark-read, dismiss, read-all, and history.
   - Read/dismiss state is persisted in MySQL.

7. **Audit trail**
   - Records significant actions with actor, action, entity, before/after
     context, and source address.
   - Filters by action, actor, date, and description.
   - Loopback source addresses are shown as "On-site" in the interface.

8. **Theme and accessibility layer**
   - Light/dark themes with a pre-paint theme script.
   - Glass surfaces, responsive sidebar, cards, tables, forms, charts, dialogs,
     and notifications.
   - Reduced-motion support and keyboard-friendly interactions.

## Request lifecycle

```
pending ──approve──> approved (checkout created) ──partial return──> approved
   │                                  │
   ├──reject──> rejected              └──final return──> completed
   └──cancel──> cancelled
```

A request is a record of intent. A checkout is a `borrow_transactions` row. A
return is a `return_records` row; a transaction can have multiple return rows
until the outstanding quantity reaches zero.

## Inventory invariant

For every equipment row:

```
total = available + borrowed + damaged + under_repair + lost
```

The application enforces this in services, and MySQL enforces it again with a
CHECK constraint. Every stock movement runs inside a database transaction; the
approval and condition-adjustment paths lock the equipment row first.

## Security boundaries

- PDO prepared statements for all queries.
- CSRF validation on POST routes.
- Role and ownership checks in controllers/services, not only in views.
- Password hashing with PHP `password_hash`.
- Output escaping through the view helper.
- Security headers emitted from `app/bootstrap.php`; debug exceptions are
  logged server-side and rendered as a friendly error page.
- Demo data is local-only and must not be treated as production credentials.
