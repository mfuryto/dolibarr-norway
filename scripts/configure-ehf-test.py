#!/usr/bin/env python3
"""Privately enable only the separate EHF test network in an existing synthetic environment."""
import getpass
import os
from pathlib import Path
import re
import tempfile

root = Path(__file__).resolve().parents[1]
path = root / '.env'
if path.is_symlink() or not path.is_file():
    raise SystemExit('Create the synthetic environment first with scripts/init-test-env.py.')
text = path.read_text()
if not all(re.search(r'^' + name + r'=.+$', text, re.M) for name in ['TEST_DB_ROOT_PASSWORD', 'TEST_DB_PASSWORD', 'TEST_ADMIN_PASSWORD']):
    raise SystemExit('Not a configured synthetic environment. Existing configuration was retained.')
key = getpass.getpass('Peppyrus TEST API key (hidden): ')
if not re.fullmatch(r'[A-Za-z0-9._~+=/:-]{12,512}', key):
    raise SystemExit('Unexpected key format. Existing configuration was retained.')
text = re.sub(r'^NORWAY_EHF_(?:MODE|TEST_API_KEY)=.*\n?', '', text, flags=re.M)
text = text.rstrip('\n') + '\nNORWAY_EHF_MODE=test\nNORWAY_EHF_TEST_API_KEY=' + key + '\n'
fd, name = tempfile.mkstemp(prefix='.env.ehf-', dir=root)
try:
    with os.fdopen(fd, 'w') as stream:
        stream.write(text)
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(name, path)
finally:
    if os.path.exists(name):
        os.unlink(name)
print('Test network configured privately. Recreate only the synthetic web service to load it.')
