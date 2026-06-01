#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_MODULE="internautench"
MODULE_NAME="${1:-$DEFAULT_MODULE}"
MODULE_DIR="$ROOT_DIR/$MODULE_NAME"
OUTPUT_DIR="$ROOT_DIR/dist"
OUTPUT_FILE="$OUTPUT_DIR/${MODULE_NAME}.zip"

if [[ ! -d "$MODULE_DIR" ]]; then
  echo "Modulverzeichnis nicht gefunden: $MODULE_DIR" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_FILE"

if command -v zip >/dev/null 2>&1; then
  pushd "$ROOT_DIR" >/dev/null
  zip -rq "$OUTPUT_FILE" "$MODULE_NAME"
  popd >/dev/null
elif command -v python3 >/dev/null 2>&1; then
  MODULE_DIR="$MODULE_DIR" OUTPUT_FILE="$OUTPUT_FILE" python3 - <<'PY'
import os
import zipfile

module_dir = os.environ["MODULE_DIR"]
output_file = os.environ["OUTPUT_FILE"]
root_dir = os.path.dirname(module_dir)
module_name = os.path.basename(module_dir.rstrip(os.sep))

with zipfile.ZipFile(output_file, "w", compression=zipfile.ZIP_DEFLATED) as archive:
    for current_root, _, files in os.walk(module_dir):
        for file_name in files:
            file_path = os.path.join(current_root, file_name)
            archive_name = os.path.relpath(file_path, root_dir)
            archive.write(file_path, archive_name)
PY
else
  echo "Weder 'zip' noch 'python3' ist installiert. Kein ZIP kann erstellt werden." >&2
  exit 1
fi

echo "ZIP erstellt: $OUTPUT_FILE"