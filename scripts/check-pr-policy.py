#!/usr/bin/env python3
"""Check that main promotion PRs originate from this repository's staging."""
import json
import os
from pathlib import Path


def validate(event):
    pr = event.get('pull_request')
    if not pr or pr['base']['ref'] != 'main':
        return
    if pr['head']['ref'] != 'staging' or pr['head']['repo']['full_name'] != pr['base']['repo']['full_name']:
        raise ValueError('PRs to main must originate from staging in the same repository.')


if __name__ == '__main__':
    path = os.environ.get('GITHUB_EVENT_PATH')
    if path:
        validate(json.loads(Path(path).read_text()))
    print('PASS branch promotion policy')
