#!/usr/bin/env python3
"""Build a deterministic wpdocs ZIP from the committed Git tree."""

from __future__ import annotations

import argparse
import hashlib
import re
import subprocess
import zipfile
from pathlib import Path

PLUGIN_SLUG = "wpdocs"
MAIN_FILE = "wpdocs.php"
RUNTIME_ROOTS = (
	"assets/",
	"src/",
	"lib/wenpai-admin-ui/",
	"lib/git-it-write/",
	"vendor/",
)
RUNTIME_FILES = {
	"wpdocs.php",
	"readme.txt",
	"LICENSE",
	"composer.json",
	"composer.lock",
}
SKIP_FILES = {
	"lib/wenpai-admin-ui/phpcs.xml.dist",
	"lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py",
	"vendor/bin/yaml-lint",
	"vendor/erusev/parsedown-extra/.travis.yml",
	"vendor/erusev/parsedown-extra/phpunit.xml.dist",
	"vendor/erusev/parsedown-extra/test/ParsedownExtraTest.php",
	"vendor/erusev/parsedown-extra/test/TestParsedown.php",
}
FORBIDDEN_PREFIXES = (
	"lib/plugin-update-checker/",
	"lib/updatepulse-updater/",
	"tests/",
	"docs/",
	"scripts/",
	"prototypes/",
	".forgejo/",
	".github/",
	"node_modules/",
	".tmp-giw/",
)


def git(*args: str) -> bytes:
	return subprocess.check_output(("git", *args))


def is_runtime_path(name: str) -> bool:
	if name in SKIP_FILES or name.startswith(FORBIDDEN_PREFIXES):
		return False
	if any(part.startswith(".") for part in name.split("/")):
		return False
	if name in RUNTIME_FILES:
		return True
	return any(name.startswith(p) for p in RUNTIME_ROOTS)


def tracked_runtime_files() -> list[str]:
	names: list[str] = []
	for raw in git("ls-tree", "-rz", "--full-tree", "HEAD").split(b"\0"):
		if not raw:
			continue
		metadata, raw_name = raw.split(b"\t", 1)
		_mode, object_type, _oid = metadata.decode("ascii").split()
		name = raw_name.decode("utf-8")
		if object_type != "blob":
			continue
		if is_runtime_path(name):
			names.append(name)
	if MAIN_FILE not in names:
		raise SystemExit(f"candidate zip missing main file {MAIN_FILE}")
	if "vendor/autoload.php" not in names:
		raise SystemExit("candidate zip missing vendor/autoload.php")
	if "lib/git-it-write/includes/publisher.php" not in names:
		raise SystemExit("candidate zip missing lib/git-it-write")
	if not any(n.startswith("lib/wenpai-admin-ui/") for n in names):
		raise SystemExit("candidate zip missing lib/wenpai-admin-ui/")
	if "LICENSE" not in names:
		raise SystemExit("candidate zip missing LICENSE")
	for name in names:
		if name.startswith(FORBIDDEN_PREFIXES):
			raise SystemExit(f"refusing path in zip: {name}")
	return sorted(names, key=lambda n: n.encode("utf-8"))


def header_version() -> str:
	text = git("show", f"HEAD:{MAIN_FILE}").decode("utf-8")
	m = re.search(r"^ \* Version:\s*(.+)$", text, re.M)
	if not m:
		raise SystemExit(f"no Version header in {MAIN_FILE}")
	return m.group(1).strip()


def validate_metadata(version: str) -> None:
	plugin = git("show", f"HEAD:{MAIN_FILE}").decode("utf-8")
	readme = git("show", "HEAD:readme.txt").decode("utf-8")
	const = re.search(r"define\(\s*'WPDOCS_VERSION'\s*,\s*'([^']+)'\s*\)", plugin)
	stable = re.search(r"^Stable tag:\s*(\S+)\s*$", readme, re.M)
	header = re.search(r"^ \* Version:\s*(\S+)\s*$", plugin, re.M)
	for label, match in (
		("plugin header", header),
		("WPDOCS_VERSION", const),
		("Stable tag", stable),
	):
		actual = match.group(1) if match else "<missing>"
		if actual != version:
			raise SystemExit(f"{label} mismatch: expected {version}, got {actual}")
	uri = re.search(r"^ \* Update URI:\s*(\S+)\s*$", plugin, re.M)
	if not uri or uri.group(1) != "https://updates.wenpai.net":
		raise SystemExit("Update URI must remain https://updates.wenpai.net")


def build(out: Path, version: str) -> str:
	validate_metadata(version)
	names = tracked_runtime_files()
	out.parent.mkdir(parents=True, exist_ok=True)
	if out.exists():
		out.unlink()
	h = hashlib.sha256()
	with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED) as zf:
		for name in names:
			data = git("show", f"HEAD:{name}")
			arc = f"{PLUGIN_SLUG}/{name}"
			zf.writestr(arc, data)
			h.update(data)
			h.update(arc.encode("utf-8"))
	digest = hashlib.sha256(out.read_bytes()).hexdigest()
	(out.parent / f"{out.name}.sha256").write_text(f"{digest}  {out.name}\n", encoding="ascii")
	print(f"archive={out}")
	print(f"sha256={digest}")
	print(f"files={len(names)}")
	return digest


def main() -> int:
	ap = argparse.ArgumentParser()
	ap.add_argument("--version", help="default: header Version")
	ap.add_argument("--output-dir", default="dist")
	ap.add_argument("--archive-name")
	args = ap.parse_args()
	version = args.version or header_version()
	name = args.archive_name or f"{PLUGIN_SLUG}-{version}-candidate.zip"
	build(Path(args.output_dir) / name, version)
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
