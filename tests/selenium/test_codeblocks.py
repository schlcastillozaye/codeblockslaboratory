"""
End-to-end Selenium coverage for the Code Blocks laboratory inventory system.

Critical flow:
  student request -> personnel approval/checkout -> server-side return-condition
  rejection -> valid damaged return -> inventory movement -> student notification.

Run from the project root after the application and MySQL are available:
    python tests/selenium/run_tests.py --base-url http://127.0.0.1:8080

The suite intentionally uses the seeded demo accounts. Re-seed demo data before
recording evidence so request codes and stock assertions are deterministic.
"""

from __future__ import annotations

import csv
import io
import os
import re
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid
from datetime import date, timedelta

from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait

BASE_URL = os.environ.get("CODEBLOCKS_BASE_URL", "http://127.0.0.1:8080").rstrip("/")
EVIDENCE_DIR = os.environ.get(
    "CODEBLOCKS_EVIDENCE_DIR",
    os.path.join(os.path.dirname(__file__), "evidence", "latest"),
)
DOWNLOAD_DIR = os.path.join(EVIDENCE_DIR, "downloads")

ADMIN = ("admin@codeblocks.test", "Admin@12345")
STUDENT = ("ana.reyes@codeblocks.test", "Student@12345")
EQUIPMENT_ID = int(os.environ.get("CODEBLOCKS_EQUIPMENT_ID", "15"))  # Seed: Thermal Imaging Camera
WAIT_SECONDS = 12


class CodeBlocksSeleniumTest(unittest.TestCase):
    """One browser session, independent tests in alphabetical order."""

    driver: webdriver.Chrome
    request_id: int | None = None
    request_code: str | None = None
    transaction_id: int | None = None
    transaction_code: str | None = None
    return_code: str | None = None
    stock_after_approval: dict[str, int] | None = None

    @classmethod
    def setUpClass(cls) -> None:
        os.makedirs(EVIDENCE_DIR, exist_ok=True)
        os.makedirs(DOWNLOAD_DIR, exist_ok=True)

        options = Options()
        if os.environ.get("CODEBLOCKS_HEADLESS", "1") != "0":
            options.add_argument("--headless=new")
        options.add_argument("--window-size=1440,1100")
        options.add_argument("--disable-gpu")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--no-first-run")
        options.add_argument("--disable-extensions")
        options.add_argument("--disable-search-engine-choice-screen")
        options.add_experimental_option(
            "prefs",
            {
                "download.default_directory": DOWNLOAD_DIR,
                "download.prompt_for_download": False,
                "download.directory_upgrade": True,
                "safebrowsing.enabled": True,
            },
        )
        cls.driver = webdriver.Chrome(options=options)
        cls.driver.set_page_load_timeout(30)

    @classmethod
    def tearDownClass(cls) -> None:
        cls.driver.quit()

    # ------------------------------------------------------------------ helpers
    @property
    def wait(self) -> WebDriverWait:
        return WebDriverWait(self.driver, WAIT_SECONDS)

    def go(self, path: str) -> None:
        self.driver.get(BASE_URL + (path if path.startswith("/") else "/" + path))

    def clear_session(self) -> None:
        self.driver.delete_all_cookies()

    def login(self, account: tuple[str, str]) -> None:
        email, password = account
        self.clear_session()
        self.go("/login")
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="login-input"]'))
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="login-input"]').send_keys(email)
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="password-input"]').send_keys(password)
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="login-submit"]').click()
        self.wait.until(lambda d: "/dashboard" in d.current_url)
        self.assertIn("dashboard", self.driver.current_url)

    def click(self, selector: str) -> None:
        element = self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, selector))
        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center', inline: 'center'});", element)
        time.sleep(0.15)
        element.click()

    def visible_text(self, selector: str) -> str:
        element = self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, selector))
        return element.text.strip()

    def wait_for_text(self, selector: str, expected: str) -> str:
        def condition(d):
            try:
                element = d.find_element(By.CSS_SELECTOR, selector)
                return element.text if expected in element.text else False
            except Exception:
                return False

        self.wait.until(condition)
        return self.driver.find_element(By.CSS_SELECTOR, selector).text

    def read_stock(self, equipment_id: int = EQUIPMENT_ID) -> dict[str, int]:
        self.go(f"/equipment/{equipment_id}")
        self.wait.until(lambda d: len(d.find_elements(By.CSS_SELECTOR, ".quantity-tile")) >= 6)
        stock: dict[str, int] = {}
        for tile in self.driver.find_elements(By.CSS_SELECTOR, ".quantity-tile"):
            label = tile.find_element(By.CSS_SELECTOR, ".quantity-tile__label").get_attribute("textContent").strip().lower()
            value_text = tile.find_element(By.CSS_SELECTOR, ".quantity-tile__value").get_attribute("textContent").strip()
            value = int(re.sub(r"[^0-9]", "", value_text))
            stock[label] = value
        return stock

    def save_evidence(self, name: str) -> str:
        path = os.path.join(EVIDENCE_DIR, name)
        self.driver.save_screenshot(path)
        return path

    def page_token(self) -> str:
        html = self.driver.page_source
        match = re.search(r'name="_token" value="([^"]+)"', html)
        self.assertIsNotNone(match, "CSRF token not found on page")
        return match.group(1)

    def cookie_header(self) -> str:
        return "; ".join(f"{c['name']}={c['value']}" for c in self.driver.get_cookies())

    def http_post(self, path: str, fields: dict[str, str], include_token: bool = True) -> tuple[int, str]:
        """POST with the browser session's cookies; useful for server-side status checks."""
        body = dict(fields)
        if include_token:
            body["_token"] = self.page_token()
        data = urllib.parse.urlencode(body).encode()
        request = urllib.request.Request(
            BASE_URL + path,
            data=data,
            method="POST",
            headers={
                "Cookie": self.cookie_header(),
                "Content-Type": "application/x-www-form-urlencoded",
                "User-Agent": "CodeBlocks-Selenium-Evidence/1.0",
            },
        )
        try:
            response = urllib.request.urlopen(request, timeout=20)
            return response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as exc:
            return exc.code, exc.read().decode("utf-8", "replace")

    def http_get(self, path: str) -> tuple[int, dict[str, str], str]:
        request = urllib.request.Request(
            BASE_URL + path,
            method="GET",
            headers={"Cookie": self.cookie_header(), "User-Agent": "CodeBlocks-Selenium-Evidence/1.0"},
        )
        try:
            with urllib.request.urlopen(request, timeout=20) as response:
                return response.status, dict(response.headers), response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as exc:
            return exc.code, dict(exc.headers), exc.read().decode("utf-8", "replace")

    def assert_stock_invariant(self, stock: dict[str, int]) -> None:
        total = stock["total"]
        parts = (
            stock["available"]
            + stock["borrowed"]
            + stock["damaged"]
            + stock["in repair"]
            + stock["lost"]
        )
        self.assertEqual(total, parts, f"Quantity invariant broken: {stock}")

    # ------------------------------------------------------------------- tests
    def test_01_login_session_and_password_rejection(self) -> None:
        self.clear_session()
        self.go("/login")
        pre_cookies = {c["name"]: c for c in self.driver.get_cookies()}

        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="login-input"]').send_keys("admin@codeblocks.test")
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="password-input"]').send_keys("Definitely-Wrong-123")
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="login-submit"]').click()
        error = self.wait_for_text('[data-testid="flash-error"]', "Invalid credentials")
        self.assertIn("Invalid credentials", error)
        self.assertIn("/login", self.driver.current_url)

        self.login(ADMIN)
        post_cookies = {c["name"]: c for c in self.driver.get_cookies()}
        session_name = "codeblocks_session"
        self.assertIn(session_name, post_cookies)
        self.assertTrue(post_cookies[session_name]["httpOnly"])
        self.assertEqual(post_cookies[session_name]["sameSite"], "Lax")
        if session_name in pre_cookies:
            self.assertNotEqual(
                pre_cookies[session_name]["value"],
                post_cookies[session_name]["value"],
                "Session identifier did not rotate after authentication",
            )
        self.assertIn("Dashboard", self.driver.title)
        self.save_evidence("01_admin_dashboard.png")

    def test_02_student_rbac_and_csrf(self) -> None:
        self.login(STUDENT)
        self.go("/reports")
        self.assertIn("/reports", self.driver.current_url)
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="error-403"]'))
        self.assertIn("Access Denied", self.driver.page_source)

        for path, marker in [
            ("/users", "Access Denied"),
            ("/audit", "Access Denied"),
            ("/returns/create?transaction=1", "Access Denied"),
        ]:
            self.go(path)
            self.assertIn(marker, self.driver.page_source, f"RBAC failed for {path}")

        # Unauthorized approval: Ana owns seed request 10; the role guard must reject it.
        self.go("/borrow/10")
        self.assertEqual(self.http_post("/borrow/10/approve", {})[0], 403)

        # Missing CSRF token is rejected before any business logic executes.
        status, _ = self.http_post("/notifications/read-all", {}, include_token=False)
        self.assertEqual(status, 419)
        self.save_evidence("02_student_rbac.png")

    def test_03_full_borrow_approve_return_workflow(self) -> None:
        self.login(STUDENT)
        stock_before = self.read_stock()
        self.assertGreaterEqual(stock_before["available"], 1)
        self.assert_stock_invariant(stock_before)

        self.go(f"/equipment/{EQUIPMENT_ID}")
        request_link = self.wait.until(
            lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="detail-borrow-button"]')
        )
        request_link.click()
        self.wait.until(lambda d: "/borrow/create" in d.current_url)

        Select(self.driver.find_element(By.CSS_SELECTOR, '[data-testid="borrow-equipment"]')).select_by_value(
            str(EQUIPMENT_ID)
        )
        quantity = self.driver.find_element(By.CSS_SELECTOR, '[data-testid="borrow-quantity"]')
        quantity.clear()
        quantity.send_keys("1")
        expected = (date.today() + timedelta(days=2)).isoformat()
        date_input = self.driver.find_element(By.CSS_SELECTOR, '[data-testid="borrow-return-date"]')
        self.driver.execute_script(
            "arguments[0].value = arguments[1];"
            "arguments[0].dispatchEvent(new Event('input', {bubbles:true}));"
            "arguments[0].dispatchEvent(new Event('change', {bubbles:true}));",
            date_input,
            expected,
        )
        purpose = (
            "Selenium end-to-end verification of approval, damaged return, stock movement, "
            "and notification " + uuid.uuid4().hex[:8]
        )
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="borrow-purpose"]').send_keys(purpose)
        self.click('[data-testid="borrow-submit"]')

        self.wait.until(lambda d: re.search(r"/borrow/\d+$", d.current_url))
        match = re.search(r"/borrow/(\d+)$", self.driver.current_url)
        self.assertIsNotNone(match)
        type(self).request_id = int(match.group(1))
        page = self.driver.page_source
        code_match = re.search(r"REQ-\d{4}-\d+", page)
        self.assertIsNotNone(code_match, "Request code not rendered")
        type(self).request_code = code_match.group(0)
        self.wait_for_text('[data-testid="flash-success"]', "submitted")
        self.assertIn("Pending laboratory review", self.driver.page_source)
        self.save_evidence("03_request_submitted.png")

        self.login(ADMIN)
        self.go(f"/borrow/{self.request_id}")
        self.click('[data-testid="approve-request"]')
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, "[data-modal-confirm]")).click()
        self.wait_for_text('[data-testid="flash-success"]', "Request approved")
        page = self.driver.page_source
        tx_match = re.search(r"TX-\d{4}-\d+", page)
        self.assertIsNotNone(tx_match, "Checkout transaction code not rendered")
        type(self).transaction_code = tx_match.group(0)
        self.assertIn(self.transaction_code, page)

        # The approved request is a checkout now, not a second approval target.
        status, duplicate_body = self.http_post(f"/borrow/{self.request_id}/approve", {})
        self.assertEqual(status, 200)
        self.assertIn("already been processed", duplicate_body)
        self.save_evidence("04_request_approved.png")

        stock_after_approval = self.read_stock()
        self.assert_stock_invariant(stock_after_approval)
        self.assertEqual(stock_after_approval["available"], stock_before["available"] - 1)
        self.assertEqual(stock_after_approval["borrowed"], stock_before["borrowed"] + 1)
        type(self).stock_after_approval = stock_after_approval

        self.go(f"/borrow/{self.request_id}")
        return_link = self.wait.until(
            lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid^="return-transaction-"]')
        )
        href = return_link.get_attribute("href")
        self.assertIsNotNone(href)
        tx_id_match = re.search(r"transaction=(\d+)", href)
        self.assertIsNotNone(tx_id_match)
        type(self).transaction_id = int(tx_id_match.group(1))
        return_link.click()
        self.wait.until(lambda d: "/returns/create" in d.current_url)

        # Browser-level check: bypass HTML validation and submit with no condition.
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="return-form"]'))
        self.driver.execute_script(
            "var form = document.querySelector('[data-testid=\"return-form\"]');"
            "form.setAttribute('novalidate', 'novalidate');"
            "var select = document.querySelector('[data-testid=\"return-condition\"]');"
            "select.removeAttribute('required');"
        )
        self.click('[data-testid="return-submit"]')
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="condition-error"]'))
        self.assertIn("Action not completed", self.driver.page_source)
        self.save_evidence("05_condition_required_422.png")

        # Direct check of the HTTP status contract.
        status, body = self.http_post(
            "/returns",
            {
                "transaction_id": str(self.transaction_id),
                "quantity": "1",
                "return_date": date.today().isoformat(),
                "notes": "Selenium verifies that a missing condition writes nothing.",
            },
        )
        self.assertEqual(status, 422, "Missing condition must return HTTP 422")
        self.assertIn('data-testid="condition-error"', body)
        self.assertIn("Condition is required", body)

        stock_after_invalid = self.read_stock()
        self.assertEqual(stock_after_invalid, stock_after_approval, "Invalid return changed stock")

        self.go(f"/returns/create?transaction={self.transaction_id}")
        self.wait.until(lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="return-form"]'))
        Select(self.driver.find_element(By.CSS_SELECTOR, '[data-testid="return-condition"]')).select_by_value("damaged")
        self.driver.find_element(By.CSS_SELECTOR, '[data-testid="return-notes"]').send_keys(
            "Selenium inspection: cracked lens mount, moved to damaged stock."
        )
        self.click('[data-testid="return-submit"]')
        self.wait.until(lambda d: d.current_url.rstrip("/").endswith("/returns"))
        self.wait_for_text('[data-testid="flash-success"]', "Return recorded")
        first_return = self.wait.until(
            lambda d: d.find_element(By.CSS_SELECTOR, '[data-testid="returns-table"] tbody tr')
        )
        return_text = first_return.text
        self.assertIn("Damaged", return_text)
        return_match = re.search(r"RET-\d{4}-\d+", return_text)
        self.assertIsNotNone(return_match, "Return code missing from returns table")
        type(self).return_code = return_match.group(0)
        self.save_evidence("06_damaged_return_recorded.png")

        stock_after_return = self.read_stock()
        self.assert_stock_invariant(stock_after_return)
        self.assertEqual(stock_after_return["available"], stock_after_approval["available"])
        self.assertEqual(stock_after_return["borrowed"], stock_after_approval["borrowed"] - 1)
        self.assertEqual(stock_after_return["damaged"], stock_after_approval["damaged"] + 1)
        self.assertEqual(stock_after_return["total"], stock_after_approval["total"])

        self.go(f"/borrow/{self.request_id}")
        self.assertIn("Completed", self.driver.page_source)
        self.assertIn("All units returned", self.driver.page_source)

        self.login(STUDENT)
        self.go("/notifications")
        notifications = self.driver.page_source
        self.assertIn("Borrowing request approved", notifications)
        self.assertIn("Return recorded", notifications)
        self.assertIn(self.return_code, notifications)
        self.save_evidence("07_student_notifications.png")

        self.login(ADMIN)
        self.go("/audit")
        self.assertIn("return.processed", self.driver.page_source)
        self.assertIn(self.return_code, self.driver.page_source)
        self.save_evidence("08_audit_trail.png")

    def test_04_report_reads_live_return_and_exports_csv(self) -> None:
        if not self.return_code:
            self.skipTest("Prerequisite workflow did not produce a return record")
        self.login(ADMIN)
        self.go("/reports?type=returns")
        page = self.driver.page_source
        self.assertIn(self.return_code, page)
        self.assertIn("Damaged", page)
        self.save_evidence("09_returns_report.png")

        status, headers, body = self.http_get("/reports/export?type=returns")
        self.assertEqual(status, 200)
        self.assertIn("text/csv", headers.get("Content-Type", "").lower())
        self.assertIn("attachment", headers.get("Content-Disposition", "").lower())
        rows = list(csv.reader(io.StringIO(body.lstrip("\ufeff"))))
        self.assertGreaterEqual(len(rows), 2, "CSV export has no data rows")
        self.assertEqual(rows[0][0], "Return Code")
        self.assertTrue(any(self.return_code == row[0] for row in rows[1:]), "Return missing from CSV export")


if __name__ == "__main__":
    unittest.main(verbosity=2)

