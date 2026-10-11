#!/usr/bin/env python3
"""Create installation credentials, keeping generated values out of terminal output."""
import argparse
import os
from pathlib import Path
import re
import secrets
from urllib.parse import urlsplit


def create_config(company, url, admin, output):
    if not company or any(c in company for c in ('\n', '\r', "'", '"', '$', '`', '\\')):
        raise ValueError('Company name must be nonempty and contain no quotes or shell interpolation characters.')
    parsed = urlsplit(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password or parsed.port or parsed.path not in ('', '/') or parsed.query or parsed.fragment or not re.fullmatch(r'[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?', parsed.hostname):
        raise ValueError('Public URL must be an HTTPS origin, for example https://regnskap.thunold-olsen.no.')
    if not re.fullmatch(r'[A-Za-z0-9_-]{3,32}', admin):
        raise ValueError('Admin login must be 3-32 letters, digits, underscores or hyphens.')
    values = {'PUBLIC_URL': url.rstrip('/'), 'COMPANY_NAME': company, 'ADMIN_LOGIN': admin}
    for key in ('DB_ROOT_PASSWORD', 'DB_PASSWORD', 'ADMIN_PASSWORD', 'INSTANCE_KEY'):
        values[key] = secrets.token_hex(32)
    fd = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as stream:
        for key, value in values.items():
            stream.write(f"{key}='{value}'\n")


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--company-name', required=True)
    parser.add_argument('--url', default='https://regnskap.thunold-olsen.no')
    parser.add_argument('--admin-login', default='regnskapadmin')
    args = parser.parse_args()
    create_config(args.company_name, args.url, args.admin_login,
                  Path(__file__).resolve().parents[1] / 'deploy/.env')
    print('Created deploy/.env (0600). Credentials were not printed. Keep this file with the private backup.')
