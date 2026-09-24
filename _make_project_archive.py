#!/usr/bin/env python3
"""Package the Magento 1 project into a timestamped zip under ./zip/.

Excludes runtime / cache / tool / private artifacts so the archive is
deployable to a fresh server.
"""

import os
import sys
import zipfile
from datetime import datetime
from pathlib import Path

PROJECT_ROOT = Path(r"D:\www\m1-test.com")
ZIP_DIR = PROJECT_ROOT / "zip"

# Directories to skip entirely (matched as any path segment anywhere in the tree)
EXCLUDE_DIRS = {
    ".git",
    ".agents",
    ".claude",
    ".codebuddy",
    ".codex",
    ".zcode",
    ".playwright-cli",
    ".idea",
    ".vscode",
    "node_modules",
    "__pycache__",

    # Magento runtime
    "var",
    "tmp",  # generic tmp
    "media/catalog/product/cache",
    "tests",

    # Our own archive folder (don't recurse)
    "zip",
}

# File-name patterns to skip (regex, applied to basename)
import re
EXCLUDE_FILE_PATTERNS = [
    re.compile(r"^_.*\.(php|html|js|sh)$", re.IGNORECASE),  # _*.php, _*.sh ...
    re.compile(r"^test_.*\.(php|html)$", re.IGNORECASE),
    re.compile(r"^delivery_proof_.*\.pdf$", re.IGNORECASE),
    re.compile(r"^QQ\d{8}-\d{6}\.jpg$", re.IGNORECASE),
    re.compile(r"\.swp$", re.IGNORECASE),
    re.compile(r"\.swo$", re.IGNORECASE),
    re.compile(r"^~$"),
    re.compile(r"^\.DS_Store$"),
    re.compile(r"^Thumbs\.db$", re.IGNORECASE),
    re.compile(r"^desktop\.ini$", re.IGNORECASE),
]

# Root-level files to always skip (regardless of pattern)
EXCLUDE_ROOT_FILES = {
    "22 06 2026 \u6536\u4ef6\u8d26\u6237\u4f7f\u7528\u6743\u9650 \u2014 \u5f00\u5173\u8bbe\u7f6e.pdf",
    "Douane Popup - Standalone.html",
    "QQ20260710-172758.jpg",
    "_checklogin.php",
    "_extract.js",
    "_oauth2_diag.sh",
    "_test_layout.php",
    "_test_check_cache.php",
    "_test_check_per_handle.php",
    "_test_diag.php",
    "_test_dump.php",
    "_test_dump2.php",
    "_test_exact_flow.php",
    "_test_full_flow.php",
    "_test_handle.php",
    "_test_hook.php",
    "_test_isolate.php",
    "_test_layout.php",
    "_test_layout2.php",
    "_test_layout3.php",
    "_test_layout4.php",
    "_test_layout5.php",
    "_test_layout6.php",
    "_test_pkg.php",
    "_test_pkg_layout.php",
    "_test_simple.php",
    "_test_stores.php",
    "_test_with_frontend.php",
    "_check_cache.php",
    "_trace.php",
    "_make_zip.py",
    "_make_project_archive.py",

    # Personal test artifacts at root
    "task_plan.md",
    "test-condition.html",
}

# Directories that should exist as empty markers (preserve structure but skip recurse)
PRESERVE_ONLY_DIRS = set()

# Directories that should exist as empty markers (preserve structure but skip recurse)
PRESERVE_ONLY_DIRS = set()


def is_excluded_dir(rel_path: Path) -> bool:
    """Return True if any segment of rel_path matches an exclude rule,
    OR if rel_path matches a multi-segment exclude like 'media/catalog/product/cache'.
    """
    parts = rel_path.parts
    # Match by exact multi-segment prefix (always split on '/')
    for exclude in EXCLUDE_DIRS:
        excl_parts = exclude.split("/")
        if len(excl_parts) > 1:
            if parts[:len(excl_parts)] == tuple(excl_parts):
                return True
    # Match by any single segment (so 'node_modules' anywhere is excluded)
    for part in parts:
        if part in EXCLUDE_DIRS:
            return True
    return False


def is_excluded_file(rel_path: Path, project_root: Path) -> bool:
    """Return True if the file should be skipped."""
    name = rel_path.name

    # Pattern-based exclusion (works at any depth)
    for pattern in EXCLUDE_FILE_PATTERNS:
        if pattern.match(name):
            return True

    # Root-level file exclusion
    if rel_path.parent == Path("."):
        if name in EXCLUDE_ROOT_FILES:
            return True

    return False


def main():
    if not ZIP_DIR.is_dir():
        ZIP_DIR.mkdir(parents=True, exist_ok=True)

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    zip_name = f"magento1_project_{timestamp}.zip"
    zip_path = ZIP_DIR / zip_name

    added = 0
    skipped = 0
    excluded_paths = []

    # Fixed zip timestamp (must be >= 1980-01-01 for zip standard)
    fixed_dt = (2025, 1, 1, 0, 0, 0)

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as zf:
        for root, dirs, files in os.walk(PROJECT_ROOT):
            root_path = Path(root)

            # Compute the relative path
            try:
                rel_root = root_path.relative_to(PROJECT_ROOT)
            except ValueError:
                continue

            # Filter excluded dirs (mutate dirs in-place to prevent descent)
            new_dirs = []
            for d in dirs:
                child_rel = (rel_root / d) if rel_root != Path(".") else Path(d)
                if is_excluded_dir(child_rel):
                    skipped += 1
                    continue
                new_dirs.append(d)
            dirs[:] = new_dirs

            for f in files:
                file_path = root_path / f
                rel_file = (rel_root / f) if rel_root != Path(".") else Path(f)

                if is_excluded_file(rel_file, PROJECT_ROOT):
                    skipped += 1
                    continue

                # Use forward slashes in zip archive (cross-platform friendly)
                arcname = str(rel_file).replace(os.sep, "/")

                # Write with fixed timestamp to avoid 1980 boundary errors
                info = zipfile.ZipInfo(filename=arcname, date_time=fixed_dt)
                info.compress_type = zipfile.ZIP_DEFLATED
                with open(file_path, "rb") as fh:
                    zf.writestr(info, fh.read())
                added += 1

    size_kb = zip_path.stat().st_size / 1024
    size_mb = size_kb / 1024

    print(f"Created: {zip_path}")
    print(f"  Files added: {added}")
    print(f"  Files/dirs skipped: {skipped}")
    print(f"  Size: {size_kb:.2f} KB ({size_mb:.2f} MB)")


if __name__ == "__main__":
    main()