# Manual Test Cases

| ID | Requirement | Objective | Preconditions | Data | Steps | Expected | Priority |
|---|---|---|---|---|---|---|---|
| TC-01 | Auth | Valid login | Seeded DB | admin@codeblocks.test | Login | Dashboard shown | High |
| TC-02 | RBAC | Student cannot approve | Logged in as student | request id | POST approve | 403 | High |
| TC-03 | Return | Missing condition rejected | Active transaction | none | Submit return no condition | Rejected, no update | High |
| TC-04 | Inventory | Damaged not available | Damaged item | - | View catalog | Not available | High |