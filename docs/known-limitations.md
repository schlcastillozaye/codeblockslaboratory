# Known Limitations

- External integrations (email/SMS, institutional SSO, barcode scanners) are not configured; in-app notifications are used.
- `http_response_code(419)` is replaced with an explicit header because Apache/PHP did not emit 419 reliably.
- Demo credentials are for local testing only.