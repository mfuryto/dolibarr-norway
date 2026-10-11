# Security policy

This project is under development. No version is approved for production
accounting. Security fixes are developed and tested on staging, then promoted
to main through a checked pull request. Dolibarr core and access-point services
have their own security policies and update responsibilities.

## Report a vulnerability

Use [GitHub private vulnerability reporting](https://github.com/mfuryto/dolibarr-norway/security/advisories/new).
Do not open a public issue with exploit details, credentials or accounting data.
Include the affected version, minimal synthetic reproduction, expected impact
and relevant configuration. Never attach real invoices or personal data.

The maintainer will assess the report and coordinate a fix and disclosure.
There is no guaranteed response deadline or paid support commitment.

## Repository controls

Main requires pull requests from staging and current passing source/policy and
Dolibarr integration checks from GitHub Actions. Staging permits ordinary
development pushes and runs CI after each push. Administrators are covered by
branch rules; force pushes and branch deletion are disabled on both. Actions have
read-only default permissions, cannot approve pull requests and are restricted
to GitHub-owned actions pinned to commit SHA. Secret scanning, push protection,
private vulnerability reporting and dependency vulnerability alerts are enabled.

No production credentials or real accounting data belong in source, fixtures,
issues, logs or pull requests. Test credentials are generated locally and ignored
by Git. Gitleaks scans fetched Git history in CI. Scanning is a useful control,
not a guarantee that all sensitive information will be detected.

Updates are applied manually on staging and must pass compatibility tests.
Automatic update PRs are disabled to keep only main and staging branches. Container
digests remain pinned; a new Dolibarr version requires updating the supported
version policy and tests. Repo settings do not harden the running Docker host
or make the module production-ready.
