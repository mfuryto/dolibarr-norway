#!/usr/bin/env python3
"""Only main/staging are allowed; main promotions originate from local staging."""
import json
import os
from pathlib import Path


def validate(event):
    ref = event.get('ref', '')
    if ref.startswith('refs/heads/') and ref not in ('refs/heads/main', 'refs/heads/staging'):
        raise ValueError('Only main and staging branches are allowed.')
    pr = event.get('pull_request')
    if not pr:
        return
    expected_head = {'main': 'staging', 'staging': 'main'}.get(pr['base']['ref'])
    if not expected_head or pr['head']['ref'] != expected_head or pr['head']['repo']['full_name'] != pr['base']['repo']['full_name']:
        raise ValueError('PRs must connect main and staging in the same repository.')


if __name__ == '__main__':
    path = os.environ.get('GITHUB_EVENT_PATH')
    if path:
        validate(json.loads(Path(path).read_text()))
    print('PASS branch promotion policy')
