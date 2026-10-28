#!/usr/bin/env python3
"""Build a version-checked PrestaShop ZIP from the committed module tree."""
import hashlib
from pathlib import Path
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from zipfile import ZipFile

root = Path(__file__).resolve().parents[1]
version = sys.argv[1]
if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", version):
    raise SystemExit("Expected a stable semantic version, e.g. 1.0.0")

def committed(path):
    return subprocess.check_output(["git", "show", "HEAD:" + path], cwd=root)

config = ET.fromstring(committed("config.xml"))
php_version = re.search(rb"\$this->version\s*=\s*'([^']+)'", committed("agnfe.php"))
if config.findtext("name") != "agnfe" or config.findtext("version") != version:
    raise SystemExit("config.xml does not match the requested module/version")
if not php_version or php_version.group(1).decode() != version:
    raise SystemExit("agnfe.php does not match the requested version")

archive = root / "dist" / ("agnfe-" + version + ".zip")
archive.parent.mkdir(exist_ok=True)
subprocess.run([
    "git", "archive", "--format=zip", "--prefix=agnfe/",
    "--output=" + str(archive), "HEAD",
], cwd=root, check=True)

with ZipFile(archive) as package:
    if package.testzip() is not None:
        raise SystemExit("Corrupt ZIP")
    for name in package.namelist():
        relative = Path(name).relative_to("agnfe")
        if relative.parts and (relative.parts[0].startswith(".")
                or relative.parts[0] in {"tests", "scripts", "dist", "agnfe_private"}):
            raise SystemExit("Unexpected package entry: " + name)
    for path in [
        "agnfe.php", "config.xml", "classes/AgNfeDocument.php",
        "controllers/admin/AdminAgNfeDocumentsController.php",
        "controllers/front/download.php", "LICENSE", "README.md",
    ]:
        if package.read("agnfe/" + path) != committed(path):
            raise SystemExit("Package differs from commit: " + path)

digest = hashlib.sha256(archive.read_bytes()).hexdigest()
print(str(archive.relative_to(root)) + " SHA-256 " + digest)
