#!/usr/bin/env python3
"""Build an installable external module archive, excluding all test data."""
from pathlib import Path
import re
from zipfile import ZipFile, ZIP_DEFLATED
root = Path(__file__).resolve().parents[1]
base = root / 'htdocs/custom'
version = re.search(r"\$this->version\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", (base / 'norway/core/modules/modNorway.class.php').read_text()).group(1)
if f'**Versjon {version} · Under utvikling**' not in (root / 'README.md').read_text() or (base / 'norway/README.md').read_text().splitlines()[0] != f'# Norway {version}':
    raise SystemExit('README versions must match the module version before packaging')
target = root / f'dist/module_norway-{version}.zip'
target.parent.mkdir(exist_ok=True)
with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
    for file in sorted((base / 'norway').rglob('*')):
        if file.is_file():
            archive.write(file, file.relative_to(base))
    archive.write(root / 'LICENSE', 'norway/LICENSE')
print(target)
