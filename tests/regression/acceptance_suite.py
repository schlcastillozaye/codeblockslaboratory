#!/usr/bin/env python3
"""
Code Blocks - manual/acceptance regression suite.

This suite mirrors the manual test cases in Appendix F. It drives the running
application over HTTP with independent admin, instructor, and student sessions,
then checks the resulting database state directly. It is intentionally separate
from the Selenium suite:

  * Selenium proves the browser journey and captures screenshots.
  * This suite proves server-side rules, authorization, validation, database
    invariants, reports, notifications, and audit entries across many cases.

Usage (from the project root):
    python tests/regression/acceptance_suite.py --reset-db --db-port 3307

Evidence is written to tests/regression/evidence/<timestamp>/.
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import io
import json
import os
import re
import subprocess
import sys
import time
import traceback
from pathlib import Path
from typing import Callable

import pymysql
import requests

HERE = Path(__file__).resolve().parent
PROJECT_ROOT = HERE.parent.parent

ADMIN = ("admin@codeblocks.test", "Admin@12345")
INSTRUCTOR = ("maria.santos@codeblocks.test", "Instructor@12345")
STUDENT = ("ana.reyes@codeblocks.test", "Student@12345")
OTHER_STUDENT = ("juan.delacruz@codeblocks.test", "Student@12345")
INACTIVE = ("mia.torres@codeblocks.test", "Student@12345")


class CaseFailure(AssertionError):
    pass


class Result:
    def __init__(self, case_id: str, title: str) -> None:
        self.case_id = case_id
        self.title = title
        self.status = "FAIL"
        self.actual = ""
        self.error = ""
        self.seconds = 0.0
        self.technique = ""


class Runner:
    def __init__(self, log_path: Path) -> None:
        self.results: list[Result] = []
        self.log_path = log_path
        self.log_file = log_path.open("w", encoding="utf-8")
        self.started = dt.datetime.now()

    def log(self, line: str = "") -> None:
        print(line)
        self.log_file.write(line + "\n")
        self.log_file.flush()

    def run(self, case_id: str, title: str, fn: Callable[[], str], technique: str = "") -> Result:
        result = Result(case_id, title)
        result.technique = technique
        self.log(f"\n[{case_id}] {title}")
        started = time.perf_counter()
        try:
            actual = fn()
            result.status = "PASS"
            result.actual = actual or "Observed result matched the expected result."
            self.log(f"  PASS - {result.actual}")
        except Exception as exc:  # noqa: BLE001 - evidence must capture any failure
            result.status = "FAIL"
            result.error = f"{exc.__class__.__name__}: {exc}"
            result.actual = result.error
            self.log(f"  FAIL - {result.error}")
            if not isinstance(exc, CaseFailure):
                self.log(traceback.format_exc().rstrip())
        result.seconds = time.perf_counter() - started
        self.results.append(result)
        return result

    def summary(self) -> dict:
        passed = sum(1 for r in self.results if r.status == "PASS")
        failed = len(self.results) - passed
        return {
            "started": self.started.isoformat(timespec="seconds"),
            "finished": dt.datetime.now().isoformat(timespec="seconds"),
            "total": len(self.results),
            "passed": passed,
            "failed": failed,
            "cases": [
                {
                    "id": r.case_id,
                    "title": r.title,
                    "technique": r.technique,
                    "status": r.status,
                    "actual": r.actual,
                    "seconds": round(r.seconds, 3),
                }
                for r in self.results
            ],
        }

    def close(self) -> None:
        s = self.summary()
        self.log("\n" + "=" * 78)
        self.log(f"Finished : {s['finished']}")
        self.log(f"Tests    : {s['total']} run, {s['passed']} passed, {s['failed']} failed")
        self.log("=" * 78)
        (self.log_path.parent / "summary.json").write_text(json.dumps(s, indent=2), encoding="utf-8")
        self.log_file.close()


class App:
    """Small HTTP client per role, with CSRF handling."""

    def __init__(self, base_url: str) -> None:
        self.base = base_url.rstrip("/")
        self.admin = self._session()
        self.instructor = self._session()
        self.student = self._session()
        self.other_student = self._session()
        self.guest = self._session()

    @staticmethod
    def _session() -> requests.Session:
        s = requests.Session()
        s.trust_env = False
        s.headers.update({"User-Agent": "CodeBlocks-Acceptance/1.0"})
        return s

    def url(self, path: str) -> str:
        if path.startswith("http"):
            return path
        return self.base + "/" + path.lstrip("/")

    def get(self, session: requests.Session, path: str, **kwargs) -> requests.Response:
        return session.get(self.url(path), timeout=25, **kwargs)

    def csrf(self, session: requests.Session, token_path: str = "/dashboard") -> str:
        response = self.get(session, token_path)
        match = re.search(r'name="_token" value="([^"]+)"', response.text)
        if not match:
            raise CaseFailure(
                f"CSRF token not found on {token_path} (HTTP {response.status_code}, final URL {response.url})"
            )
        return match.group(1)

    def post(
        self,
        session: requests.Session,
        path: str,
        data: dict | None = None,
        *,
        token_path: str | None = None,
        follow: bool = True,
    ) -> requests.Response:
        body = dict(data or {})
        body["_token"] = self.csrf(session, token_path or "/dashboard")
        return session.post(self.url(path), data=body, timeout=25, allow_redirects=follow)

    def login(self, session: requests.Session, account: tuple[str, str], expect_success: bool = True) -> requests.Response:
        response = self.get(session, "/login")
        token = re.search(r'name="_token" value="([^"]+)"', response.text).group(1)
        response = session.post(
            self.url("/login"),
            data={"login": account[0], "password": account[1], "_token": token},
            timeout=25,
            allow_redirects=True,
        )
        if expect_success and "/dashboard" not in response.url:
            raise CaseFailure(f"Login failed for {account[0]}; final URL {response.url}")
        return response

    def logout(self, session: requests.Session) -> None:
        self.post(session, "/logout")


class Database:
    def __init__(self, host: str, port: int, name: str, user: str, password: str) -> None:
        self.args = dict(host=host, port=port, user=user, password=password, database=name, charset="utf8mb4")

    def connect(self, autocommit: bool = True):
        return pymysql.connect(**self.args, autocommit=autocommit, cursorclass=pymysql.cursors.DictCursor)

    def one(self, sql: str, params: tuple = ()) -> dict | None:
        with self.connect() as conn, conn.cursor() as cur:
            cur.execute(sql, params)
            return cur.fetchone()

    def all(self, sql: str, params: tuple = ()) -> list[dict]:
        with self.connect() as conn, conn.cursor() as cur:
            cur.execute(sql, params)
            return list(cur.fetchall())

    def scalar(self, sql: str, params: tuple = ()) -> object:
        with self.connect() as conn, conn.cursor() as cur:
            cur.execute(sql, params)
            row = cur.fetchone()
            return next(iter(row.values())) if row else None


def expect(condition: bool, message: str) -> None:
    if not condition:
        raise CaseFailure(message)


def contains(response: requests.Response, *needles: str) -> None:
    for needle in needles:
        expect(needle.lower() in response.text.lower(), f"Expected '{needle}' in HTTP {response.status_code} response ({response.url})")


def no_php_errors(response: requests.Response) -> None:
    lowered = response.text.lower()
    for marker in ("fatal error", "parse error", "uncaught exception", "<b>warning</b>", "<b>notice</b>"):
        expect(marker not in lowered, f"PHP error marker '{marker}' found in {response.url}")


def stock(db: Database, equipment_id: int) -> dict[str, int]:
    row = db.one(
        "SELECT total_quantity, available_quantity, borrowed_quantity, damaged_quantity, "
        "under_repair_quantity, lost_quantity, condition_status, record_status "
        "FROM equipment WHERE id = %s",
        (equipment_id,),
    )
    expect(row is not None, f"Equipment {equipment_id} not found")
    return {k: (int(v) if k.endswith("quantity") else str(v)) for k, v in row.items()}


def invariant(stock_row: dict) -> None:
    total = stock_row["total_quantity"]
    parts = (
        stock_row["available_quantity"]
        + stock_row["borrowed_quantity"]
        + stock_row["damaged_quantity"]
        + stock_row["under_repair_quantity"]
        + stock_row["lost_quantity"]
    )
    expect(total == parts, f"Quantity invariant broken: {stock_row}")


def main() -> int:
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass

    parser = argparse.ArgumentParser(description="Code Blocks acceptance and manual-case regression suite")
    parser.add_argument("--base-url", default=os.environ.get("CODEBLOCKS_BASE_URL", "http://127.0.0.1:8080"))
    parser.add_argument("--db-host", default=os.environ.get("DB_HOST", "127.0.0.1"))
    parser.add_argument("--db-port", type=int, default=int(os.environ.get("DB_PORT", "3306")))
    parser.add_argument("--db-name", default=os.environ.get("DB_NAME", "codeblocks_lab_inventory"))
    parser.add_argument("--db-user", default=os.environ.get("DB_USER", "root"))
    parser.add_argument("--db-password", default=os.environ.get("DB_PASS", ""))
    parser.add_argument("--reset-db", action="store_true", help="Re-seed demo data before the run (destructive)")
    args = parser.parse_args()

    if args.reset_db:
        print("Resetting demo database (destructive)...")
        env = os.environ.copy()
        env["DB_PORT"] = str(args.db_port)
        env["DB_HOST"] = args.db_host
        env["DB_NAME"] = args.db_name
        env["DB_USER"] = args.db_user
        env["DB_PASS"] = args.db_password
        completed = subprocess.run(
            ["php", "database/setup.php", "--seed", "--force"],
            cwd=PROJECT_ROOT,
            env=env,
        )
        if completed.returncode != 0:
            print("Database reset failed; tests were not started.", file=sys.stderr)
            return 2

    run_dir = HERE / "evidence" / dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    run_dir.mkdir(parents=True, exist_ok=True)
    runner = Runner(run_dir / "execution.log")
    runner.log("Code Blocks - Acceptance and Manual Test Regression")
    runner.log(f"Base URL : {args.base_url}")
    runner.log(f"Database : {args.db_host}:{args.db_port}/{args.db_name}")
    runner.log(f"Evidence : {run_dir}")
    runner.log(f"Started  : {runner.started.isoformat(timespec='seconds')}")

    app = App(args.base_url)
    db = Database(args.db_host, args.db_port, args.db_name, args.db_user, args.db_password)
    # Establish the long-lived role sessions before the cases run. Separate
    # sessions keep privilege checks independent from the guest login case.
    app.login(app.admin, ADMIN)
    app.login(app.instructor, INSTRUCTOR)
    ctx: dict = {}
    run_id = dt.datetime.now().strftime("%H%M%S")
    today = dt.date.today()
    tomorrow = (today + dt.timedelta(days=1)).isoformat()
    in_three_days = (today + dt.timedelta(days=3)).isoformat()
    in_twenty_days = (today + dt.timedelta(days=20)).isoformat()

    # ------------------------------------------------------------------ security
    def mtc_01() -> str:
        response = app.get(app.guest, "/login")
        headers = {k.lower(): v for k, v in response.headers.items()}
        expect(headers.get("x-content-type-options") == "nosniff", "Missing nosniff header")
        expect(headers.get("x-frame-options") == "DENY", "Missing frame deny header")
        expect("default-src 'self'" in headers.get("content-security-policy", ""), "Missing CSP")
        expect("geolocation=()" in headers.get("permissions-policy", ""), "Missing permissions policy")
        expect(headers.get("referrer-policy") == "strict-origin-when-cross-origin", "Missing referrer policy")
        return "Login response carried CSP, nosniff, DENY, referrer and permissions headers."

    def mtc_02() -> str:
        app.get(app.guest, "/login")  # establish a session cookie
        before = app.guest.cookies.get("codeblocks_session")
        expect(before is not None, "Session cookie not issued on login page")
        response = app.login(app.guest, ADMIN)
        after = app.guest.cookies.get("codeblocks_session")
        expect(after and before != after, "Session identifier did not rotate after login")
        cookie = next((c for c in app.guest.cookies if c.name == "codeblocks_session"), None)
        expect(cookie is not None, "Session cookie missing after login")
        rest = {k.lower(): v for k, v in getattr(cookie, "_rest", {}).items()}
        expect("httponly" in rest, "Session cookie is not HttpOnly")
        expect(str(rest.get("samesite", "")).lower() == "lax", "Session cookie is not SameSite=Lax")
        expect("dashboard" in response.url, "Valid login did not reach the dashboard")
        return "Valid admin login rotated the session ID; cookie is HttpOnly and SameSite=Lax."

    def mtc_03() -> str:
        session = App._session()
        response = app.login(session, ("admin@codeblocks.test", "Definitely-Wrong-123"), expect_success=False)
        contains(response, "Invalid credentials")
        expect("/login" in response.url, "Invalid login left the login page")
        return "Wrong password was rejected and the browser stayed on /login."

    def mtc_04() -> str:
        session = App._session()
        response = app.login(session, INACTIVE, expect_success=False)
        contains(response, "Invalid credentials or inactive account")
        return "Inactive demo account was refused with the generic credential message."

    def mtc_05() -> str:
        session = App._session()
        response = app.get(session, "/dashboard", allow_redirects=False)
        expect(response.status_code == 302, f"Guest dashboard returned {response.status_code}")
        expect("/login" in response.headers.get("Location", ""), "Guest was not redirected to login")
        root = app.get(session, "/", allow_redirects=False)
        expect(root.status_code == 302 and "/login" in root.headers.get("Location", ""), "Guest root did not redirect to login")
        return "Guests were redirected from /dashboard and / to /login."

    def mtc_06() -> str:
        app.login(app.student, STUDENT)
        failures = []
        for path in ("/reports", "/users", "/audit", "/returns/create", "/equipment/create", "/approvals"):
            response = app.get(app.student, path, allow_redirects=False)
            if response.status_code != 403:
                failures.append(f"{path}={response.status_code}")
        expect(not failures, "Student RBAC failures: " + ", ".join(failures))
        other_request = db.one(
            "SELECT id FROM borrow_requests WHERE user_id <> 4 ORDER BY id LIMIT 1"
        )
        expect(other_request is not None, "No other borrower's request found for the ownership check")
        response = app.get(app.student, f"/borrow/{int(other_request['id'])}")
        expect(response.status_code == 403, "Student could open another borrower's request")
        return "Student received 403 on all six admin routes and on another borrower's request."

    def mtc_07() -> str:
        response = app.student.post(app.url("/notifications/read-all"), data={}, timeout=25, allow_redirects=False)
        expect(response.status_code == 419, f"Missing CSRF token returned {response.status_code}")
        token = app.csrf(app.student)
        response = app.student.post(
            app.url("/notifications/read-all"),
            data={"_token": "invalid-token"},
            timeout=25,
            allow_redirects=False,
        )
        expect(response.status_code == 419, f"Invalid CSRF token returned {response.status_code}")
        return "Both a missing and an invalid CSRF token were rejected with HTTP 419."

    def mtc_08() -> str:
        response = app.get(app.guest, "/this-route-does-not-exist", allow_redirects=False)
        expect(response.status_code == 404, f"Unknown route returned {response.status_code}")
        response = app.post(app.admin, "/dashboard", follow=False)
        expect(response.status_code == 405, f"POST to GET-only route returned {response.status_code}")
        return "Unknown route returned 404 and a wrong method returned 405."

    # ---------------------------------------------------------------- equipment
    def mtc_09() -> str:
        response = app.post(
            app.admin,
            "/equipment",
            {
                "equipment_code": f"QAEQ{run_id}",
                "name": "QA Test Oscilloscope",
                "category_id": "2",
                "description": "Acceptance suite equipment record.",
                "total_quantity": "3",
                "storage_location": "QA Lab - Shelf 1",
                "barcode": f"QA-BAR-{run_id}",
            },
        )
        contains(response, "Equipment record created")
        match = re.search(r"/equipment/(\d+)$", response.url)
        expect(match is not None, f"Could not parse equipment ID from {response.url}")
        eq_id = int(match.group(1))
        ctx["qa_equipment_id"] = eq_id
        row = stock(db, eq_id)
        expect(row["total_quantity"] == 3 and row["available_quantity"] == 3, f"Unexpected new stock: {row}")
        expect(row["condition_status"] == "good" and row["record_status"] == "active", f"Unexpected status: {row}")
        expect(db.scalar("SELECT COUNT(*) FROM audit_logs WHERE action='equipment.created' AND entity_id=%s", (eq_id,)) == 1, "Missing equipment.created audit")
        return f"Created equipment #{eq_id} with 3 available units and an audit entry."

    def mtc_10() -> str:
        before = db.scalar("SELECT COUNT(*) FROM equipment")
        response = app.post(
            app.admin,
            "/equipment",
            {
                "equipment_code": f"QAEQ{run_id}",
                "name": "Duplicate Code Equipment",
                "category_id": "2",
                "description": "Should be rejected.",
                "total_quantity": "1",
                "storage_location": "QA Lab",
                "barcode": "",
            },
        )
        contains(response, "already in use")
        after = db.scalar("SELECT COUNT(*) FROM equipment")
        expect(before == after, "Duplicate equipment code created a row")
        return "Duplicate equipment code was rejected and no row was created."

    def mtc_11() -> str:
        eq_id = ctx["qa_equipment_id"]
        response = app.post(
            app.admin,
            f"/equipment/{eq_id}",
            {
                "equipment_code": f"QAEQ{run_id}",
                "name": "QA Test Oscilloscope (edited)",
                "category_id": "2",
                "description": "Updated by acceptance suite.",
                "total_quantity": "4",
                "storage_location": "QA Lab - Shelf 2",
                "barcode": f"QA-BAR-{run_id}",
                "record_status": "active",
            },
        )
        contains(response, "Equipment record updated")
        row = stock(db, eq_id)
        expect(row["total_quantity"] == 4 and row["available_quantity"] == 4, f"Total update failed: {row}")
        invariant(row)
        response = app.post(
            app.admin,
            "/equipment/1",
            {
                "equipment_code": "BIO-001",
                "name": "Binocular Microscope",
                "category_id": "1",
                "description": "LED-illuminated binocular microscope with 40x-1000x magnification.",
                "total_quantity": "1",
                "storage_location": "Science Lab A - Cabinet 3",
                "barcode": "CB-BIO-001",
                "record_status": "active",
            },
        )
        contains(response, "Total cannot be lower than")
        expect(stock(db, 1)["total_quantity"] == 10, "Rejected total reduction still changed the record")
        return "Edit increased the total to 4; reducing below committed units was rejected."

    def mtc_12() -> str:
        eq_id = ctx["qa_equipment_id"]
        history_before = db.scalar("SELECT COUNT(*) FROM equipment_condition_history WHERE equipment_id=%s", (eq_id,))
        response = app.post(
            app.admin,
            f"/equipment/{eq_id}/condition",
            {"from_bucket": "available", "to_bucket": "damaged", "quantity": "1", "notes": "QA damage inspection"},
        )
        contains(response, "Condition updated")
        row = stock(db, eq_id)
        expect(row["available_quantity"] == 3 and row["damaged_quantity"] == 1, f"Bucket move failed: {row}")
        invariant(row)
        response = app.post(
            app.admin,
            f"/equipment/{eq_id}/condition",
            {"from_bucket": "damaged", "to_bucket": "available", "quantity": "1", "notes": "QA repair complete"},
        )
        contains(response, "Condition updated")
        row = stock(db, eq_id)
        expect(row["available_quantity"] == 4 and row["damaged_quantity"] == 0, f"Bucket restore failed: {row}")
        history_after = db.scalar("SELECT COUNT(*) FROM equipment_condition_history WHERE equipment_id=%s", (eq_id,))
        expect(history_after == history_before + 2, "Condition history did not record both moves")
        return "Units moved available->damaged->available with no change to the total; history recorded both moves."

    def mtc_13() -> str:
        response = app.post(app.admin, "/equipment/1/archive")
        contains(response, "Cannot archive equipment while units are still borrowed")
        expect(stock(db, 1)["record_status"] == "active", "Borrowed equipment was archived")
        return "Archiving equipment with 2 borrowed units was blocked and the record stayed active."

    def mtc_14() -> str:
        eq_id = ctx["qa_equipment_id"]
        response = app.post(app.admin, f"/equipment/{eq_id}/archive")
        contains(response, "Equipment archived")
        expect(stock(db, eq_id)["record_status"] == "archived", "Archive did not persist")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": str(eq_id),
                "quantity": "1",
                "purpose": "Attempt to borrow an archived item for testing.",
                "expected_return_date": in_three_days,
            },
        )
        contains(response, "archived and cannot be borrowed")
        response = app.post(app.admin, f"/equipment/{eq_id}/restore")
        contains(response, "Equipment restored")
        expect(stock(db, eq_id)["record_status"] == "active", "Restore did not persist")
        return "Archive and restore worked; a direct request for the archived item was rejected."

    # ---------------------------------------------------------------- borrowing
    def create_request(session, equipment_id: int, quantity: int, purpose: str, date_value: str) -> tuple[int, str]:
        response = app.post(
            session,
            "/borrow",
            {
                "equipment_id": str(equipment_id),
                "quantity": str(quantity),
                "purpose": purpose,
                "expected_return_date": date_value,
            },
        )
        match = re.search(r"/borrow/(\d+)$", response.url)
        expect(match is not None, f"Request was not created; final URL {response.url}: {response.text[:300]}")
        request_id = int(match.group(1))
        code = re.search(r"REQ-\d{4}-\d+", response.text)
        expect(code is not None, "Request code not rendered")
        return request_id, code.group(0)

    def mtc_15() -> str:
        eq_id = 15
        before = stock(db, eq_id)
        request_id, code = create_request(
            app.student,
            eq_id,
            1,
            "Acceptance test: verify pending requests do not reserve stock.",
            in_three_days,
        )
        ctx["request_a"] = request_id
        ctx["request_a_code"] = code
        contains(app.get(app.student, f"/borrow/{request_id}"), "Pending laboratory review")
        row = db.one("SELECT status, user_id FROM borrow_requests WHERE id=%s", (request_id,))
        expect(row["status"] == "pending", f"New request status {row['status']}")
        expect(stock(db, eq_id) == before, "Pending request changed stock")
        expect(db.scalar("SELECT COUNT(*) FROM notifications WHERE user_id=1 AND type='borrow_request' AND message LIKE %s", (f"%{code}%",)) >= 1, "Admin notification missing")
        return f"Request {code} was created as pending without reserving stock; admin was notified."

    def mtc_16() -> str:
        before = db.scalar("SELECT COUNT(*) FROM borrow_requests")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": "16",
                "quantity": "1",
                "purpose": "Boundary test for a past return date.",
                "expected_return_date": (today - dt.timedelta(days=1)).isoformat(),
            },
        )
        contains(response, "cannot be in the past")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_requests") == before, "Past-date request was created")
        return "A return date before today was rejected and no request was created."

    def mtc_17() -> str:
        before = db.scalar("SELECT COUNT(*) FROM borrow_requests")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": "16",
                "quantity": "1",
                "purpose": "Boundary test for the maximum borrowing window.",
                "expected_return_date": in_twenty_days,
            },
        )
        contains(response, "cannot be more than 14 days")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_requests") == before, "Out-of-window request was created")
        return "A date beyond the 14-day borrowing window was rejected."

    def mtc_18() -> str:
        before = db.scalar("SELECT COUNT(*) FROM borrow_requests")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": "15",
                "quantity": "99",
                "purpose": "Boundary test for quantity above available stock.",
                "expected_return_date": in_three_days,
            },
        )
        contains(response, "Quantity must not exceed 20")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_requests") == before, "Over-quantity request was created")
        return "A quantity above the per-request limit (and available stock) was rejected."

    def mtc_19() -> str:
        before = db.scalar("SELECT COUNT(*) FROM borrow_requests")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": "16",
                "quantity": "1",
                "purpose": "short",
                "expected_return_date": in_three_days,
            },
        )
        contains(response, "Purpose must be at least 10 characters")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_requests") == before, "Short-purpose request was created")
        return "A purpose shorter than 10 characters was rejected."

    def mtc_20() -> str:
        first_id, first_code = create_request(
            app.student,
            14,
            1,
            "Duplicate request guard acceptance test (first submission).",
            in_three_days,
        )
        before = db.scalar("SELECT COUNT(*) FROM borrow_requests")
        response = app.post(
            app.student,
            "/borrow",
            {
                "equipment_id": "14",
                "quantity": "1",
                "purpose": "Duplicate request guard acceptance test (second submission).",
                "expected_return_date": in_three_days,
            },
        )
        contains(response, "similar request was just submitted")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_requests") == before, "Duplicate request was created")
        app.post(app.student, f"/borrow/{first_id}/cancel")
        return f"A second request for the same equipment right after {first_code} was rejected."

    def mtc_21() -> str:
        request_id = ctx["request_a"]
        before = stock(db, 15)
        response = app.post(app.admin, f"/borrow/{request_id}/approve")
        contains(response, "Request approved", "Checkout recorded")
        request_row = db.one("SELECT status FROM borrow_requests WHERE id=%s", (request_id,))
        expect(request_row["status"] == "approved", f"Request status {request_row['status']}")
        tx = db.one("SELECT * FROM borrow_transactions WHERE request_id=%s", (request_id,))
        expect(tx is not None, "Checkout transaction not created")
        ctx["tx_a"] = int(tx["id"])
        ctx["tx_a_code"] = str(tx["transaction_code"])
        after = stock(db, 15)
        expect(
            after["available_quantity"] == before["available_quantity"] - 1
            and after["borrowed_quantity"] == before["borrowed_quantity"] + 1,
            f"Approval stock movement wrong: before={before}, after={after}",
        )
        invariant(after)
        expect(db.scalar("SELECT COUNT(*) FROM audit_logs WHERE action='borrow.approved' AND entity_id=%s", (request_id,)) == 1, "Approval audit missing")
        return f"Request approved, {ctx['tx_a_code']} created, available-1 and borrowed+1."

    def mtc_22() -> str:
        request_id = ctx["request_a"]
        tx_count = db.scalar("SELECT COUNT(*) FROM borrow_transactions WHERE request_id=%s", (request_id,))
        response = app.post(app.admin, f"/borrow/{request_id}/approve")
        contains(response, "already been processed")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_transactions WHERE request_id=%s", (request_id,)) == tx_count, "Duplicate approval created a transaction")
        return "A second approval was rejected and no duplicate transaction was created."

    def mtc_23() -> str:
        request_id = ctx["request_a"]
        response = app.post(app.student, f"/borrow/{request_id}/approve", follow=False)
        expect(response.status_code == 403, f"Student approval returned {response.status_code}")
        return "A student approval POST was blocked with HTTP 403."

    def mtc_24() -> str:
        request_id, code = create_request(
            app.student,
            17,
            1,
            "Acceptance test: rejection reason and notification.",
            in_three_days,
        )
        ctx["request_rejected"] = request_id
        response = app.post(
            app.admin,
            f"/borrow/{request_id}/reject",
            {"rejection_reason": "Equipment is reserved for a scheduled laboratory class."},
        )
        contains(response, "Request rejected", "Equipment is reserved")
        row = db.one("SELECT status, rejection_reason FROM borrow_requests WHERE id=%s", (request_id,))
        expect(row["status"] == "rejected", f"Status {row['status']}")
        expect("reserved" in row["rejection_reason"], "Reason not stored")
        expect(
            db.scalar(
                "SELECT COUNT(*) FROM notifications WHERE user_id=4 AND type='rejection' AND message LIKE %s",
                (f"%{code}%",),
            )
            >= 1,
            "Rejection notification missing",
        )
        return f"Rejected {code} with a reason and delivered an in-app notification."

    def mtc_25() -> str:
        request_id, code = create_request(
            app.student,
            18,
            1,
            "Acceptance test: owner cancels a pending request.",
            in_three_days,
        )
        response = app.post(app.student, f"/borrow/{request_id}/cancel")
        contains(response, "cancelled")
        expect(db.one("SELECT status FROM borrow_requests WHERE id=%s", (request_id,))["status"] == "cancelled", "Cancel did not persist")
        return f"Owner cancelled pending request {code}."

    def mtc_26() -> str:
        tx_id = ctx["tx_a"]
        before = stock(db, 15)
        returns_before = db.scalar("SELECT COUNT(*) FROM return_records")
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(tx_id),
                "quantity": "1",
                "return_date": today.isoformat(),
                "condition": "",
                "notes": "Missing condition must write nothing.",
            },
            follow=False,
        )
        expect(response.status_code == 422, f"Missing condition returned {response.status_code}")
        contains(response, "Condition is required", "condition-error")
        expect(stock(db, 15) == before, "Invalid return changed stock")
        expect(db.scalar("SELECT COUNT(*) FROM return_records") == returns_before, "Invalid return wrote a return record")
        return "Missing condition returned HTTP 422 and wrote no stock, return, or history rows."

    def mtc_27() -> str:
        before = stock(db, 15)
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(ctx["tx_a"]),
                "quantity": "1",
                "return_date": tomorrow,
                "condition": "good",
                "notes": "Future return date must be rejected.",
            },
            follow=False,
        )
        expect(response.status_code == 422, f"Future return date returned {response.status_code}")
        contains(response, "cannot be later than today")
        expect(stock(db, 15) == before, "Future-dated return changed stock")
        return "A future return date was rejected with HTTP 422 and no stock movement."

    def mtc_28() -> str:
        before = stock(db, 15)
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(ctx["tx_a"]),
                "quantity": "99",
                "return_date": today.isoformat(),
                "condition": "good",
                "notes": "Over-return must be rejected.",
            },
            follow=False,
        )
        expect(response.status_code == 422, f"Over-return returned {response.status_code}")
        contains(response, "exceeds the outstanding quantity")
        expect(stock(db, 15) == before, "Over-return changed stock")
        return "A return quantity above the outstanding quantity was rejected with HTTP 422."

    def mtc_29() -> str:
        tx_id = ctx["tx_a"]
        before = stock(db, 15)
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(tx_id),
                "quantity": "1",
                "return_date": today.isoformat(),
                "condition": "damaged",
                "notes": "Acceptance inspection: cracked lens mount.",
            },
        )
        contains(response, "Return recorded")
        record = db.one("SELECT * FROM return_records WHERE transaction_id=%s ORDER BY id DESC LIMIT 1", (tx_id,))
        expect(record is not None and record["condition"] == "damaged", "Damaged return record not written")
        ctx["return_code"] = str(record["return_code"])
        after = stock(db, 15)
        expect(
            after["borrowed_quantity"] == before["borrowed_quantity"] - 1
            and after["damaged_quantity"] == before["damaged_quantity"] + 1
            and after["available_quantity"] == before["available_quantity"],
            f"Damaged return movement wrong: before={before}, after={after}",
        )
        invariant(after)
        tx = db.one("SELECT status, quantity_returned FROM borrow_transactions WHERE id=%s", (tx_id,))
        expect(tx["status"] == "returned" and int(tx["quantity_returned"]) == 1, f"Transaction not closed: {tx}")
        expect(db.one("SELECT status FROM borrow_requests WHERE id=%s", (ctx["request_a"],))["status"] == "completed", "Request not completed")
        expect(db.scalar("SELECT COUNT(*) FROM equipment_condition_history WHERE return_record_id=%s", (record["id"],)) == 1, "Condition history missing")
        expect(db.scalar("SELECT COUNT(*) FROM audit_logs WHERE action='return.processed' AND entity_id=%s", (record["id"],)) == 1, "Return audit missing")
        return f"Recorded {ctx['return_code']} as damaged; borrowed-1, damaged+1, transaction and request closed atomically."

    def mtc_30() -> str:
        request_id, code = create_request(
            app.student,
            16,
            2,
            "Acceptance test: partial return then final return.",
            in_three_days,
        )
        app.post(app.admin, f"/borrow/{request_id}/approve")
        tx = db.one("SELECT * FROM borrow_transactions WHERE request_id=%s", (request_id,))
        expect(tx is not None, "Partial-return transaction not created")
        tx_id = int(tx["id"])
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(tx_id),
                "quantity": "1",
                "return_date": today.isoformat(),
                "condition": "good",
                "notes": "First of two units returned in good condition.",
            },
        )
        contains(response, "Return recorded")
        tx = db.one("SELECT status, quantity_returned FROM borrow_transactions WHERE id=%s", (tx_id,))
        expect(tx["status"] == "partially_returned" and int(tx["quantity_returned"]) == 1, f"Partial return state: {tx}")
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(tx_id),
                "quantity": "1",
                "return_date": today.isoformat(),
                "condition": "needs_maintenance",
                "notes": "Second unit needs calibration.",
            },
        )
        contains(response, "Return recorded")
        tx = db.one("SELECT status, quantity_returned FROM borrow_transactions WHERE id=%s", (tx_id,))
        expect(tx["status"] == "returned" and int(tx["quantity_returned"]) == 2, f"Final return state: {tx}")
        row = stock(db, 16)
        invariant(row)
        expect(row["under_repair_quantity"] >= 1, f"Repair bucket not updated: {row}")
        ctx["tx_partial"] = tx_id
        return f"Request {code} supported a 1+1 partial return and closed after the final unit."

    def mtc_31() -> str:
        tx_id = ctx["tx_partial"]
        returns_before = db.scalar("SELECT COUNT(*) FROM return_records WHERE transaction_id=%s", (tx_id,))
        response = app.post(
            app.admin,
            "/returns",
            {
                "transaction_id": str(tx_id),
                "quantity": "1",
                "return_date": today.isoformat(),
                "condition": "good",
                "notes": "Duplicate return must be rejected.",
            },
        )
        contains(response, "already been fully returned")
        expect(
            db.scalar("SELECT COUNT(*) FROM return_records WHERE transaction_id=%s", (tx_id,)) == returns_before,
            "Duplicate return wrote a record",
        )
        return "A return against a fully returned transaction was rejected."

    def mtc_32() -> str:
        broken = db.all(
            "SELECT id, equipment_code FROM equipment WHERE total_quantity <> "
            "available_quantity + borrowed_quantity + damaged_quantity + under_repair_quantity + lost_quantity"
        )
        expect(not broken, f"Invariant violations: {broken}")
        rows = db.all("SELECT id, total_quantity, available_quantity, borrowed_quantity, damaged_quantity, under_repair_quantity, lost_quantity FROM equipment")
        for row in rows:
            invariant({k: int(v) if k.endswith("quantity") else v for k, v in row.items()})
        return f"All {len(rows)} equipment rows satisfied total = available + borrowed + damaged + repair + lost."

    def mtc_33() -> str:
        eq_id = ctx["qa_equipment_id"]
        before = stock(db, eq_id)
        conn = db.connect(autocommit=False)
        rejected = False
        message = ""
        try:
            with conn.cursor() as cur:
                cur.execute("UPDATE equipment SET total_quantity = total_quantity + 1 WHERE id = %s", (eq_id,))
                conn.commit()
        except pymysql.MySQLError as exc:
            rejected = True
            message = str(exc)
            conn.rollback()
        finally:
            conn.close()
        expect(rejected, "Database CHECK constraint accepted an inconsistent total")
        expect(stock(db, eq_id) == before, "Rejected direct update altered the row")
        return f"The database CHECK rejected an inconsistent direct update ({message.splitlines()[0][:90]})."

    # ------------------------------------------------------------------ reports
    def mtc_34() -> str:
        checks = {
            "inventory": "QA Test Oscilloscope (edited)",
            "borrowing": ctx["request_a_code"],
            "returns": ctx["return_code"],
            "pending": "REQ-2026-0010",
            "overdue": "TX-2026-0003",
            "condition": "Thermal Imaging Camera",
            "borrowers": "Ana Reyes",
        }
        for report_type, needle in checks.items():
            response = app.get(app.admin, f"/reports?type={report_type}")
            expect(response.status_code == 200, f"Report {report_type} status {response.status_code}")
            contains(response, needle)
            no_php_errors(response)
        summary = app.get(app.admin, "/reports?type=inventory")
        contains(summary, "Inventory")
        return "All seven live report views rendered expected database-backed rows."

    def mtc_35() -> str:
        response = app.get(app.admin, "/reports/export?type=returns")
        expect(response.status_code == 200, f"CSV export status {response.status_code}")
        expect("text/csv" in response.headers.get("Content-Type", "").lower(), "CSV content type missing")
        expect("attachment" in response.headers.get("Content-Disposition", "").lower(), "CSV is not an attachment")
        raw = response.content
        expect(raw.startswith(b"\xef\xbb\xbf"), "CSV lacks UTF-8 BOM")
        rows = list(csv.reader(io.StringIO(raw.decode("utf-8-sig"))))
        expect(rows and rows[0][0] == "Return Code", f"Unexpected CSV header {rows[:1]}")
        expect(any(row and row[0] == ctx["return_code"] for row in rows[1:]), "New return missing from CSV")
        return f"Returns CSV exported with UTF-8 BOM, header, and {ctx['return_code']}."

    # -------------------------------------------------------------------- users
    def mtc_36() -> str:
        email = f"qa.student.{run_id}@codeblocks.test"
        response = app.post(
            app.admin,
            "/users",
            {
                "first_name": "QA",
                "last_name": "Student",
                "school_id": f"QA-{run_id}",
                "email": email,
                "role": "student",
                "department": "QA Department",
                "phone": "0917-000-9999",
                "status": "active",
                "password": "QaUser@12345",
                "password_confirm": "QaUser@12345",
            },
        )
        contains(response, "User account created")
        row = db.one("SELECT * FROM users WHERE email=%s", (email,))
        expect(row is not None, "Created user not found")
        ctx["qa_user_id"] = int(row["id"])
        expect(row["role"] == "student" and row["status"] == "active", "New user role/status wrong")
        expect(row["password_hash"].startswith("$2y$"), "Password was not hashed with bcrypt")
        qa_session = App._session()
        app.login(qa_session, (email, "QaUser@12345"))
        return f"Created user #{row['id']} with a bcrypt hash; the new account signed in successfully."

    def mtc_37() -> str:
        before = db.scalar("SELECT COUNT(*) FROM users")
        response = app.post(
            app.admin,
            "/users",
            {
                "first_name": "QA",
                "last_name": "Duplicate",
                "school_id": f"QA-DUP-{run_id}",
                "email": f"qa.student.{run_id}@codeblocks.test",
                "role": "student",
                "department": "",
                "phone": "",
                "status": "active",
                "password": "QaUser@12345",
                "password_confirm": "QaUser@12345",
            },
        )
        contains(response, "already registered")
        expect(db.scalar("SELECT COUNT(*) FROM users") == before, "Duplicate user was created")
        return "A duplicate email was rejected and no user row was created."

    def mtc_38() -> str:
        response = app.post(
            app.admin,
            "/users/1",
            {
                "first_name": "Elena",
                "last_name": "Marquez",
                "school_id": "ADMIN-001",
                "email": "admin@codeblocks.test",
                "role": "student",
                "department": "Laboratory Management Office",
                "phone": "0917-000-1001",
                "status": "active",
            },
        )
        contains(response, "at least one active administrator")
        row = db.one("SELECT role, status FROM users WHERE id=1")
        expect(row["role"] == "admin" and row["status"] == "active", "Self-demotion changed the admin account")
        return "The sole administrator could not demote or deactivate their own account."

    def mtc_39() -> str:
        uid = ctx["qa_user_id"]
        email = f"qa.student.{run_id}@codeblocks.test"
        response = app.post(
            app.admin,
            f"/users/{uid}",
            {
                "first_name": "QA",
                "last_name": "Student",
                "school_id": f"QA-{run_id}",
                "email": email,
                "role": "student",
                "department": "QA Department",
                "phone": "0917-000-9999",
                "status": "inactive",
            },
        )
        contains(response, "User account updated")
        session = App._session()
        app.login(session, (email, "QaUser@12345"), expect_success=False)
        response = app.post(
            app.admin,
            f"/users/{uid}",
            {
                "first_name": "QA",
                "last_name": "Student",
                "school_id": f"QA-{run_id}",
                "email": email,
                "role": "student",
                "department": "QA Department",
                "phone": "0917-000-9999",
                "status": "active",
                "password": "Reset@12345",
                "password_confirm": "Reset@12345",
            },
        )
        contains(response, "User account updated", "Password was reset")
        session = App._session()
        app.login(session, (email, "Reset@12345"))
        ctx["qa_user_email"] = email
        return "Deactivation blocked sign-in; reactivation plus password reset restored access with the new password."

    def mtc_40() -> str:
        response = app.post(
            app.student,
            "/profile",
            {
                "first_name": "Ana",
                "last_name": "Reyes",
                "email": "ana.reyes@codeblocks.test",
                "department": "BS Computer Science - QA",
                "phone": "0917-000-4009",
            },
        )
        contains(response, "profile was updated")
        row = db.one("SELECT department, phone FROM users WHERE id=4")
        expect(row["department"] == "BS Computer Science - QA", f"Profile not saved: {row}")
        expect(db.scalar("SELECT COUNT(*) FROM audit_logs WHERE action='profile.updated' AND entity_id=4") >= 1, "Profile audit missing")
        return "Student profile changes were persisted and audited."

    def mtc_41() -> str:
        response = app.post(
            app.student,
            "/profile/password",
            {
                "current_password": "wrong-password",
                "password": "NewStudent@12345",
                "password_confirm": "NewStudent@12345",
            },
        )
        contains(response, "Current password is incorrect")
        response = app.post(
            app.student,
            "/profile/password",
            {
                "current_password": "Student@12345",
                "password": "NewStudent@12345",
                "password_confirm": "NewStudent@12345",
            },
        )
        contains(response, "Password changed")
        session = App._session()
        app.login(session, ("ana.reyes@codeblocks.test", "NewStudent@12345"))
        return "Wrong current password was rejected; the correct change allowed a fresh sign-in."

    # ------------------------------------------------- notifications and audit
    def mtc_42() -> str:
        response = app.get(app.student, "/notifications")
        contains(response, "Borrowing request approved", "Return recorded", ctx["return_code"])
        unread_before = int(db.scalar("SELECT COUNT(*) FROM notifications WHERE user_id=4 AND is_read=0"))
        response = app.post(app.student, "/notifications/read-all")
        contains(response, "Notifications")
        unread_after = int(db.scalar("SELECT COUNT(*) FROM notifications WHERE user_id=4 AND is_read=0"))
        expect(unread_before > 0 and unread_after == 0, f"Unread count {unread_before}->{unread_after}")
        latest = db.one("SELECT id, is_read FROM notifications WHERE user_id=4 ORDER BY id DESC LIMIT 1")
        db_unread = int(db.scalar("SELECT COUNT(*) FROM notifications WHERE user_id=4 AND is_read=0"))
        expect(db_unread == 0, "Notifications were not all marked read")
        return "Approval and return notifications were visible; read-all cleared the unread counter."

    def mtc_43() -> str:
        for action in ("borrow.approved", "return.processed", "equipment.created", "user.created", "report.exported"):
            response = app.get(app.admin, f"/audit?action={action}")
            contains(response, action)
            no_php_errors(response)
        total = db.scalar("SELECT COUNT(*) FROM audit_logs")
        expect(int(total) > 9, "Audit trail did not grow during the run")
        return f"Audit filters displayed all key actions; the table now holds {total} entries."

    def mtc_44() -> str:
        css = app.get(app.guest, "/assets/css/app.css")
        expect(css.status_code == 200, "Stylesheet not served")
        for token in ("@media (max-width: 760px)", "prefers-reduced-motion", "@keyframes"):
            expect(token in css.text, f"CSS missing '{token}'")
        js = app.get(app.guest, "/assets/js/app.js")
        expect(js.status_code == 200 and "data-confirm" in js.text, "Interaction script missing")
        login = app.get(App._session(), "/login")
        for token in ('data-testid="login-input"', 'data-testid="password-input"', 'data-testid="login-submit"'):
            contains(login, token)
        logo = app.get(app.guest, "/assets/images/code-blocks-logo.svg")
        expect(logo.status_code == 200 and "<svg" in logo.text, "Original SVG logo not served")
        return "Responsive/reduced-motion CSS, confirm-dialog JS, stable test IDs, and the SVG logo were served."

    def mtc_45() -> str:
        for path in ("/approvals", "/users", "/audit", "/reports", "/equipment/create"):
            response = app.get(app.instructor, path, allow_redirects=False)
            expect(response.status_code == 403, f"Instructor {path} returned {response.status_code}")
        allowed = app.get(app.instructor, "/borrow", allow_redirects=False)
        expect(allowed.status_code == 200, f"Instructor /borrow returned {allowed.status_code}")
        return "Instructor role was denied all administrator routes but retained borrowing access."

    def mtc_46() -> str:
        admin_routes = [
            "/dashboard",
            "/equipment",
            "/equipment?q=microscope&category_id=1&availability=available&condition=good&status=active&sort=name",
            f"/equipment/{ctx['qa_equipment_id']}",
            "/equipment/create",
            "/borrow",
            f"/borrow/{ctx['request_a']}",
            "/approvals",
            "/transactions",
            "/returns",
            "/returns/create?transaction=1",
            "/users",
            f"/users/{ctx['qa_user_id']}/edit",
            "/reports",
            "/reports?type=borrowing",
            "/reports?type=returns",
            "/reports?type=pending",
            "/reports?type=overdue",
            "/reports?type=condition",
            "/reports?type=borrowers",
            "/audit",
            "/notifications",
            "/profile",
        ]
        failures = []
        for path in admin_routes:
            response = app.get(app.admin, path)
            if response.status_code != 200:
                failures.append(f"{path}={response.status_code}")
            try:
                no_php_errors(response)
            except CaseFailure as exc:
                failures.append(f"{path}: {exc}")
        expect(not failures, "Admin route failures: " + "; ".join(failures))
        return f"All {len(admin_routes)} administrator GET routes returned HTTP 200 without PHP errors."

    def mtc_47() -> str:
        student_routes = ["/dashboard", "/equipment", "/equipment/15", "/borrow", f"/borrow/{ctx['request_a']}", "/transactions", "/returns", "/notifications", "/profile"]
        failures = []
        for path in student_routes:
            response = app.get(app.student, path)
            if response.status_code != 200:
                failures.append(f"{path}={response.status_code}")
            try:
                no_php_errors(response)
            except CaseFailure as exc:
                failures.append(f"{path}: {exc}")
        expect(not failures, "Student route failures: " + "; ".join(failures))
        return f"All {len(student_routes)} student GET routes returned HTTP 200 without PHP errors."

    def mtc_48() -> str:
        ghost = f"ghost-{run_id}@codeblocks.test"
        session = App._session()
        for attempt in range(1, 6):
            response = app.login(session, (ghost, "WrongPassword1"), expect_success=False)
            expect("Too many failed attempts" not in response.text, f"Account locked before attempt {attempt}")
        response = app.login(session, (ghost, "WrongPassword1"), expect_success=False)
        contains(response, "Too many failed attempts", "wait 15 minutes")
        return "Five failed attempts triggered the 15-minute lockout message on the sixth attempt."

    def mtc_49() -> str:
        # Approval must re-check stock under a lock, not trust request-time availability.
        request_id, code = create_request(
            app.student,
            14,
            1,
            "Acceptance test: stock re-check at approval time.",
            in_three_days,
        )
        row = stock(db, 14)
        if row["available_quantity"] < 1:
            raise CaseFailure(f"No available unit for re-check test: {row}")
        # Equipment 14 still has a borrowed unit, so emptying the available
        # bucket leaves the record in "good" condition. That makes the approval
        # reach the stock re-check instead of failing the condition pre-check.
        response = app.post(
            app.admin,
            "/equipment/14/condition",
            {"from_bucket": "available", "to_bucket": "damaged", "quantity": str(row["available_quantity"]), "notes": "Temporary QA withdrawal"},
        )
        contains(response, "Condition updated")
        before_tx = db.scalar("SELECT COUNT(*) FROM borrow_transactions")
        response = app.post(app.admin, f"/borrow/{request_id}/approve")
        contains(response, "Insufficient stock")
        expect(db.scalar("SELECT COUNT(*) FROM borrow_transactions") == before_tx, "Failed approval created a transaction")
        expect(db.one("SELECT status FROM borrow_requests WHERE id=%s", (request_id,))["status"] == "pending", "Failed approval changed request status")
        response = app.post(
            app.admin,
            "/equipment/14/condition",
            {"from_bucket": "damaged", "to_bucket": "available", "quantity": str(row["available_quantity"]), "notes": "Restore after QA withdrawal"},
        )
        contains(response, "Condition updated")
        app.post(app.admin, f"/borrow/{request_id}/reject", {"rejection_reason": "QA cleanup after stock re-check test."})
        return f"Approval of {code} was refused with 'Insufficient stock' and no partial checkout was written."

    def mtc_50() -> str:
        response = app.post(app.student, "/logout")
        contains(response, "signed out securely")
        response = app.get(app.student, "/dashboard", allow_redirects=False)
        expect(response.status_code == 302 and "/login" in response.headers.get("Location", ""), "Session still active after logout")
        return "Logout destroyed the session; the dashboard redirected to login afterwards."

    cases = [
        ("MTC-01", "Security response headers on the login page", mtc_01, "Security configuration review"),
        ("MTC-02", "Valid login, session rotation, and cookie flags", mtc_02, "State transition"),
        ("MTC-03", "Invalid password is rejected", mtc_03, "Equivalence partitioning"),
        ("MTC-04", "Inactive account cannot sign in", mtc_04, "State transition / EP"),
        ("MTC-05", "Guest routes require authentication", mtc_05, "Access control"),
        ("MTC-06", "Student role authorization and ownership", mtc_06, "Access control matrix"),
        ("MTC-07", "CSRF token required on state changes", mtc_07, "Security negative test"),
        ("MTC-08", "Unknown route and wrong method handling", mtc_08, "Error guessing"),
        ("MTC-09", "Create equipment record", mtc_09, "CRUD happy path"),
        ("MTC-10", "Duplicate equipment code rejected", mtc_10, "Equivalence partitioning"),
        ("MTC-11", "Edit equipment and guard total reduction", mtc_11, "Boundary value analysis"),
        ("MTC-12", "Move units between condition buckets", mtc_12, "State transition"),
        ("MTC-13", "Archive blocked while units are borrowed", mtc_13, "State transition"),
        ("MTC-14", "Archive/restore and archived-item request rejection", mtc_14, "State transition"),
        ("MTC-15", "Submit request without reserving stock", mtc_15, "Happy path"),
        ("MTC-16", "Past return date rejected", mtc_16, "Boundary value analysis"),
        ("MTC-17", "Return date beyond 14-day window rejected", mtc_17, "Boundary value analysis"),
        ("MTC-18", "Quantity above available stock rejected", mtc_18, "Boundary value analysis"),
        ("MTC-19", "Purpose shorter than 10 characters rejected", mtc_19, "Boundary value analysis"),
        ("MTC-20", "Duplicate request guard", mtc_20, "State transition"),
        ("MTC-21", "Approve request and create checkout", mtc_21, "State transition"),
        ("MTC-22", "Duplicate approval rejected", mtc_22, "State transition"),
        ("MTC-23", "Student cannot approve requests", mtc_23, "Access control"),
        ("MTC-24", "Reject with reason and notify borrower", mtc_24, "State transition"),
        ("MTC-25", "Owner cancels a pending request", mtc_25, "State transition"),
        ("MTC-26", "Return requires a condition (422, no writes)", mtc_26, "Decision table"),
        ("MTC-27", "Future return date rejected (422)", mtc_27, "Boundary value analysis"),
        ("MTC-28", "Over-return rejected (422)", mtc_28, "Boundary value analysis"),
        ("MTC-29", "Damaged return moves stock atomically", mtc_29, "State transition"),
        ("MTC-30", "Partial return then final return", mtc_30, "State transition"),
        ("MTC-31", "Duplicate return rejected", mtc_31, "State transition"),
        ("MTC-32", "Global quantity invariant", mtc_32, "Invariant check"),
        ("MTC-33", "Database CHECK rejects inconsistent totals", mtc_33, "Negative database test"),
        ("MTC-34", "All seven report views use live data", mtc_34, "Data validation"),
        ("MTC-35", "Returns CSV export", mtc_35, "File export"),
        ("MTC-36", "Create user and sign in", mtc_36, "CRUD happy path"),
        ("MTC-37", "Duplicate user email rejected", mtc_37, "Equivalence partitioning"),
        ("MTC-38", "Admin cannot demote self", mtc_38, "Access control"),
        ("MTC-39", "Deactivate, reactivate, and reset password", mtc_39, "State transition"),
        ("MTC-40", "Profile update persisted and audited", mtc_40, "CRUD happy path"),
        ("MTC-41", "Password change validation", mtc_41, "Security negative test"),
        ("MTC-42", "Notifications delivered and read", mtc_42, "Notification check"),
        ("MTC-43", "Audit trail covers key actions", mtc_43, "Audit check"),
        ("MTC-44", "Responsive CSS, reduced motion, test IDs, SVG", mtc_44, "UI/static review"),
        ("MTC-45", "Instructor role restrictions", mtc_45, "Access control matrix"),
        ("MTC-46", "Administrator route sweep", mtc_46, "Smoke test"),
        ("MTC-47", "Student route sweep", mtc_47, "Smoke test"),
        ("MTC-48", "Login lockout after five failures", mtc_48, "Boundary value analysis"),
        ("MTC-49", "Stock re-check at approval time", mtc_49, "Concurrency/state test"),
        ("MTC-50", "Logout destroys the session", mtc_50, "State transition"),
    ]

    # Login sessions are established lazily by the cases that need them.
    for case_id, title, fn, technique in cases:
        runner.run(case_id, title, fn, technique)

    # Database snapshot for the evidence package.
    counts = {
        table: int(db.scalar(f"SELECT COUNT(*) FROM {table}"))
        for table in (
            "users",
            "categories",
            "equipment",
            "borrow_requests",
            "borrow_request_items",
            "borrow_transactions",
            "return_records",
            "equipment_condition_history",
            "notifications",
            "audit_logs",
        )
    }
    broken = db.all(
        "SELECT id, equipment_code FROM equipment WHERE total_quantity <> "
        "available_quantity + borrowed_quantity + damaged_quantity + under_repair_quantity + lost_quantity"
    )
    snapshot = {
        "counts": counts,
        "invariant_violations": broken,
        "stock_15": stock(db, 15),
        "stock_16": stock(db, 16),
        "context": {k: v for k, v in ctx.items()},
    }
    (run_dir / "database-snapshot.json").write_text(json.dumps(snapshot, indent=2), encoding="utf-8")
    with (run_dir / "database-verification.txt").open("w", encoding="utf-8") as handle:
        handle.write("Code Blocks - database verification after acceptance suite\n")
        handle.write(f"Timestamp: {dt.datetime.now().isoformat(timespec='seconds')}\n\n")
        for table, count in counts.items():
            handle.write(f"{table:30s} {count}\n")
        handle.write(f"\nQuantity invariant violations: {len(broken)}\n")
        for row in broken:
            handle.write(f"  {row}\n")
        handle.write(f"\nEquipment 15 stock: {json.dumps(snapshot['stock_15'], sort_keys=True)}\n")
        handle.write(f"Equipment 16 stock: {json.dumps(snapshot['stock_16'], sort_keys=True)}\n")

    runner.close()
    summary = json.loads((run_dir / "summary.json").read_text(encoding="utf-8"))
    print(f"Evidence : {run_dir}")
    return 0 if summary["failed"] == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
