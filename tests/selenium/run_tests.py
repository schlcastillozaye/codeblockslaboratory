"""Run the Code Blocks Selenium suite and preserve execution evidence.

Examples:
    python tests/selenium/run_tests.py --reset-db --db-port 3307
    python tests/selenium/run_tests.py --base-url http://localhost/codeblocks-lab-inventory/public
    set CODEBLOCKS_HEADLESS=0 && python tests/selenium/run_tests.py

--reset-db runs `php database/setup.php --seed --force`, which resets demo data.
Only use it against a disposable/demo database.
"""

from __future__ import annotations

import argparse
import datetime as dt
import os
import subprocess
import sys
import time
import unittest
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
PROJECT_ROOT = HERE.parent.parent


class Tee:
    def __init__(self, *streams):
        self.streams = streams

    def write(self, data: str) -> None:
        for stream in self.streams:
            try:
                stream.write(data)
            except UnicodeEncodeError:
                encoding = getattr(stream, "encoding", None) or "utf-8"
                stream.write(data.encode(encoding, "replace").decode(encoding, "replace"))
            stream.flush()

    def flush(self) -> None:
        for stream in self.streams:
            stream.flush()


class RecordingResult(unittest.TextTestResult):
    """TextTestResult that also remembers successes for the JUnit artifact."""

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.successes: list[unittest.TestCase] = []

    def addSuccess(self, test: unittest.TestCase) -> None:
        super().addSuccess(test)
        self.successes.append(test)


def write_junit(result: RecordingResult, path: Path, elapsed: float) -> None:
    failures = {test.id(): text for test, text in result.failures}
    errors = {test.id(): text for test, text in result.errors}
    skipped = {test.id(): text for test, text in result.skipped}
    suite = ET.Element(
        "testsuite",
        {
            "name": "CodeBlocks Selenium",
            "tests": str(result.testsRun),
            "failures": str(len(result.failures)),
            "errors": str(len(result.errors)),
            "skipped": str(len(result.skipped)),
            "time": f"{elapsed:.3f}",
        },
    )
    cases: list[unittest.TestCase] = list(getattr(result, "successes", []))
    cases.extend(test for test, _ in result.failures)
    cases.extend(test for test, _ in result.errors)
    cases.extend(test for test, _ in result.skipped)
    seen: set[str] = set()
    for test in cases:
        test_id = test.id()
        if test_id in seen:
            continue
        seen.add(test_id)
        element = ET.SubElement(
            suite,
            "testcase",
            {
                "classname": test.__class__.__name__,
                "name": getattr(test, "_testMethodName", test_id.rsplit(".", 1)[-1]),
            },
        )
        if test_id in failures:
            node = ET.SubElement(element, "failure", {"message": "assertion failed"})
            node.text = failures[test_id]
        elif test_id in errors:
            node = ET.SubElement(element, "error", {"message": "unexpected error"})
            node.text = errors[test_id]
        elif test_id in skipped:
            ET.SubElement(element, "skipped", {"message": skipped[test_id]})
    ET.ElementTree(suite).write(path, encoding="utf-8", xml_declaration=True)


def main() -> int:
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass
    parser = argparse.ArgumentParser(description="Run Code Blocks Selenium evidence suite")
    parser.add_argument("--base-url", default=os.environ.get("CODEBLOCKS_BASE_URL", "http://127.0.0.1:8080"))
    parser.add_argument("--db-port", help="DB_PORT for --reset-db (e.g. 3307)")
    parser.add_argument("--reset-db", action="store_true", help="Reset demo data before the run")
    parser.add_argument("--no-headless", action="store_true", help="Show Chrome during the run")
    args = parser.parse_args()

    os.environ["CODEBLOCKS_BASE_URL"] = args.base_url.rstrip("/")
    if args.no_headless:
        os.environ["CODEBLOCKS_HEADLESS"] = "0"
    else:
        os.environ.setdefault("CODEBLOCKS_HEADLESS", "1")
    if args.db_port:
        os.environ["DB_PORT"] = args.db_port

    if args.reset_db:
        print("Resetting demo database (destructive)...")
        command = ["php", "database/setup.php", "--seed", "--force"]
        completed = subprocess.run(command, cwd=PROJECT_ROOT, env=os.environ.copy())
        if completed.returncode != 0:
            print("Database reset failed; tests were not started.", file=sys.stderr)
            return 2

    run_dir = HERE / "evidence" / dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    run_dir.mkdir(parents=True, exist_ok=True)
    os.environ["CODEBLOCKS_EVIDENCE_DIR"] = str(run_dir)
    log_path = run_dir / "execution.log"
    junit_path = run_dir / "junit.xml"

    log_file = log_path.open("w", encoding="utf-8")
    tee = Tee(sys.stdout, log_file)
    print(f"Base URL : {os.environ['CODEBLOCKS_BASE_URL']}")
    print(f"Evidence : {run_dir}")
    print(f"Started  : {dt.datetime.now().isoformat(timespec='seconds')}")
    print("-" * 78)

    loader = unittest.TestLoader()
    suite = loader.discover(str(HERE), pattern="test_*.py")
    runner = unittest.TextTestRunner(stream=tee, verbosity=2, descriptions=True, resultclass=RecordingResult)
    started = time.perf_counter()
    result = runner.run(suite)
    elapsed = time.perf_counter() - started

    write_junit(result, junit_path, elapsed)
    print("-" * 78)
    print(f"Finished : {dt.datetime.now().isoformat(timespec='seconds')}")
    print(f"Elapsed  : {elapsed:.1f}s")
    print(
        f"Tests    : {result.testsRun} run, {len(result.failures)} failed, "
        f"{len(result.errors)} errors, {len(result.skipped)} skipped"
    )
    print(f"Log      : {log_path}")
    print(f"JUnit    : {junit_path}")
    log_file.close()
    return 0 if result.wasSuccessful() else 1


if __name__ == "__main__":
    raise SystemExit(main())
