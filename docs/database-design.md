# Database Design

Database name: `codeblocks_lab_inventory`

The authoritative schema and demo data are in:

- `database/schema.sql`
- `database/seed.sql`
- `database/setup.php`

## Tables

| Table | Purpose |
|---|---|
| `users` | Accounts, role, department, school ID, password hash, and active status |
| `login_attempts` | Login identifier, source address, attempt time, and lockout tracking |
| `categories` | Equipment categories and display order |
| `equipment` | Equipment master record and five quantity buckets |
| `borrow_requests` | Request header, requester, status, review metadata |
| `borrow_request_items` | Equipment and requested quantity for each request |
| `borrow_transactions` | Approved checkout, due date, outstanding/returned quantity, status |
| `return_records` | Return event, condition, quantity, notes, receiving user |
| `equipment_condition_history` | Bucket movement and condition audit per equipment |
| `notifications` | In-app messages, read timestamp, dismissed timestamp, optional link |
| `audit_logs` | Actor, action, entity, description, before/after JSON, source address |

## Key relationships

```
users 1 ── * borrow_requests
borrow_requests 1 ── * borrow_request_items
borrow_requests 1 ── * borrow_transactions
borrow_request_items 1 ── 0..1 borrow_transactions
borrow_transactions 1 ── * return_records
equipment 1 ── * borrow_request_items
equipment 1 ── * borrow_transactions
equipment 1 ── * equipment_condition_history
users 1 ── * notifications
users 1 ── * audit_logs
```

Foreign keys use restrictive/cascade rules appropriate to the record: master
records are not silently deleted while dependent history exists. Indexes cover
status, date, user, equipment, and notification read-state queries.

## Quantity model

```
total_quantity = available_quantity + borrowed_quantity
               + damaged_quantity + under_repair_quantity
               + lost_quantity
```

The application updates buckets through services that lock the equipment row.
The database also has `chk_equipment_quantities` as a second line of defence.

### Approval

1. Lock the request and its equipment rows.
2. Re-check that the equipment is active, in good condition, and has enough
   available units.
3. Move the requested quantity from available to borrowed.
4. Create the checkout transaction and close the request as approved.
5. Notify the borrower and write audit/condition history.

If any step fails, the transaction rolls back.

### Return

1. Lock the transaction and its equipment.
2. Validate quantity, condition, and return date.
3. Move the returned units from borrowed to available, damaged, under repair,
   or lost according to the condition.
4. Insert the return record, update the transaction's returned/outstanding
   quantity, and close the request when fully returned.
5. Notify the borrower and write audit history.

## Notification persistence

`notifications` includes:

- `is_read` and `read_at` for read state.
- `dismissed_at` for hidden/dismissed state.
- Optional `link` for opening the related record.

The notification center defaults to unread items. `?show=all` shows read
history that has not been dismissed. Dismissed items remain in the database but
are excluded from both views and the unread count.

## Seed data

The seed is intentionally small but realistic:

- 6 users (administrator, instructors, students, one inactive student).
- 6 equipment categories.
- 18 equipment records with varied conditions and archived state.
- Borrow requests across pending, approved, rejected, cancelled, and completed
  states.
- Checkout transactions, returns, notifications, and audit entries.

Run:

```bash
php database/setup.php --seed --force
```

The command is destructive: it drops and recreates the demo database. Do not
run it against a real deployment.

## Configuration

`config/config.php` holds XAMPP defaults. `config/config.local.php` can override
host, port, database name, user, password, and debug flag. Credentials should
not be committed for a real deployment.
