#!/usr/bin/env python3
"""Build an installable external module archive, excluding all test data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root = Path(__file__).resolve().parents[1]
base = root / 'htdocs/custom'
target = root / 'dist/module_norway-0.23.0.zip'
target.parent.mkdir(exist_ok=True)
with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
    for file in sorted((base / 'norway').rglob('*')):
        if file.is_file():
            archive.write(file, file.relative_to(base))
    archive.write(root / 'LICENSE', 'norway/LICENSE')
print(target)
