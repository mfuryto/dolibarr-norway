#!/usr/bin/env python3
"""Generate local test credentials; refuse to replace existing configuration."""
from pathlib import Path
import os
import secrets
path = Path(__file__).resolve().parents[1] / '.env'
fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w') as stream:
    for key in ('TEST_DB_ROOT_PASSWORD', 'TEST_DB_PASSWORD', 'TEST_ADMIN_PASSWORD'):
        stream.write(f'{key}={secrets.token_hex(24)}\n')
print('Created .env with random local test credentials (mode 0600).')
