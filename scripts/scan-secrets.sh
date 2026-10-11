#!/usr/bin/env bash
# Gitleaks release archive locked to its verified upstream SHA-256.
set -euo pipefail
cd "$(dirname "$0")/.."
scan_root=$(mktemp -d /tmp/norway-scan.XXXXXXXX)
trap 'rm -rf -- "$scan_root"' EXIT
curl --fail --silent --show-error --location --retry 3 \
    https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/gitleaks_8.30.1_linux_x64.tar.gz \
    --output "$scan_root/gitleaks.tar.gz"
printf '%s  %s\n' 551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb "$scan_root/gitleaks.tar.gz" | sha256sum --check --status
tar -xzf "$scan_root/gitleaks.tar.gz" -C "$scan_root" gitleaks
"$scan_root/gitleaks" git . --log-opts=--all --redact --no-banner
